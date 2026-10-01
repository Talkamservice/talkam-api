# Chat & Session API Reference

Endpoint-by-endpoint reference for the messaging and therapy-session/booking surfaces in `talkam-api`. This is the "how do I actually call this" companion to [CHAT_AND_SESSION_FLOW.md](CHAT_AND_SESSION_FLOW.md) (the narrative service-level doc) and [CHAT_SESSION_QUESTIONS_ANSWERED.md](CHAT_SESSION_QUESTIONS_ANSWERED.md) (specific Q&A with more edge-case detail, including known gaps and safety caveats). Written from the actual routes/controllers/resources/validation as of 2026-09-28 — file:line citations throughout so it can be re-verified as the code changes.

**API versioning**: routes live in `routes/api_v1.php` and `routes/api_v2.php`, both wrapped in their respective `api/v1`/`api/v2` prefixes by `app/Providers/RouteServiceProvider.php`. **Prefer v2 for everything below** — it's the actively maintained surface. Where v1 is still the only (or the more complete) option, that's called out explicitly. All routes below require `auth:sanctum` unless stated otherwise.

---

## Real-time transport

Laravel broadcasting — `log` driver locally, Pusher in real deployments (`config/broadcasting.php`).

### Auth

**Use `POST /broadcasting/auth`** (Laravel's standard `Broadcast::routes()`, `auth:sanctum`-gated, registered in `app/Providers/BroadcastServiceProvider.php:16`). Authenticate with the normal Sanctum bearer token; this evaluates the real per-channel rules below.

⚠️ Do **not** use `POST /api/v1/broadcasting/auth` (`routes/api_v1.php:310`) — it's a legacy endpoint that signs auth for any channel name the client sends with no ownership check (authorization bypass). See [CHAT_SESSION_QUESTIONS_ANSWERED.md § Q0](CHAT_SESSION_QUESTIONS_ANSWERED.md#prerequisite--security-raised-independently) for the full writeup; it needs fixing/removal on the backend regardless of what the client does.

### Channels

Registered in `routes/channels.php` (patterns are bare, no `private-`/`presence-` prefix — Laravel strips the prefix from the subscription name before matching; subscribe with the prefixed name client-side as usual):

| Channel | Access rule | What's actually broadcast here |
|---|---|---|
| `conversation.{conversationId}` | Caller must be a `ConversationMember` of that conversation | All message-content events (see table below) |
| `presence-user.{id}` | Caller is that user, or shares a conversation with them and their `activity_status` is on (presence channel; auth returns `{id}`) | Only `UserPresenceChanged` (online/away/offline) |
| `App.Models.User.{id}` | Caller must be that exact user | **Nothing today** — registered, authorized correctly, but no event currently broadcasts on it |
| `refresh-notification.{userId}` | Caller must be that exact user | `RefreshNotification` |

There is **no** per-booking/per-session channel today (e.g. `booking.{id}`) — session status changes (join/leave/complete) are not broadcast at all; clients must poll. See [CHAT_SESSION_QUESTIONS_ANSWERED.md § lifecycle Q2](CHAT_SESSION_QUESTIONS_ANSWERED.md#2-does-anything-broadcast-a-session-ended-event-today) for what adding one would take.

### Events

| Event | File | Delivery | Channel |
|---|---|---|---|
| `NewMessage` | `app/Events/NewMessage.php` | `ShouldBroadcast` (queued) | `conversation.{id}` |
| `ReceiveMessage` | `app/Events/ReceiveMessage.php` | `ShouldBroadcastNow` (sync, latency-sensitive) | `conversation.{id}` only |
| `MessageDelivered` | `app/Events/Messaging/MessageDelivered.php` | `ShouldBroadcastNow` | `conversation.{id}` only |
| `MessageRead` | `app/Events/Messaging/MessageRead.php` | `ShouldBroadcastNow` | `conversation.{id}` only |
| `MessageEdited` / `MessageDeleted` | `app/Events/Messaging/{MessageEdited,MessageDeleted}.php` | `ShouldBroadcast` | `conversation.{id}` |
| `MessagePinned` / `MessageUnpinned` | `app/Events/Messaging/{MessagePinned,MessageUnpinned}.php` | `ShouldBroadcast` | `conversation.{id}` |
| `MessageReactionAdded` / `Removed` | `app/Events/Messaging/MessageReaction{Added,Removed}.php` | `ShouldBroadcast` | `conversation.{id}` |
| `ConversationSeen` | `app/Events/Messaging/ConversationSeen.php` | `ShouldBroadcast` | `conversation.{id}` |
| `UserTyping` | `app/Events/Messaging/UserTyping.php` | `ShouldBroadcast` | `conversation.{id}` |
| `UserPresenceChanged` | `app/Events/Messaging/UserPresenceChanged.php` | `ShouldBroadcast` | `presence-user.{userId}` (PresenceChannel) |
| `RefreshNotification` | `app/Events/RefreshNotification.php` | `ShouldBroadcast` | `refresh-notification.{userId}` |

**Practical consequence: to get real-time message delivery for ALL of a user's conversations, the client must subscribe to `conversation.{id}` for every conversation it cares about** — there is no single per-user firehose channel today. (Feasible to add — see the Q&A doc — but not implemented.)

`ShouldBroadcastNow` events (`ReceiveMessage`, `MessageDelivered`, `MessageRead`) fire synchronously inside the HTTP request because `QUEUE_CONNECTION=sync` and there's no queue worker — expect their latency to be part of the API response time for `send()`/delivery-ack/read-ack calls.

---

## Messaging endpoints

Base path: `/api/v2/user/messaging/...` (all `auth:sanctum` + `pricingCountry` + `org.active` middleware, `routes/api_v2.php:286-287`).

### Conversations

#### `GET /api/v2/user/messaging/conversations` — list
`routes/api_v2.php:325` → `ConversationController::index` → `ConversationStateService::list()`/`::serialize()` (`app/Services/Messaging/V2/ConversationStateService.php`).

Query params: `archived` (bool-ish), `starred` (bool-ish). Paginated.

Response, one item:
```jsonc
{
  "id": 123,
  "status": "Awaiting_Response",      // or "Active" — see Status values below
  "is_anonymous": false,
  "other_member": {                    // null if the counterpart record is gone
    "id": 45,                          // REAL numeric user_id — always, regardless of anonymization
    "name": "Jane Doe",                // REAL name — always, see safety note below
    "username": "janedoe",
    "avatar": "https://...",
    "same_organization": false         // advisory flag only — client must apply it itself
  },
  "last_message": { "id": 999, "message": "...", "sender_id": 45, "created_at": "..." },
  "unread_count": 3,
  "is_muted": false,
  "archived_at": null,
  "starred_at": null
}
```
⚠️ **`other_member.id`/`name` are the real identity even in an anonymized conversation** — the server does not withhold them, it only adds a `same_organization` flag for the client to decide display. Do not build a "view profile" navigation off `other_member.id` without checking `is_anonymous`/`same_organization` client-side first — and be aware the profile-fetch endpoints themselves don't re-check it either (see Known gaps below).

#### `POST /api/v2/user/messaging/conversations` — create/start
`routes/api_v2.php:326` → `ConversationController::store` → `ConversationService::create()`. **Idempotent** — returns the existing conversation if one already exists between the two users.

Request:
```jsonc
{
  "receiver_id": 45,           // required, exists:users,id
  "message": "hi",             // optional
  "message_type": "text",      // optional
  "asset_url": null,           // optional
  "notification_status": 1,    // optional, 0|1
  "is_anonymous": 0            // optional, 0|1
}
```

Response — `ConversationResource`:
```jsonc
{
  "id": 123,
  "members": [ /* ConversationMemberResource[] */ ],
  "last_message": { /* MessageResource */ },
  "number_of_unread": 0,
  "notification_status": 1,
  "is_anonymous": false,
  "requested_by": { /* UserResource */ },
  "user_is_banned": false,
  "user_blocked": false,
  "i_am_blocked": false,
  "status": "Awaiting_Response"
}
```

#### `POST /user/messaging/conversations/update-status` — accept/decline a conversation request
Available at both `/api/v1/user/messaging/conversations/update-status` and `/api/v2/user/messaging/conversations/update-status` — both route to the same shared `ConversationService::updateStatus()`.

Request:
```jsonc
{ "conversation_id": 123, "status": "Accepted" }   // or "Declined" — exact case, no other values validate
```

#### `GET /user/messaging/conversations/current/fetch`, and the create-conversation v1 path
Backed by the same live `ConversationService` as the v2 endpoints above — not a stale system. Safe to keep using as-is; see [Q&A § Q4](CHAT_SESSION_QUESTIONS_ANSWERED.md#q4-is-there-a-separate-v1-messaging-system) if migrating fully to v2 conventions matters for other reasons.

#### Mute / archive / star / seen
`routes/api_v2.php:334-337` — one route per action:

`POST /api/v2/user/messaging/conversations/{action}` where `{action}` ∈ `mute`, `unmute`, `archive`, `unarchive`, `star`, `unstar`, `seen`.

Request: `{ "conversation_id": 123, "muted_until": "2026-10-01T00:00:00Z" }` (`muted_until` only relevant for `mute`).

Response:
```jsonc
{
  "is_muted": true,
  "muted_until": "2026-10-01T00:00:00Z",
  "archived_at": null,
  "starred_at": null,
  "last_seen_at": "2026-09-28T10:00:00Z"
}
```

### Conversation status values

`app/Constants/General/StatusConstants.php` — exact literals:
- `AWAITING_RESPONSE = "Awaiting_Response"` — default on creation of a cold-start conversation
- `ACTIVE = "Active"` — booking-scoped conversations start here directly (skip the request gate)
- `ACCEPTED = "Accepted"` / `DECLINED = "Declined"` — the only values `update-status` accepts

### Messages

#### `GET /api/v2/user/messaging/messages/list`
`routes/api_v2.php:343` → `MessageController::list`.

Query: `conversation_id` (required, `exists:conversations,id`), `pinned` (optional bool). Paginated.

Response, one item:
```jsonc
{
  "id": 999,
  "conversation_id": 123,
  "sender_id": 45,
  "receiver_id": 1,
  "message": "hello",
  "message_type": "text",
  "file_id": 12,
  "file_url": "https://.../file/<base64-path>",   // already resolved — no second call needed
  "file_name": "note.pdf",
  "voice_duration": null,
  "delivered_at": "2026-09-28T10:00:01Z",
  "read": true,
  "read_at": "2026-09-28T10:01:00Z",
  "edited_at": null,
  "is_pinned": false,
  "is_forwarded": false,
  "replied_to_message_id": null,
  "reply_count": 0,
  "reactions": [ { "user_id": 45, "reaction": "👍" } ],
  "created_at": "2026-09-28T10:00:00Z"
}
```

File attachments (including session notes shared into chat — see below) arrive with `file_url` already populated. There is no separate file-by-id endpoint.

#### `POST /api/v2/user/messaging/messages/send`
`routes/api_v2.php:344` → `MessageController::send` → `MessageActionService::send()`.

Request (multipart if `file` present):
```jsonc
{
  "conversation_id": 123,          // required
  "message": "hello",              // optional, max 1000 chars (config('v2.messaging.max_length'))
  "message_type": "text",          // optional
  "file": "<binary>",              // optional, max 10240 KB (config('v2.messaging.file_max_kb'))
  "voice_duration": 12,            // optional, integer seconds
  "replied_to_message_id": 998     // optional, exists:messages,id
}
```
Response: raw `Message` model attributes (not a resource wrapper) — `id, conversation_id, sender_id, receiver_id, message, message_type, file_id, voice_duration, replied_to_message_id, read, delivered_at, created_at, updated_at, ...`.

Other `MessageActionService` actions available (not detailed field-by-field here, see `app/Services/Messaging/V2/MessageActionService.php`): `sendFile()`, `edit()` (15-min window, config-driven), `delete()` (60-min window), `forward()`, `addReaction()`/`removeReaction()` (16-char max reaction), `setPinned()`, `bulkMarkRead()`.

⚠️ **v1's `/user/messaging/messages/list` and `/send`** (`routes/api_v1.php:196-199`, backed by the separate `App\Services\Messaging\MessageService`) are stale — no file-attachment support, no reactions/edit/pin/forward, no membership check on list, and a dead import (`App\Events\RefreshMessage`, class doesn't exist). Don't build new client behavior against these; migrate off them if currently in use.

---

## Session/booking endpoints

Base path: `/api/v2/user/bookings/...` and `/api/v2/user/therapists/...` unless noted. `auth:sanctum`-gated.

### Book a session
`POST /api/v2/user/bookings` → `SessionBookingService::create()`.

Request:
```jsonc
{
  "therapist_id": 7,               // required, exists:therapists,id
  "starts_at": "2026-10-01T14:00:00Z",  // required, date
  "format": "video",               // required, one of TherapistConstants::SESSION_FORMATS
  "notes": "First session"         // optional, max 1000 chars
}
```

Response — `SessionBookingService::detail()` (also used by the show/list/join-adjacent endpoints):
```jsonc
{
  "id": 501, "uuid": "...", "therapist_id": 7, "therapist_name": "Dr. X",
  "starts_at": "2026-10-01T14:00:00Z", "join_opens_at": "2026-10-01T13:50:00Z",
  "duration_minutes": 50, "format": "video",
  "status": "pending_payment",     // always this on creation — see Status reference below
  "coverage": "consumer",
  "acknowledged_at": null, "amount": 5000, "currency": "NGN",
  "notes": "First session", "has_note": false, "payment_reference": null,
  "rating": null, "client_pre_mood": null, "client_post_mood": null,
  "receipt_url": null, "shared_note": null, "pending_reschedule": null
}
```
Note: `client_joined_at`/`therapist_joined_at`/`client_left_at`/`therapist_left_at`/`started_at`/`ended_at` are tracked on the model but **not** included in this response today (internal-only). No `ended_by` field exists either.

Consumer-path clients then call `POST /api/v2/user/bookings/{booking}/initiate-payment`, response `{reference, amount, currency, link, customer, meta}`.

### Therapist availability
`GET /api/v2/user/therapists/{therapist}/slots?date=2026-10-01` (date optional — omitting it scans 14 days / 200 slots forward).

Response:
```jsonc
{ "date": "2026-10-01", "slots": [ { "starts_at": "2026-10-01T14:00:00Z", "ends_at": "2026-10-01T14:50:00Z" } ] }
```

### Join a session
`GET /api/v2/user/bookings/{booking}/join` → `SessionLifecycleService::join()`. No body needed.

Response:
```jsonc
{
  "channel_ref": "TKSESS-<uuid>",
  "token": "<opaque Agora RTC token>",
  "starts_at": "2026-10-01T14:00:00Z",
  "duration_minutes": 50
}
```
- Agora token TTL: **4 hours** (`SessionCallService::TOKEN_TTL_SECONDS`). Both sides always get `ROLE_PUBLISHER` (no host/audience split).
- No `uid`/`app_id`/`expires_at` field — `uid` is implicitly the caller's own id, App ID must be in client config, expiry is opaque inside the token.
- **Safe to re-call mid-session purely to refresh the token** — all `join()` timestamp writes are `if (empty(...))`-guarded, so `started_at`/`{role}_joined_at` are never re-stamped by a later call. One real side effect: each call unconditionally clears `{role}_left_at` (intentional rejoin semantics).
- Join window: opens `SESSION_JOIN_EARLY_MINUTES` before `starts_at`, closes at `starts_at + duration_minutes`. Only `confirmed`/`in_progress` sessions are joinable — see rejection table below for everything else.
- First join of either side flips status to `in_progress` and stamps `started_at`.

**Rejection reasons** (all HTTP 400 via `InvalidRequestException`, no machine-readable code today — client must match on `message` string):

| Session state | `message` |
|---|---|
| `pending_payment`, consumer coverage | "Payment for this session hasn't been completed yet." |
| `pending_payment`, org-covered | "Your therapist hasn't accepted this session yet." |
| `completed` | "This session has already ended." |
| `cancelled` | "This session was cancelled." |
| `failed` | "This session couldn't be set up — please book a new one." |
| `expired` | "This session's payment window expired before it was confirmed." |
| `no_show` | "This session was marked as a no-show." |
| before join window | "This session starts{when} at {time} — you can join from {window_opens}." |
| after `starts_at + duration_minutes` | "This session's scheduled time has ended." |
| not a participant | 404 `"Booking not found"` |

### Leave a session
`SessionLifecycleService::leave()` (route mirrors `join()`'s pattern in `SessionController`). Stamps `{role}_left_at`; triggers immediate completion only if **both** sides have joined and left, and the scheduled end hasn't passed yet (`completeIfBothLeft()`). Otherwise the session is finalized later by the sweep.

⚠️ **There is no therapist-authoritative "end this session for both parties right now" action today** — only the per-side `leave()` described above. See [Q&A § lifecycle Q1](CHAT_SESSION_QUESTIONS_ANSWERED.md#1-does-a-therapist-end-session-now-action-exist) for exactly where a `POST .../end` endpoint would need to hook in if added.

### Automatic completion (sweep)
`bookings:sweep-session-completions` runs **every minute** (`app/Console/Kernel.php:59`). Catches every `confirmed`/`in_progress` session whose `starts_at + duration_minutes` is in the past:
- Client never joined → `no_show`, bundle draw refunded if org-covered.
- Therapist never joined while client did → `no_show` + refund.
- Both joined → `completed`.

Also a webhook path, `handleRoomClosed()` — if the AV room closes and both sides had joined at some point, completes immediately; a solo join-then-leave is deliberately left for the sweep to resolve as a no-show.

**No event is broadcast when any of this happens** — clients must poll the booking/session detail endpoint to observe a status change; there's no `booking.{id}` channel today.

### Status reference

| Status | Meaning |
|---|---|
| `pending_payment` | Booked, awaiting therapist acceptance (org-covered) or client payment (consumer) |
| `confirmed` | Both sides cleared to join once the window opens |
| `in_progress` | At least one side has joined |
| `completed` | Both sides attended (or early-completed) |
| `cancelled` | Cancelled before it happened |
| `no_show` | Past scheduled end with one or both sides never joining |
| `failed` | Consumer payment failed |
| `expired` | Consumer payment hold ran out unpaid |

### Earnings (therapist)

`GET /api/v2/therapist/earnings/dashboard`:
```jsonc
{
  "balance": 42000, "currency": "NGN",
  "totals": { "this_week": 5000, "this_month": 20000, "all_time": 42000 },
  "chart": [ { "date": "2026-09-22", "amount": 1000 }, /* 7 days */ ],
  "tiles": { "sessions_this_week": 3, "avg_per_session": 5000, "pending_payout": 0, "pending_settlement": 0 },
  "recent_payouts": [ { "id": 1, "date": "...", "amount": 10000, "status": "paid" } ]
}
```
`GET /api/v2/therapist/earnings/transactions` (paginated): `{ id, type, amount, status, reference, created_at }` rows.

Both 403 if the caller has no `therapist` record, and separately 403 with an explanatory message if the therapist is business-employed (org pays them directly — no TalkAM balance applies).

### Therapist application flow

| Step | Route | Notes |
|---|---|---|
| Start | `POST /api/v2/auth/register-therapist` | Creates user + `draft` application row. Response: `{token, user}`. |
| Status/read | `GET /api/v2/therapist/application` | `{status, application_id, rejection_reason, submitted_at, steps: {personal, documents, specialties, availability, payout}}` — the client's poll endpoint. |
| Credential types | `GET /api/v2/therapist/application/credential-types` | Returns config-driven list. |
| Personal | `POST /api/v2/therapist/application/personal` | `{credential_type, years_experience}` |
| Documents | `POST /api/v2/therapist/application/documents` | `{type, file, expires_at?}` — response `{id, type, status, expires_at}`, re-upload replaces same type |
| Delete document | `DELETE /api/v2/therapist/application/documents/{id}` | — |
| Specialties | `POST /api/v2/therapist/application/specialties` | `{bio, specialties: [category_id, ...]}` |
| Availability | `POST /api/v2/therapist/application/availability` | `{session_duration, buffer_minutes, days: [{day_of_week, start_time, end_time, active}]}` |
| Payout | `POST /api/v2/therapist/application/payout` | (not detailed further here) |
| Submit | `POST /api/v2/therapist/application/submit` | Server re-validates every step; per-step error if incomplete. On success: `{status: "submitted", submitted_at}`. |

Document/personal/specialties/availability writes require `email.verified` middleware (OTP confirmed first).

Status values: `draft`, `submitted`, `in_review`, `approved`, `rejected`.

**Admin review** (`platform-admin/therapist-verification/*`):
- `GET .../` (list), `GET .../{id}` (show), `POST .../{id}/start-review`
- `POST .../{id}/approve` — creates/updates the real `Therapist` row, flips `users.role` to `"Therapist"`, sends `TherapistApplicationApprovedNotification`. Response: refreshed `TherapistApplication`.
- `POST .../{id}/reject` — requires `{reason}`, sets `rejection_reason`. Response: refreshed `TherapistApplication`.
- `POST .../documents/{documentId}/verdict` — `{status: approved|rejected, reason (required if rejected)}`.

### Is the signed-in user a therapist?

`GET /api/v2/user/me` → `role` field, `"Therapist"` once approved **or** while an application is in progress (v2-only override — `/api/v1/user/me` doesn't get the in-progress override and will show `"User"` for applicants). Prefer v2's `/me` for this check.

---

## Where the two systems connect

### Booking-scoped chat
`SessionLifecycleService::startConversation($user, $bookingId)`:
1. Resolves the other participant server-side from the session row (not client-supplied).
2. Opens/reuses the conversation via `ConversationService::create()`.
3. Force-activates it (`status = Active`) — skips the `Awaiting_Response` cold-start gate, since a confirmed/in-progress/completed booking is itself proof these two are allowed to talk.

### Session notes → chat
When a therapist flips a session note to shared (`false → true` transition only):
1. Opens/reuses the same booking-scoped conversation.
2. Formats as `"{title}\n\n{content}\n\n— Shared by {therapist} on {date}"`.
3. Sends as a real **file message** via `MessageActionService::sendFile()` — arrives in `GET /api/v2/user/messaging/messages/list` with `file_url` already resolved (see Messages § above) — client can render it as a normal attachment, no special "note" message type or extra endpoint needed.
4. Best-effort — wrapped in try/catch, logged on failure, never blocks the note save.

### Identity & anonymization

`SessionNotificationSupport::anonRef($userId)` → `"Anonymous · #" + (4000 + userId % 6000)`, a stable deterministic pseudonym. Real-name reveal is allowed when the org's `coverage_enabled` flag permits it for that session, or `CoverageResolver::shareOrganization()` says both users are active members of the same org.

⚠️ **This is a display convention only, not a data-access control.** `ConversationStateService::serialize()`'s `other_member` block and the therapist Clients endpoints (`GET /api/v2/therapist/clients[/{user}]`) both return real `id`/`name` regardless of anonymization state — they only expose a `same_organization` flag for the client to act on. `GET /api/v1/user/profile/fetch?user_id=` has no anonymization check whatsoever. **A client should never build a "view real profile" navigation off a numeric user id sourced from an anonymized conversation** until this is fixed server-side. Full detail: [CHAT_SESSION_QUESTIONS_ANSWERED.md § Q11](CHAT_SESSION_QUESTIONS_ANSWERED.md#q11-does-the-profile-view-endpoint-re-apply-anonymization).

---

## Known gaps / follow-ups surfaced by this investigation

1. `POST /api/v1/broadcasting/auth` is a live authorization bypass — any authenticated user can get a signed subscription to any private channel. Fix or remove (routes/api_v1.php:310).
2. No per-user realtime channel is populated (`App.Models.User.{id}` is registered but unused) — clients must subscribe per-conversation.
3. No `booking.{id}`/session-status broadcast channel exists — session state changes require polling.
4. `join()` rejections have no machine-readable code — client must string-match `message`.
5. No therapist "end session now" action — only mutual/timed completion.
6. `client_joined_at`/`therapist_joined_at`/etc. aren't exposed on the booking response.
7. No `ended_by` field.
8. Anonymization is not enforced at the data-access layer for profile-view endpoints (`other_member`, Clients endpoints, v1 profile fetch) — real identity leaks through regardless of chat anonymization state.
9. v1's messaging `MessageService` (send/list) is stale/unmaintained (missing attachments, reactions, edit/pin/forward; has a dead class import) — migrate any client usage to v2's `MessageActionService`-backed endpoints.
