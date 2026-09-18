# Session Flow — Mobile Integration Guide

Everything the mobile app needs to integrate therapy-session booking through
completion. All endpoints below are **role-agnostic** — the backend resolves
whether the caller is the client or the therapist from the bearer token, so
the exact same routes are shared by the mobile consumer app and the TalkAM
for Business web dashboards. Nothing here is web-only.

Import [`TalkAM-v2.postman_collection.json`](./TalkAM-v2.postman_collection.json)
into Postman and set the collection variables `BASE_URL` and `TOKEN` (a
Sanctum bearer token from `01 Authentication > Login`). The relevant folders
are **`07 Therapist Booking`**, **`07b Session Requests (No-Slot Leads)`**,
**`08 Sessions`**, and the acknowledge/decline items in **`10 Schedule &
Notifications`**.

## 1. Two ways into a session

There are two entry points into the exact same lifecycle:

- **Direct booking** — the client already sees a bookable slot (`Slots`
  endpoint) and books it directly. Used when the therapist has real
  availability.
- **No-slot lead** (`07b`) — the client wants a specific therapist but no
  listed slot works for them. They submit a preferred day/time as a lead;
  the therapist reviews it and *proposes* a real slot, which is what
  actually creates the `TherapySession`. This mirrors the reschedule
  request/respond shape, just before any session exists yet.

Both paths converge on the same `TherapySession` once a real slot is
committed — same status machine, same coverage rules, same everything below.

## 2. Coverage — who's actually paying

Every session resolves to a `coverage` value that changes what happens next.
This is feature-flagged (`business.coverage_enabled`) — with it off, every
session is always `consumer`.

| `coverage` | Who pays | What happens after booking |
|---|---|---|
| `consumer` | The client, via Flutterwave | Must call **Initiate Payment**, then a successful **Payment Callback** confirms the session. A `hold_expires_at` timer (15 min by default) releases the slot if payment never lands. |
| `org_bundle` | Employer's prepaid bundle | Nothing to pay — a bundle session is drawn immediately. Therapist's **Acknowledge** confirms it. |
| `org_meter` | Employer, billed monthly by usage | Same as above — nothing to pay upfront. |
| `org_external` | Settled outside TalkAM entirely | Same as above — nothing to pay upfront. |
| `blocked` | — | Booking rejected outright (422) with a `blocked_reason`. |

**The important part for mobile:** regardless of coverage, every new session
is created with `status: "pending_payment"` and `hold_expires_at: null` for
org-covered sessions (no expiry — it waits indefinitely for the therapist).
Read the `coverage` field on the booking response to decide what UI to show
next:

- `coverage === "consumer"` → show a **Pay** button (Initiate Payment).
- anything else → show a **"Waiting for your therapist to confirm"** state.
  No payment UI at all.

## 3. Status state machine

```
pending_payment ──(consumer: pay)──────────────► confirmed
pending_payment ──(org-covered: therapist acknowledges/proposes)──► confirmed
pending_payment ──(hold expires, consumer only)──► expired
pending_payment ──(cancel)───────────────────────► cancelled
pending_payment ──(booking/payment fails)────────► failed

confirmed ──(join, first participant)────────────► in_progress
confirmed ──(cancel)──────────────────────────────► cancelled
confirmed ──(passes end time, client never joined)► no_show (no refund)
confirmed ──(passes end time, therapist never joined)► no_show (full refund)

in_progress ──(call room closes / sweep)──────────► completed
```

`acknowledged_at` is a separate timestamp from `status` — it's stamped the
first time the therapist acts on a direct booking (Accept/Decline), and it's
what the **Requests** tab uses to know a booking has been "dealt with." For
an org-covered session, acknowledging *also* flips status to `confirmed`
(nothing left to pay). For a consumer session it does not — the client still
has to pay.

## 4. Endpoint reference

All under `{{BASE_URL}}/api/v2`, all requiring `Authorization: Bearer
{{TOKEN}}` unless noted. Timestamps are `Y-m-d H:i:s` in **Africa/Lagos
(WAT, UTC+1)** — no timezone suffix in the string, so don't assume UTC.

| Step | Method | Path | Postman folder |
|---|---|---|---|
| Browse therapists | GET | `/user/therapists` | 07 |
| Therapist profile | GET | `/user/therapists/{id}` | 07 |
| Available slots | GET | `/user/therapists/{id}/slots` | 07 |
| **Direct booking** | POST | `/user/bookings` | 07 |
| Submit a no-slot lead | POST | `/user/session-requests` | 07b |
| My leads | GET | `/user/session-requests` | 07b |
| Decline a proposed lead time | POST | `/user/session-requests/{id}/decline` | 07b |
| Therapist's lead queue | GET | `/therapist/session-requests` | 07b |
| Therapist proposes a real slot | POST | `/therapist/session-requests/{id}/propose` | 07b |
| Therapist declines a lead | POST | `/therapist/session-requests/{id}/decline` | 07b |
| Therapist's request sheet (a direct booking, pre-acknowledge) | GET | `/therapist/sessions/{id}/request` | 10 |
| Therapist acknowledges (accept) | POST | `/therapist/sessions/{id}/acknowledge` | 10 |
| Therapist declines a direct booking | POST | `/therapist/sessions/{id}/decline` | 10 |
| Initiate payment (consumer only) | POST | `/user/bookings/{id}/initiate-payment` | 07 |
| Payment callback | POST | `/finance/payments/callback` | 07 |
| My sessions (upcoming + past) | GET | `/user/bookings` | 07 |
| Therapist's sessions | GET | `/therapist/sessions` | — |
| Booking detail | GET | `/user/bookings/{id}` | 07 |
| Request reschedule | POST | `/user/bookings/{id}/reschedule` | 08 |
| Respond to reschedule | POST | `/user/reschedules/{id}/respond` | 08 |
| Cancel | POST | `/user/bookings/{id}/cancel` | 08 |
| Join (get AV token) | GET | `/user/bookings/{id}/join` | 08 |
| Pre/post session mood | POST | `/user/bookings/{id}/session-mood` | — |
| Session notes (therapist only) | GET/POST | `/therapist/sessions/{id}/notes` | 11 |
| Receipt (paid bookings) | GET | `/user/bookings/{id}/receipt` | 08 |
| Review a completed session | POST | `/user/bookings/{id}/review` | 07 |
| Therapist reviews (public) | GET | `/user/therapists/{id}/reviews` | 07 |

## 5. Walkthrough: consumer booking (self-pay)

**1. Book a slot**

```
POST /user/bookings
{
  "therapist_id": 6,
  "starts_at": "2026-08-14 09:00:00",
  "format": "video",
  "notes": "First session, a bit nervous"
}
```

```json
{
  "message": "Booking created successfully",
  "success": true,
  "data": {
    "id": 101,
    "therapist_id": 6,
    "therapist_name": "Danny Doe",
    "starts_at": "2026-08-14 09:00:00",
    "duration_minutes": 45,
    "format": "video",
    "status": "pending_payment",
    "coverage": "consumer",
    "acknowledged_at": null,
    "amount": 15000,
    "currency": "NGN",
    "pending_reschedule": null
  }
}
```

`coverage: "consumer"` → show the Pay button.

**2. Initiate payment**

```
POST /user/bookings/101/initiate-payment
```
Returns a Flutterwave checkout payload — hand it to the SDK/WebView as
usual. On success, Flutterwave redirects to your callback, which you POST to
`/finance/payments/callback` with the `reference`. That call verifies the
transaction and flips the session to `confirmed`.

**3. Poll or refetch** `GET /user/bookings/101` until `status === "confirmed"`.

## 6. Walkthrough: org-covered booking (no payment)

Same `POST /user/bookings` call, but the response for an org-employed client
looks like:

```json
{
  "data": {
    "id": 102,
    "status": "pending_payment",
    "coverage": "org_bundle",
    "acknowledged_at": null
  }
}
```

There is **no payment step**. Show "Waiting for [therapist] to confirm."
The therapist calls `POST /therapist/sessions/102/acknowledge`; the response
includes the new status:

```json
{
  "data": { "acknowledged_at": "2026-08-13 10:02:11", "status": "confirmed" }
}
```

Poll/refetch on the client side to pick that up (or handle the push
notification — see §8).

## 7. Walkthrough: no-slot lead

```
POST /user/session-requests
{
  "therapist_id": 6,
  "format": "video",
  "preferred_at": "2026-08-14 09:00:00",
  "note": "Prefer mornings, flexible on exact time"
}
```
```json
{ "data": { "id": 9, "status": "pending" } }
```

Therapist reviews `GET /therapist/session-requests`, then either:

- **Proposes a real slot** — `POST /therapist/session-requests/9/propose`
  `{ "starts_at": "2026-08-14 09:00:00" }`. This *creates* the actual
  `TherapySession` (through the normal booking path — same coverage rules
  apply) and links it to the request. If org-covered, it auto-confirms
  immediately (no extra acknowledge step). Response includes `session_id`.
- **Declines outright** — `POST /therapist/session-requests/9/decline`. No
  session is ever created.

The client checks `GET /user/session-requests` — once `status === "proposed"`,
`session_id` and `session_status` tell you what to render (pay button if
`session_status === "pending_payment"` and `coverage === "consumer"`,
otherwise "waiting to be confirmed"). The client can also
`POST /user/session-requests/9/decline` to turn down the proposed time
(cancels the underlying session).

## 8. Reschedule — request/respond, not instant

Rescheduling is a **proposal**, not a direct edit. Only `confirmed` sessions
can be rescheduled.

```
POST /user/bookings/101/reschedule
{
  "new_starts_at": "2026-08-15 10:00:00",
  "reason": "client_request"
}
```

> ⚠️ **The field is `new_starts_at`, not `starts_at`.** This exact mismatch
> was a live bug in the web client until this session's audit — worth
> calling out because it's an easy copy-paste mistake from the booking
> endpoint, which *does* use `starts_at`.

`reason` must be one of `personal_emergency | technical_issues |
client_request`. Limits: max **2** reschedule requests per session
(`SESSION_RESCHEDULE_MAX`, counts `pending` + `declined`, not `accepted`),
and a **6-hour** cutoff before the session start (`SESSION_RESCHEDULE_CUTOFF_HOURS`)
— both config-driven per environment and not exposed via any endpoint, so
just handle the 422 (`"This session has reached the maximum of 2 reschedule
requests."` / `"Sessions can no longer be rescheduled within 6 hours of the
start time."`) rather than hardcoding the numbers client-side.

```json
{ "data": { "id": 3, "status": "pending", "new_starts_at": "2026-08-15 10:00:00" } }
```

The counterpart sees the pending proposal in **every session-list/detail
response** as `pending_reschedule`:

```json
"pending_reschedule": {
  "id": 3,
  "new_starts_at": "2026-08-15 10:00:00",
  "reason": "client_request",
  "requested_by": 50
}
```

Compare `requested_by` to the logged-in user's own id — if it matches, show
a "waiting on them" state; otherwise show Accept/Decline. Then:

```
POST /user/reschedules/3/respond
{ "action": "accept" }   // or "decline"
```

Accept moves `session.starts_at` to `new_starts_at` at the **same price** —
never a payment delta. Decline just closes out the request; the session is
untouched. Either way `pending_reschedule` clears on the next fetch — **make
sure your app actually refetches the session/list after responding**; don't
just optimistically hide the banner, since a stale local update was exactly
the bug found in the web client this session.

## 9. Cancel

```
POST /user/bookings/101/cancel
{ "reason": "Personal emergency" }
```

Refund policy: client cancelling ≥24h before start (`SESSION_FREE_CANCELLATION_HOURS`)
→ full refund; inside 24h → no refund; therapist-initiated → always
refunds. Only `pending_payment` and `confirmed` sessions can be cancelled —
everything else 422s with a specific reason (`"This session has already
ended."`, `"This session was already marked as a no-show."`, etc. — surface
the `message` field directly, it's already precise).

## 10. Join

```
GET /user/bookings/101/join
```
```json
{ "data": { "channel_ref": "TKSESS-ABC123", "token": "...", "starts_at": "2026-08-14 09:00:00", "duration_minutes": 45 } }
```

Gated by status and time:

- Wrong status → a specific message per state, e.g.
  `"Payment for this session hasn't been completed yet."` (consumer,
  pending_payment) vs `"Your therapist hasn't accepted this session yet."`
  (org-covered, pending_payment) vs `"This session was cancelled."`, etc.
- Too early → **`"This session starts at 8:00am — you can join from 7:45am."`**
  if today, **`"This session starts tomorrow at 8:00am — you can join from
  7:45am."`** if tomorrow, or **`"This session starts in 3 days at 8:00am —
  you can join from 7:45am."`** otherwise. `join_early_minutes` defaults to
  **0** (join exactly at start time) — configurable per environment, so
  don't hardcode the 15-minute offset shown in these examples.

Show the `message` field from the error response directly — it's built to
be user-facing as-is, no need to reconstruct it client-side.

First participant to call `join` on a `confirmed` session stamps
`started_at` and flips status to `in_progress`.

## 11. Completion, notes, review

- The call room signals completion via an AV webhook (`room_closed`) which
  stamps `ended_at` and flips status to `completed`; a background sweep also
  catches stragglers past their end time (no_show handling included).
- Session notes are therapist-only to write (`POST
  /therapist/sessions/{id}/notes`, `shared_with_client: true|false`). A
  shared note appears on the client's booking detail as `shared_note`.
- `POST /user/bookings/{id}/review` — completed sessions only, one review
  per session, always shown anonymised to other users.

## 12. Error handling

Every error response has the same shape:

```json
{ "message": "...", "code": 422, "success": false, "error_code": 0, "errors": { "field": ["..."] } }
```

Validation errors (`code: 422`) carry field-level messages in `errors`;
everything else is a single human-readable `message` — display it directly,
these are already written for end users (see the join/cancel examples
above — they're deliberately specific, not generic "something went wrong"
text).
