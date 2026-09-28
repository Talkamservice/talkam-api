# Local dev test accounts

Created via tinker for local testing only — not real users. Org id 19
("Acme QA Test Org", domain acme-qa-test.local).

| Role                          | Email                          | Username        | Password       |
|-------------------------------|---------------------------------|-----------------|----------------|
| Business (org admin, user 91) | test.business@talkam.local      | test_business   | DevTest#2026   |
| Employee (user 92)            | test.employee@talkam.local      | test_employee   | DevTest#2026   |
| Therapist (user 93, therapist id 18) | test.therapist@talkam.local | test_therapist  | DevTest#2026   |
| Therapist 2 (therapist id 19) | test.therapist2@talkam.local    | test_therapist2 | DevTest#2026   |
| Therapist 3 (therapist id 20) | test.therapist3@talkam.local    | test_therapist3 | DevTest#2026   |
| Therapist 4 (therapist id 21) | test.therapist4@talkam.local    | test_therapist4 | DevTest#2026   |
| Therapist 5 (therapist id 22) | test.therapist5@talkam.local    | test_therapist5 | DevTest#2026   |
| Therapist 6 (therapist id 23) | test.therapist6@talkam.local    | test_therapist6 | DevTest#2026   |
| Therapist 7 (therapist id 24) | test.therapist7@talkam.local    | test_therapist7 | DevTest#2026   |
| Therapist 8 (therapist id 25) | test.therapist8@talkam.local    | test_therapist8 | DevTest#2026   |
| Therapist 9 (therapist id 26) | test.therapist9@talkam.local    | test_therapist9 | DevTest#2026   |
| Therapist 10 (therapist id 27) | test.therapist10@talkam.local  | test_therapist10 | DevTest#2026  |
| Therapist 11 (therapist id 28) | test.therapist11@talkam.local  | test_therapist11 | DevTest#2026  |
| Therapist 12 (therapist id 29) | test.therapist12@talkam.local  | test_therapist12 | DevTest#2026  |
| Therapist 13 (therapist id 30) | test.therapist13@talkam.local  | test_therapist13 | DevTest#2026  |
| Therapist 14 (therapist id 31) | test.therapist14@talkam.local  | test_therapist14 | DevTest#2026  |
| Therapist 15 (therapist id 32) | test.therapist15@talkam.local  | test_therapist15 | DevTest#2026  |
| Therapist 16 (therapist id 33) | test.therapist16@talkam.local  | test_therapist16 | DevTest#2026  |

All 16 therapists are seat-holding "own" members of org 19 (role=therapist
on organization_members), so they show up under the business dashboard's
Therapists tab (`OrgRosterService::therapists()`), each with a randomized
credential_type (Clinical Psychologist, LMFT, LPC, etc.) via TherapistFactory.
None have an approved TherapistApplication, so their "specialty" field will
show blank in the UI — fine for roster/list testing, not for specialty-filter
testing.

All 16 also have recurring availability (TherapistAvailability, active=true),
Mon–Fri, continuous 20-minute blocks from 09:00 through 17:00 (24 slots/day:
09:00-09:20, 09:20-09:40, … 16:40-17:00). Each therapist's session_duration=20
and buffer_minutes=0 to match (slot length/spacing is driven by those fields,
not window width), so TherapistSlotService resolves real bookable slots and
they're actually bookable end-to-end.

Regenerate anytime with the tinker script used to create these
(User::factory + OrganizationMember::factory + Therapist::factory),
or delete these rows with:

```
DELETE FROM users WHERE email LIKE 'test.%@talkam.local';
```
(cascades will need to clean up organizations/organization_members/therapists rows for org id 19 as well).
