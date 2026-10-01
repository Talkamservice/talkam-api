# Chat & Therapy Session Flow

How real-time messaging and the therapy-session lifecycle work in this codebase, and how the two connect. Written from the actual implementation (service classes, models, constants) — not the product spec — so it stays accurate as the source of truth.

## 1. Therapy session lifecycle

### 1.1 Coverage — who's paying

Every session has a `coverage` value (`therapy_sessions.coverage`, default `consumer`), resolved once at booking time by `CoverageResolver::resolve()` (`app/Services/Business/CoverageResolver.php`) and then fixed for the life of the session:

| Coverage | Who pays | Therapist paid |
|---|---|---|
| `consumer` | The client, via Flutterwave | On completion |
| `org_bundle` | Drawn from the org's prepaid session bundle | On completion |
| `org_meter` | Postpay — the org is billed later | After the org settles the invoice |
| `org_external` | The org's own therapist — settled outside TalkAM entirely | Never (no TalkAM credit) |
| `blocked` | N/A — booking is rejected | — |

`org_covered = coverage !== 'consumer'`. This single flag governs almost every fork described below.

### 1.2 Booking (`SessionBookingService::create()`)

1. Validates `therapist_id` / `starts_at` / `format`, checks the slot is actually in the therapist's real availability (`TherapistSlotService::matchSlot()`).
2. Enforces the org's per-employee session cap (`SessionCapService`), independent of coverage.
3. Resolves coverage (above); rejects with the real reason if `blocked`.
4. **Clash guard** (inside a DB transaction, `lockForUpdate()`): rejects if another session at the same therapist + `starts_at` is already `active()` — see [1.6](#16-the-active-scope--why-hold_expires_at-matters).
5. Creates the row as `status = pending_payment` always — even an org-covered booking starts here, because the therapist still gets to accept/decline it (see 1.3).
6. `hold_expires_at`:
   - **Consumer**: `now() + booking.hold_minutes` — a real payment hold; a background sweep releases it if payment never lands.
   - **Org-covered**: `null` — there's no payment to hold, so there's nothing to expire.
7. `org_bundle` additionally draws one session from the org's bundle ledger (`BundleLedgerService::draw()`), refunded if the session is later cancelled.
8. Org-covered bookings notify both sides immediately (`SessionBookedNotification`) since nothing is left to pay. A consumer booking stays quiet until payment actually clears (webhook-driven, see 1.4).

### 1.3 Therapist review / confirmation

A `pending_payment` session isn't "real" until the therapist has acted:

- **Org-covered**: `SessionBookingService::confirmIfAwaitingReview()` flips `pending_payment → confirmed` the moment the therapist acknowledges/accepts — there's no payment gate, so acceptance *is* confirmation.
- **Consumer**: stays `pending_payment` regardless of therapist acceptance; the client still has to pay.

The same request/propose flow (`TherapistSessionRequestService::propose()`) reuses this — proposing a concrete time from a session *request* is itself the therapist's acceptance.

### 1.4 Payment (consumer path only)

`SessionBookingService::initiatePayment()` creates a pending `Payment` row and a Flutterwave checkout payload. The webhook handler (`SessionPaymentHandlerService`, not detailed here) is what actually flips `pending_payment → confirmed` and sends `SessionBookedNotification` once Flutterwave confirms the charge.

### 1.5 Join / in-progress (`SessionLifecycleService::join()`)

- Only `confirmed` or `in_progress` sessions are joinable; every other status has a specific, user-facing rejection reason (e.g. `pending_payment` reads differently depending on whether the client or the therapist is the one still owed an action).
- **Join window**: opens `SESSION_JOIN_EARLY_MINUTES` before `starts_at` (config: `therapist.sessions.join_early_minutes`) and closes at `starts_at + duration_minutes`. Both edges are enforced server-side.
- The Agora channel + token are minted **before** the session row is touched — if the AV provider call fails, the session must not end up stamped `in_progress` with no actual way to join.
- First join of either side flips the session to `in_progress` and stamps `started_at`. Each side's own `{role}_joined_at` / `{role}_left_at` are tracked independently (a rejoin clears a prior leave mark).

### 1.6 Completion

Two independent paths reach `completed`:

1. **Early completion** (`SessionLifecycleService::leave()` → `completeIfBothLeft()`): if both sides joined *and* both have now left, and the scheduled end time hasn't passed yet, complete immediately rather than waiting on the sweep.
2. **Sweep** (`SessionLifecycleService::sweep()`, run on a schedule): catches every `confirmed`/`in_progress` session whose `starts_at + duration_minutes` is already in the past.
   - Client never joined → `no_show`, no refund, but the org's bundle draw (if any) is returned (`refundBundleIfCovered()`).
   - Therapist never joined while the client did → `no_show` + refund.
   - Both joined → `completed`.

Both paths call `EarningsLedgerService::creditForSession()` on completion — the one place a therapist's balance actually moves for a session (except `org_external`, which is settled outside TalkAM and never credited).

There's also a webhook path: `SessionLifecycleService::handleRoomClosed()` — if the AV room closes and both sides had joined at some point, complete immediately; a solo join-then-leave is left for the sweep to correctly resolve as a no-show, not treated as a completion.

### 1.7 The `active()` scope — why `hold_expires_at` matters

`TherapySession::scopeActive()` is what the booking clash guard (1.2) and slot-availability checks are built on:

```php
$q->whereIn('status', [CONFIRMED, IN_PROGRESS, COMPLETED])
  ->orWhere(fn ($hold) => $hold->where('status', PENDING_PAYMENT)
      ->where(fn ($expiry) => $expiry->whereNull('hold_expires_at')
          ->orWhere('hold_expires_at', '>', now())));
```

The `whereNull('hold_expires_at')` branch is load-bearing: an org-covered `pending_payment` session has no expiry (1.2), so without it, an org-covered booking would never block its own slot and a second employee could book the exact same therapist at the exact same time. (This was a real, shipped double-booking bug — fixed by adding that branch. Don't remove it without re-checking this reasoning.)

### 1.8 Cancellation

`SessionLifecycleService::cancel()` — not detailed exhaustively here; reverses the bundle draw for `org_bundle` sessions via the same ledger `refundBundleIfCovered()` uses on no-show.

### 1.9 Status reference

| Status | Meaning |
|---|---|
| `pending_payment` | Booked, awaiting therapist acceptance (org-covered) or client payment (consumer) |
| `confirmed` | Both sides cleared to join once the window opens |
| `in_progress` | At least one side has joined |
| `completed` | Both sides attended (or early-completed per 1.6) |
| `cancelled` | Cancelled before it happened |
| `no_show` | Past its scheduled end with one or both sides never joining |
| `failed` | Consumer payment failed |
| `expired` | Consumer payment hold ran out unpaid |

### 1.10 Session notes

`SessionNoteService` (`app/Services/Therapist/SessionNoteService.php`) — one note per session (upserted, not append-only). A note can be flagged shared-with-client; on the `false → true` transition it's **delivered into the real chat thread** — see [3.2](#32-session-notes--chat).

## 2. Chat / messaging

### 2.1 Data model

- `Conversation` — one thread, `status` in `{ awaiting_response, active, ... }` (`StatusConstants`).
- `ConversationMember` — membership row per participant; also carries per-user state: muted/archived/starred, seen timestamps.
- `Message` / `MessageEdit` / `MessageHide` / `MessageReaction` / `MessageDraft` — message content and its edit/reaction/hide history.

### 2.2 Opening a conversation (`ConversationService`)

- `create(['receiver_id' => ...])` — creates a new conversation **or returns the existing one** between the two users (idempotent by design). A first-contact conversation starts `awaiting_response`.
- `currentConversation()` — resolves "the" conversation for a given context without creating a new one.
- Both methods run inside a DB transaction; both **must** call `DB::commit()` on every return path, including the early "conversation already exists" branch — a real bug here (an early `return` that skipped the commit) silently discarded every subsequent write in the same request until the connection closed. Already fixed; worth remembering if this method grows a new early return.

### 2.3 Real-time transport

Laravel broadcasting, driver-configured (`log` locally, Pusher in real deployments — see `config/broadcasting.php`). Private/presence channels are registered in `routes/channels.php`:

| Channel | Access rule |
|---|---|
| `conversation.{conversationId}` | Must be a `ConversationMember` of that conversation |
| `presence-user.{id}` | That user, or a conversation co-member while their `activity_status` is on (presence channel) |
| `App.Models.User.{id}` | Must be that exact user (general per-user notifications) |
| `refresh-notification.{userId}` | Must be that exact user |

Note: channel patterns are registered **without** the `private-`/`presence-` prefix — Laravel strips it before matching, so a pattern that includes the prefix silently 403s every subscriber. The client still subscribes with the prefixed name; only the server-side registration stays bare.

Events broadcast on these channels (`app/Events/Messaging/*`, plus `app/Events/NewMessage.php`, `ReceiveMessage.php`, `RefreshNotification.php`):

`NewMessage`, `ReceiveMessage` (synchronous — `ShouldBroadcastNow`, for latency-sensitive delivery), `MessageDelivered` (also synchronous), `MessageRead`, `MessageEdited`, `MessageDeleted`, `MessagePinned` / `MessageUnpinned`, `MessageReactionAdded` / `MessageReactionRemoved`, `ConversationSeen`, `UserTyping`, `UserPresenceChanged`, `RefreshNotification`.

### 2.4 Message actions (`MessageActionService`)

`send()`, `sendFile()` (attach a real uploaded file, optional caption), `edit()`, `delete()`, `forward()`, `addReaction()` / `removeReaction()`, `setPinned()`, `bulkMarkRead()`. Each is a real, independent method — not a single generic "update message" endpoint.

### 2.5 Identity & anonymization

A client is anonymised to a therapist by default: `SessionNotificationSupport::anonRef($userId)` → `"Anonymous · #" + (4000 + userId % 6000)` — a stable, deterministic pseudonym, not a real lookup.

Real-name reveal happens when either:
- The org's coverage/`coverage_enabled` flag genuinely allows it for that session, or
- `CoverageResolver::shareOrganization($userIdA, $userIdB)` — both users are **active** members of the **same organization** (independent of the coverage flag; a real "you already work together" signal, not a payment-coverage one).

`ConversationStateService::serialize()`'s `other_member.same_organization` and `TherapistDashboardService::clientRef()` both consult this — the same real signal, checked in two different UI surfaces (messages list, dashboard client cards).

## 3. Where the two systems connect

### 3.1 `startConversation()` — booking-scoped chat

`SessionLifecycleService::startConversation($user, $bookingId)` is the actual bridge:

1. Resolves the *other* participant **server-side from the session row**, not from a client-supplied id — this is what lets the therapist-facing app never learn a client's real `user_id`, only their anonymised ref.
2. Opens (or reuses, via `ConversationService::create()`) the conversation between them.
3. **Skips the cold-start `awaiting_response` gate** — a confirmed/in-progress/completed booking is itself proof these two are allowed to talk, so the conversation is force-activated instead of sitting in the normal stranger-messaging queue.

### 3.2 Session notes → chat

When a therapist marks a session note shared (`SessionNoteService`, the `false → true` transition only):

1. Opens/reuses the same booking-scoped conversation as above (`ConversationService::create()`).
2. Formats the note as `"{title}\n\n{content}\n\n— Shared by {therapist} on {date}"`.
3. Writes it to a real temp file, stores it as a proper uploaded file (`FileService`), and sends it as a **file message** via `MessageActionService::sendFile()` — the note literally arrives in the client's chat as an attachment, not a special "note" message type.
4. Best-effort: wrapped in try/catch, logged on failure, never blocks the note save itself.

### 3.3 Coverage governs both

`coverage`/`same_organization` isn't just a billing detail — it's the same signal that decides whether a client's real name is shown in chat (2.5) and on the therapist dashboard, independent of whether the *session itself* was org-covered. A consumer-paid session between two people who happen to share an org still gets real-name treatment; an org-covered session between people at different orgs does not.

## 4. Where to look next

- Booking: `app/Services/Therapist/SessionBookingService.php`, `SessionLifecycleService.php`
- Coverage: `app/Services/Business/CoverageResolver.php`, `app/Constants/Business/SessionCoverageConstants.php`
- Messaging: `app/Services/Messaging/ConversationService.php`, `app/Services/Messaging/V2/{MessageActionService,ConversationStateService}.php`
- Real-time: `routes/channels.php`, `app/Events/Messaging/`
- Session notes: `app/Services/Therapist/SessionNoteService.php`
