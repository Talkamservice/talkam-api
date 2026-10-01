# Chat / Session Questions — Answered

Investigated 2026-09-28 directly against the code in `talkam-api` (composer.json root: `/Users/morrison/Workspace/Work/talkam/talkam-api`). Every answer below is grounded in the actual source — file:line citations included so you can verify. Companion doc: [CHAT_AND_SESSION_API_REFERENCE.md](CHAT_AND_SESSION_API_REFERENCE.md) has the full endpoint-by-endpoint reference these answers feed into.

---

## Prerequisite — security (raised independently)

### Q0. Is there a server-side broadcasting auth route today, or is the client expected to sign its own auth challenge?

**A server-side route exists and is correct — but a second, dangerous one also exists and needs fixing.**

**1. The real, working route:** `POST /broadcasting/auth`
- `app/Providers/BroadcastServiceProvider.php:16` — `Broadcast::routes(['middleware' => ['auth:sanctum']]);`, registered via `config/app.php:170`.
- This is Laravel's standard `Broadcast::auth($request)` flow. It evaluates the callbacks in `routes/channels.php`, so `conversation.{id}` is checked against real `ConversationMember` rows (`routes/channels.php:37`), and `App.Models.User.{id}` / `presence-user.{id}` / `refresh-notification.{userId}` are checked against the caller's own id.
- Auth guard: `auth:sanctum` — the same bearer token the app already uses everywhere else.
- Regression-tested: `tests/Feature/V2/Messaging/BroadcastAuthTest.php:36-78` (its docblock references a prior "v1 hole" where any authenticated user could subscribe to any private channel — now fixed for **this** route).

**Conclusion: the mobile client does not need to sign anything on-device.** It should authenticate `POST /broadcasting/auth` with its normal Sanctum bearer token, exactly like Pusher's standard JS/native SDK flow expects — no custom code.

**2. A second, dangerous endpoint exists and reintroduces the hole:** `POST /api/v1/broadcasting/auth`
- Route: `routes/api_v1.php:310` (sits outside the `api/v1` prefix group in the file, but `RouteServiceProvider.php:33-35` wraps the whole file in that prefix anyway, so the live path is `/api/v1/broadcasting/auth`).
- Handler: `app/Http/Controllers/Api/V1/General/AuthController.php:12-46`. It requires only `auth()->user()`, then builds a raw `Pusher\Pusher` client from `config('broadcasting.connections.pusher.*')` and calls `$pusher->socket_auth($channelName, $socketId)` on **whatever channel name the client sends** — no `Broadcast::auth()` call, no reference to `routes/channels.php` at all.
- **Net effect: any authenticated user can get a validly-signed subscription to any private channel** — someone else's `conversation.{id}`, someone else's `refresh-notification.{userId}`, etc. This is the same class of hole `routes/channels.php:11-13`'s comment and the `BroadcastAuthTest` docblock describe as fixed — the fix only covers route #1. This v1 route has no test coverage.

**On the "app secret compiled into the binary" claim:** `config/broadcasting.php:34-49` and `.env.example` show `PUSHER_APP_SECRET` is read only server-side and used only in the two signing paths above. Nothing in this repo ships the secret to a client config endpoint. The backend gives no explanation for how the secret ended up in the mobile binary — that's a mobile-side leak, not caused by this API.

**Action items:**
1. Mobile: stop on-device signing immediately, call `POST /broadcasting/auth` with the existing Sanctum bearer token instead.
2. Backend: fix or delete `POST /api/v1/broadcasting/auth` (`routes/api_v1.php:310`, `AuthController::authenticate`) — it's a live authorization bypass reachable by any logged-in user, right now, independent of the mobile migration.
3. Rotate `PUSHER_APP_SECRET` once mobile stops embedding it — a secret compiled into shipped binaries must be treated as compromised regardless of where it leaked from.

---

## Chat / messaging

### Q1. Event → channel mapping

| Event | File | Broadcast type | Channel |
|---|---|---|---|
| `NewMessage` | `app/Events/NewMessage.php:34-39` | `ShouldBroadcast` | `Channel('private-conversation.'+id)` (prefix hand-baked) |
| `ReceiveMessage` | `app/Events/ReceiveMessage.php:41-46` | `ShouldBroadcastNow` | `PrivateChannel('conversation.'+id)` — **only** this channel |
| `MessageDelivered` | `app/Events/Messaging/MessageDelivered.php:20-23` | `ShouldBroadcastNow` | `PrivateChannel('conversation.'+id)` — only |
| `MessageRead` | `app/Events/Messaging/MessageRead.php:20-23` | `ShouldBroadcastNow` | `PrivateChannel('conversation.'+id)` — only |
| `MessageEdited` | `app/Events/Messaging/MessageEdited.php:19-22` | `ShouldBroadcast` | `Channel('private-conversation.'+id)` |
| `MessageDeleted` | `.../MessageDeleted.php:19-22` | `ShouldBroadcast` | same pattern |
| `MessagePinned`/`MessageUnpinned` | `.../MessagePinned.php` / `MessageUnpinned.php:19-22` | `ShouldBroadcast` | same pattern |
| `MessageReactionAdded`/`Removed` | `.../MessageReactionAdded.php` / `MessageReactionRemoved.php:19-22` | `ShouldBroadcast` | same pattern |
| `ConversationSeen` | `.../ConversationSeen.php:19-22` | `ShouldBroadcast` | same pattern |
| `UserTyping` | `.../UserTyping.php:19-22` | `ShouldBroadcast` | same pattern |
| `UserPresenceChanged` | `.../UserPresenceChanged.php:19-22` | `ShouldBroadcast` | `Channel('presence-user.'+userId)` — **the only** event on this channel |
| `RefreshNotification` | `app/Events/RefreshNotification.php:32-37` | `ShouldBroadcast` | `Channel('refresh-notification.'+userId)` |

**No message-content event ever broadcasts on `App.Models.User.{id}` or `presence-user.{id}`.** `ReceiveMessage::broadcastOn()` returns exactly `conversation.{conversationId}` and nothing else. `presence-user.{id}` carries only online/away/offline status via `UserPresenceChanged`, no message content.

Minor inconsistency noted (not a bug): `ReceiveMessage`/`MessageDelivered`/`MessageRead` use `PrivateChannel('conversation.'+id)` (framework adds the `private-` prefix), while all the other Messaging events hand-write `Channel('private-conversation.'+id)`. Both resolve to the same wire channel name.

### Q2. What are `App.Models.User.{id}` and `presence-user.{id}` actually used for today?

- **`App.Models.User.{id}`**: registered in `routes/channels.php:18-20` with a correct auth callback (`(int)$user->id === (int)$id`), but a full-codebase search of `"App.Models.User"` finds **no other reference anywhere** — no event, notification, or job broadcasts on it. It is registered and **100% unused**.
- It is **not** implicitly Laravel's default notification-broadcast channel in practice here — that only kicks in when a `Notification` class implements `ShouldBroadcast`, and `grep -rl ShouldBroadcast app/Notifications/` returns zero results. No notification classes broadcast at all currently.
- **Adding it to `ReceiveMessage::broadcastOn()` would work with zero other backend changes** — the channel is already registered with the right auth rule, so `Broadcast::routes()` would authorize it immediately. The only change needed: add `new PrivateChannel('App.Models.User.'.$this->userId)` inside `ReceiveMessage::broadcastOn()` (`app/Events/ReceiveMessage.php:41-46` — the constructor already receives the receiver's `$userId`).
- `presence-user.{id}`: two hits total in the codebase — its registration and `UserPresenceChanged::broadcastOn()`. Nothing else touches it.

### Q3. Feasibility/cost of adding a per-user channel to `ReceiveMessage`/`MessageDelivered`

Cheap on fan-out, adds real per-request latency, and has a payload/privacy tradeoff worth flagging — not a rate-limit problem.

- **Conversations are strictly 1:1** — confirmed in `MessageActionService::send()` (`app/Services/Messaging/V2/MessageActionService.php:72-74`, picks exactly one counterpart via `->first()`), `ConversationService::create()` (rejects self-chat), and `ConversationStateService::serialize()` (singular `other_member`). No DB constraint enforces it, but no code path ever creates a 3rd member. So current fan-out per event is already 1 recipient — adding a second channel doesn't multiply by membership, it just adds one more channel-publish per send.
- **Real cost: latency, not fan-out.** Both `ReceiveMessage` and `MessageDelivered` implement `ShouldBroadcastNow` deliberately (per their own doc comments) because `QUEUE_CONNECTION=sync` and there's no worker — broadcasting happens synchronously inside the HTTP request. A second channel means a second synchronous Pusher HTTP round-trip before the API response returns.
- **No rate limiting exists on broadcasts** in this codebase — the only throttle found is the generic API request limiter (`Limit::perMinute(60)`, `app/Providers/RouteServiceProvider.php:27-29`), unrelated to broadcast events.
- **Privacy/payload consideration**: the full message payload (including `file_id`, content, etc.) would now reach a client's globally-subscribed channel even for conversations they're not currently viewing — a UX/battery/privacy tradeoff to design around (e.g. strip content and send a lightweight "you have a new message in conversation X" notification instead of the full payload), not a technical blocker.

### Q4. Is there a separate "v1" messaging system?

**Split answer — conversations and messages are not symmetric:**

- **Conversations**: v1's `ConversationController` (`app/Http/Controllers/Api/V1/User/Messaging/ConversationController.php`) uses the exact same `App\Services\Messaging\ConversationService` the doc describes as backing v2. In fact, `/user/messaging/conversations/current/fetch`, `/createconversations`-equivalent, and `update-status` are routed through this **same v1 controller class from inside `routes/api_v2.php`** (imported as `V1ConversationController`) — it's not legacy, it's shared and actively used by both API versions. `ConversationService.php` was last modified Sep 27 (recent).
- **Messages**: v1's `MessagingController` (`app/Http/Controllers/Api/V1/User/Messaging/MessagingController.php`, routes `messages/list`/`/send`/`/delete/{id}` in `routes/api_v1.php:196-199`) uses a genuinely **separate, older, much simpler** service: `App\Services\Messaging\MessageService` (78 lines, last touched Aug 7 — unchanged since initial build). It does a bare `Message::create()` with no file-attachment support via `FileService`, no reactions/edit/pin/forward, and no membership check on `list()`. It also has a dead import (`use App\Events\RefreshMessage;` — that class doesn't exist anywhere in the codebase), a clear signal it's unmaintained.
- v2's `MessageController`/`MessageActionController` (`app/Http/Controllers/Api/V2/Messaging/`) back onto `MessageActionService` (416 lines, modified Sep 26-27) — file attachments, edit/delete windows, reactions, pin, forward, block enforcement, read-receipt privacy.

**Bottom line for the mobile team**: if `/user/messaging/conversations/current/fetch` is what you're calling, you're already on the live, maintained `ConversationService` regardless of version prefix — safe to keep. If message send/list is going through the v1 `messages/list` / `/send` endpoints (`MessageService`-backed), you're on the stale, unmaintained path missing attachments/reactions/edit/pin — should move to v2's `MessageController`/`MessageActionController`.

### Q5. Real `Conversation.status` string values, and accept/decline literals

`app/Constants/General/StatusConstants.php` — actual constants:
```php
const ACTIVE = "Active";                       // line 7
const AWAITING_RESPONSE = "Awaiting_Response";  // line 10
const DECLINED = "Declined";                    // line 24
const ACCEPTED = "Accepted";                    // line 43
```
(Also present but unrelated to conversations: `CONFIRMED = "Comfirmed"` — yes, a real typo in the codebase — used for payments/sessions, not conversation status.)

New conversations default to `Awaiting_Response`. Booking-scoped conversations get force-set to `Active` (`app/Http/Controllers/Api/V2/Messaging/ConversationController.php:38`).

**Accept/decline endpoint**: `POST /user/messaging/conversations/update-status` (both `/api/v1/...` and `/api/v2/...`, both route to the same v1 controller → `ConversationService::updateStatus()`). Validation (`app/Services/Messaging/ConversationService.php:204-215`) accepts `status` only from `MessagingConstants::CONVERSATION_ACTIONS` = `{"Accepted" => "Accepted", "Declined" => "Declined"}`.

**Confirms the mobile client's current values are correct**: the endpoint accepts exactly `"Accepted"` / `"Declined"` (case-sensitive) — no lowercase variants validate.

### Q6. How does a client resolve `file_id` into a downloadable URL?

**No dedicated "get file by id" endpoint exists.** The messages-list response already includes the resolved URL — no second round-trip needed.

- `Message::file()` → `File` model; `File::url()` (`app/Models/File.php:24-29`) calls `MethodsHelper::readFileUrl('encrypt', $path)`, which base64-encodes the storage path and returns `route('web.read_file', $path)` — a **web** route (`routes/web.php:31`, `GET /file/{path}`), not a versioned API route.
- The message-list controller already embeds this (`app/Http/Controllers/Api/V2/Messaging/MessageController.php:88-90`):
  ```php
  "file_id" => $message->file_id,
  "file_url" => $message->file?->url(),
  "file_name" => $message->file?->name,
  ```
- **Confirms Q6/section 3.2 concern is unfounded**: a session note delivered as a file message will already carry a ready-to-use `file_url` in the same message payload the mobile client receives from `messages/list` — it can render the attachment directly without needing a separate file-fetch endpoint.

### Q7. Exact request/response shapes for the core messaging endpoints

See [CHAT_AND_SESSION_API_REFERENCE.md § Messaging endpoints](CHAT_AND_SESSION_API_REFERENCE.md#messaging-endpoints) for the full field-by-field reference (routes, validation rules, response shapes) for conversations list/create, messages list/send, and the mute/archive/star/seen actions — pulled directly from `routes/api_v2.php`, the FormRequest/inline validation, and the resource/transformer classes.

---

## Therapist / session feature

### Q8. How does the mobile client determine, server-side, whether the signed-in user is a therapist?

**Real, shipped field — not a TODO.** `GET /api/v2/user/me` (`routes/api_v2.php:288`) returns `UserResource` with a `role` field (`app/Http/Resources/Users/UserResource.php:39`, `"role" => $this->role`).

- `role` is `"Therapist"` (`UserConstants::THERAPIST`, `app/Constants/Account/User/UserConstants.php:12`) once an application is **approved** — set in `TherapistReviewService::approve()` (`app/Services/Therapist/TherapistReviewService.php:61`).
- **v2's `/me` controller also overrides `role` to `"Therapist"` while an application is merely in progress** (draft/submitted/in_review), via `TherapistApplicationService::isApplicant($user)` (`app/Http/Controllers/Api/V2/User/UserController.php:25-45`) — matching the same override used at login/register-therapist.
- **v1's `/me` does NOT get this override** — a client still calling `/api/v1/user/me` sees `role: "User"` for an in-progress applicant, only flipping to `"Therapist"` on real DB approval. If the mobile app is on v1, it will misreport applicant status; switching to v2's `/me` fixes this with no other change needed.
- No separate `is_therapist` boolean or eagerly-loaded `therapist` relation is exposed — `role === "Therapist"` (from v2 `/me`) is the field to check.

### Q9. Exact shapes for booking, availability, join, and earnings

Full field-by-field detail in [CHAT_AND_SESSION_API_REFERENCE.md § Session/booking endpoints](CHAT_AND_SESSION_API_REFERENCE.md#sessionbooking-endpoints). Headline points:

- **Booking**: `POST /api/v2/user/bookings` → `{therapist_id, starts_at, format, notes?}` → response is `SessionBookingService::detail()`'s 17-field shape (`id, uuid, therapist_id, therapist_name, starts_at, join_opens_at, duration_minutes, format, status, coverage, ...`). Status is always `pending_payment` on creation.
- **Availability**: `GET /api/v2/user/therapists/{therapist}/slots?date=Y-m-d` (optional date) → `{date, slots: [{starts_at, ends_at}]}`.
- **Join**: `GET /api/v2/user/bookings/{booking}/join` → `{channel_ref, token, starts_at, duration_minutes}`. **No separate `uid`, `app_id`, or `expires_at` field is returned** — `uid` is implicitly the caller's own user id, the Agora App ID must be baked into client config, and token expiry is opaque (encoded in the token itself, not surfaced). Agora token TTL is 4 hours (see lifecycle Q5 below).
- **Earnings**: `GET /api/v2/therapist/earnings/dashboard` (balance, currency, weekly/monthly/all-time totals, 7-day chart, tiles, recent payouts) and `GET /api/v2/therapist/earnings/transactions` (paginated `{id, type, amount, status, reference, created_at}` rows). Both 403 if the caller has no `therapist` record, and separately 403 (with an explanatory message) if the therapist is business-employed — org-covered therapists don't earn TalkAM balance.

### Q10. End-to-end therapist application/verification API contract

Full step-by-step table in [CHAT_AND_SESSION_API_REFERENCE.md § Therapist application flow](CHAT_AND_SESSION_API_REFERENCE.md#therapist-application-flow). Headline points:

- Starts at `POST /api/v2/auth/register-therapist` — creates the user and a `draft` application row (`TherapistApplicationService::draftFor()`), returns `{token, user}`.
- Wizard steps (each gated by `email.verified` middleware — OTP must be confirmed first): `personal`, `documents` (upload/delete), `specialties`, `availability`, `payout`, then `submit`.
- `GET /api/v2/therapist/application` is the client's status-poll endpoint: `{status, application_id, rejection_reason, submitted_at, steps: {...}}`.
- Status values: `draft`, `submitted`, `in_review`, `approved`, `rejected` (`app/Constants/Therapist/TherapistConstants.php:8-17`).
- `submit()` re-validates every step server-side and throws per-step validation errors if anything's incomplete — the client doesn't need to independently track step completeness, it can just call submit and surface whatever error comes back.
- Admin approval (`POST /api/v2/platform-admin/therapist-verification/{id}/approve`) creates/updates the real `Therapist` row, flips `users.role` to `"Therapist"`, sends `TherapistApplicationApprovedNotification`. Rejection requires a `reason` field, sets `rejection_reason` the client can display.

### Q11. Does the profile-view endpoint re-apply anonymization?

**NOT safe.** Every profile-view path a therapist could plausibly use by numeric `user_id` returns the real identity unconditionally — anonymization is a display convention computed in exactly two narrow call sites, not enforced at the data-access layer.

Confirmed leak paths:
1. **The conversation list itself already returns the real id/name.** `ConversationStateService::serialize()`'s `other_member` block (`app/Services/Messaging/V2/ConversationStateService.php:138-148`) returns `id` (real numeric user_id) and `name` (real full name) **unconditionally**, alongside a `same_organization` flag that is only advisory — the server has already sent the real identity in the same payload before the client gets to decide whether to show it. This directly contradicts the flow doc's claim that "the therapist-facing app never learns a client's real user_id" — it does, right there in the conversation list response.
2. **Therapist Clients endpoints** (`GET /api/v2/therapist/clients` and `/{user}`, `app/Services/Therapist/TherapistClientService.php`) return real name/profile/treatment data with **zero** `CoverageResolver`/anonymization check — the only gate is "has this therapist had a confirmed/completed session with this user," which is exactly the population anonymized-chat clients belong to.
3. **`GET /api/v1/user/profile/fetch?user_id={id}`** (`app/Http/Controllers/Api/V1/User/UserController.php:159-177`) is a bare `User::where('id', $id)->first()` lookup with **no ownership or anonymization check of any kind** — full profile (name, email, phone, bio) for any user id. v2 has no equivalent fetch-by-id route, but v1 stays mounted and reachable.

**Practical implication**: if the mobile "View Profile" action navigates using the raw numeric `other_member.id` it already received from the conversation payload, to either `GET /api/v1/user/profile/fetch?user_id=` or `GET /api/v2/therapist/clients/{user}`, it will show the real name/photo regardless of anonymization state. The two places that *do* correctly gate on `CoverageResolver::shareOrganization()`/coverage are `TherapistDashboardService::clientRef()` (dashboard card display string only) and the `same_organization` flag itself — neither is a data-access gate. This needs a real fix: either strip `other_member.id`/`name` server-side when a conversation is anonymized, or make the profile-view endpoints themselves consult the same anonymization signal before returning real identity.

---

## Session/booking lifecycle

### 1. Does a therapist "end session now" action exist?

**No — only per-side `leave()` exists.** Searched for `end()`/`endSession()`/`forceEnd()` and any `bookings/{id}/end`-style route — zero hits. `SessionLifecycleService::leave()` (`app/Services/Therapist/SessionLifecycleService.php:235-247`) only stamps `{role}_left_at` and calls `completeIfBothLeft()`, which requires **both** sides to have joined and left before the scheduled end time. Completion otherwise only happens via `sweep()` (cron) or the `handleRoomClosed()` webhook (also requires both sides to have joined at some point).

**Where a new `POST /api/v2/user/bookings/{booking}/end` would hook in:**
- Route: add to the existing `Route::prefix("bookings")` group, `routes/api_v2.php:478-493`.
- Controller: mirror `SessionController::leave()` (`app/Http/Controllers/Api/V2/Therapist/SessionController.php:117-131`) — same try/catch → `ApiHelper::problemResponse` pattern.
- Service: new `SessionLifecycleService::end(User $user, $booking_id)` — resolve via `getForParticipant()`, assert caller is the therapist (`InvalidRequestException` otherwise), assert status is `in_progress` (return current state if already `completed`, for idempotency), then do what `completeIfBothLeft()`/`sweep()` already do: `$session->update(['status' => COMPLETED, 'ended_at' => now()])`, `EarningsLedgerService::creditForSession()`, send the same follow-up notification.

### 2. Does anything broadcast a session-ended event today?

**No.** `routes/channels.php` registers exactly four channels (`App.Models.User.{id}`, `presence-user.{id}`, `conversation.{conversationId}`, `refresh-notification.{userId}`) — no `booking.{id}`/`session.{id}` pattern. `app/Events/` contains only messaging events — nothing named `SessionCompleted`/`BookingEnded`/etc. Completion today is silent: `completeIfBothLeft()`, `sweep()`, and `handleRoomClosed()` all just `$session->update([...])` with no event fired.

**To add one**: a new `App\Events\Therapist\SessionStatusChanged` implementing `ShouldBroadcast`, a new `booking.{id}` channel in `routes/channels.php` gated the same way `getForParticipant()` checks ownership (client or therapist of that session), and a `broadcast(new SessionStatusChanged($session))` call added at each of the three completion sites (and the new `end()` method from Q1).

### 3. Sweep cron interval

**Every minute.** `app/Console/Kernel.php:59` — `$schedule->command('bookings:sweep-session-completions')->everyMinute();`. So the ceiling on a stuck call (both sides drop without leave/end) is ~1 minute past `starts_at + duration_minutes`, not longer.

### 4. Machine-readable `join()` rejection codes

**There are none — only free-text messages, all wrapped in the same generic HTTP 400.** Every rejection in `join()` throws a plain `InvalidRequestException($message)` with no error code; `ApiHelper::problemResponse()` always returns `code: 400, error_code: 0/null` — only the `message` string differs. Full table of conditions → messages:

| Condition | Message |
|---|---|
| `pending_payment` (consumer coverage) | "Payment for this session hasn't been completed yet." |
| `pending_payment` (non-consumer coverage) | "Your therapist hasn't accepted this session yet." |
| `completed` | "This session has already ended." |
| `cancelled` | "This session was cancelled." |
| `failed` | "This session couldn't be set up — please book a new one." |
| `expired` | "This session's payment window expired before it was confirmed." |
| `no_show` | "This session was marked as a no-show." |
| unmapped status | "This session cannot be joined." |
| before join window | "This session starts{when} at {time} — you can join from {window_opens}." |
| after `starts_at + duration_minutes` | "This session's scheduled time has ended." |
| not a participant | 404 `ModelNotFoundException("Booking not found")` |

Only `confirmed`/`in_progress` are joinable. **If the client needs to route on a specific reason, it currently has to string-match `message`** — worth raising as a follow-up: adding a `reason` enum/code field alongside `message` in these responses.

### 5. Agora token TTL, and is re-calling join() mid-session safe?

**TTL = 4 hours** (`SessionCallService.php:19`, `TOKEN_TTL_SECONDS = 4 * 60 * 60`), applied to both the token-expire and privilege-expire args. Since typical sessions are far under 4 hours, tokens are not the cause of any production drop-offs.

**Re-calling join() mid-session to refresh the token is safe.** Every timestamp write in `join()` is `if (empty(...))`-guarded — `started_at` and `{role}_joined_at` are never re-stamped on a second call. The one real side effect per call: `{role}_left_at` is unconditionally reset to `null` (intentional "rejoin" semantics — a documented behavior, not a bug). A fresh token is minted every call. Window checks (`join_early`, past-due) are still re-evaluated every call, so a stale re-call past the session's end window will correctly reject rather than silently succeed.

### 6. Are per-participant `joined_at`/`left_at` timestamps exposed on the booking response?

**No.** The model casts them (`client_joined_at`, `therapist_joined_at`, `client_left_at`, `therapist_left_at`, `started_at`, `ended_at` — `app/Models/TherapySession.php:16-24`), but the sole booking serializer, `SessionBookingService::detail()` (lines 412-460), does not include any of them — its field list stops at `pending_reschedule` and never touches join/leave timestamps. There's no separate `BookingResource`/`SessionResource` class. These fields are currently internal-only, used by `sweep()`/`completeIfBothLeft()`/`join()`/`leave()` logic. **If the client wants to distinguish "never arrived" from "joined and left" itself, these fields need to be added to `detail()`'s output** — that's a small, additive change (they already exist on the model).

### 7. Is there an `ended_by` field?

**No** — not on the model (`$guarded = []`, no `ended_by` in `$casts`), not in any migration on `therapy_sessions` (checked the base migration and every later add-column migration, including the recent `add_left_at_timestamps` one). There **is** a precedent to follow: `cancelled_by`, a plain string column set to `SessionConstants::CANCELLED_BY_THERAPIST`/`CANCELLED_BY_CLIENT` in `cancel()` — not a foreign key.

**Recommendation (not implemented)**: mirror that pattern — `$table->string('ended_by')->nullable()->after('ended_at');` with values `'client' | 'therapist' | 'system'` (`'system'` for sweep/webhook completions where no one explicitly ended it), set at each of the three completion sites plus the new `end()` method from Q1.
