<?php

namespace App\Services\Business;

use App\Constants\Account\User\ConsentConstants;
use App\Constants\Account\User\UserConstants;
use App\Constants\Auth\PinConstants;
use App\Constants\Business\OrganizationConstants;
use App\Exceptions\Auth\PinException;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\MethodsHelper;
use App\Models\EmployeeSelfCheck;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Auth\PinService;
use App\Services\Auth\V2\VerifyService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Company account lifecycle: signup, domain confirmation, seats, plan, bench.
 *
 * Domain confirmation deliberately rides the existing PIN pipeline
 * (TYPE_VERIFY_EMAIL at 6 digits via Auth\V2\VerifyService) rather than a new
 * codes table — it is already exactly "a 6-digit code with a short expiry
 * emailed to the work address".
 */
class OrganizationService
{
    public VerifyService $verify_service;
    public UserService $user_service;

    public function __construct()
    {
        $this->verify_service = new VerifyService;
        $this->user_service = new UserService;
    }

    /* ── Signup ─────────────────────────────────────────────────────────── */

    public function register(array $data): array
    {
        $data = $this->validateRegistration($data);

        $domain = self::domainOf($data["work_email"]);

        DB::beginTransaction();
        try {
            $organization = Organization::create([
                "name" => $data["company_name"],
                "slug" => self::generateSlug($data["company_name"]),
                "domain" => $domain,
                "industry" => $data["industry"] ?? null,
                "headcount_band" => $data["headcount_band"] ?? null,
                "hr_contact_email" => $data["work_email"],
                "status" => OrganizationConstants::STATUS_PENDING_VERIFICATION,
            ]);

            $user = $this->user_service->create([
                "email" => $data["work_email"],
                "username" => self::generateUsername($data["work_email"]),
                "password" => $data["password"],
                "role" => UserConstants::USER,
                "first_name" => $data["first_name"] ?? null,
                "last_name" => $data["last_name"] ?? null,
            ]);

            $organization->update(["created_by" => $user->id]);

            OrganizationMember::create([
                "organization_id" => $organization->id,
                "user_id" => $user->id,
                "role" => OrganizationConstants::ROLE_ADMIN,
                "status" => OrganizationConstants::MEMBER_ACTIVE,
                "activated_at" => now(),
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        // Outside the transaction: a mail failure must not roll back the
        // account. The admin can always resend from the verify screen.
        $this->verify_service->sendPin($user);

        return [
            "user" => $user->refresh(),
            "organization" => $organization->refresh(),
        ];
    }

    private function validateRegistration(array $data): array
    {
        $blocked = config("business.blocked_email_domains");

        $validator = Validator::make($data, [
            "company_name" => "required|string|min:2|max:150",
            "work_email" => "required|email|unique:users,email",
            "industry" => "nullable|string|max:100",
            "headcount_band" => "nullable|string|max:50",
            "first_name" => "nullable|string|max:100",
            "last_name" => "nullable|string|max:100",
            "password" => [
                "required",
                "string",
                "regex:/" . PinConstants::PASSWORD_REGEX . "/",
            ],
        ], [
            "work_email.unique" => "The email address has already been used by another user",
            "password.regex" => "The password must be 8-32 characters and contain at least one uppercase letter, one lowercase letter, one number and one special character.",
        ]);

        $validator->after(function ($validator) use ($data, $blocked) {
            $email = $data["work_email"] ?? null;
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $domain = self::domainOf($email);

            if (in_array($domain, $blocked, true)) {
                $validator->errors()->add(
                    "work_email",
                    "Must be a company domain — free email providers (Gmail, Yahoo) aren't accepted."
                );
                return;
            }

            if (Organization::where("domain", $domain)->exists()) {
                $validator->errors()->add(
                    "work_email",
                    "A TalkAM for Business account already exists for {$domain}. Ask your administrator for an invite."
                );
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function domainOf(string $email): string
    {
        return strtolower(trim(Str::after($email, "@")));
    }

    private static function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: "org";
        $slug = $base;
        $suffix = 1;

        while (Organization::where("slug", $slug)->exists()) {
            $slug = $base . "-" . (++$suffix);
        }

        return $slug;
    }

    /**
     * B2B accounts never pick a community username during signup — the deck
     * promises "your community username stays private". One is derived from
     * the email local part and made unique.
     */
    public static function generateUsername(string $email): string
    {
        $base = Str::of(Str::before($email, "@"))
            ->lower()
            ->replaceMatches('/[^\w-]/', "")
            ->limit(20, "")
            ->toString();

        $base = $base !== "" ? $base : "member";
        $username = $base;

        while (User::where("username", $username)->exists()) {
            $username = $base . "-" . strtolower(MethodsHelper::getRandomToken(5));
        }

        return $username;
    }

    /* ── Domain confirmation ────────────────────────────────────────────── */

    /**
     * One button on the verify screen: check the 6-digit code, mark the email
     * verified AND the organization's domain confirmed.
     */
    public function verifyDomain(User $user, array $data): Organization
    {
        $validator = Validator::make($data, [
            "code" => "required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $membership = self::adminMembership($user);
        $organization = $membership->organization;

        $check = PinService::verify([
            "code" => $validator->validated()["code"],
            "type" => PinConstants::TYPE_VERIFY_EMAIL,
        ]);

        $pin_user = $check["user"] ?? null;

        if (empty($pin_user) || $pin_user->id !== $user->id) {
            throw new PinException("The code is invalid. Kindly request a new code.");
        }

        DB::beginTransaction();
        try {
            if (empty($user->email_verified_at)) {
                $user->update(["email_verified_at" => now()]);
            }

            $organization->update([
                "verified_at" => $organization->verified_at ?? now(),
                "status" => OrganizationConstants::STATUS_ACTIVE,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $organization->refresh();
    }

    /* ── Setup steps ────────────────────────────────────────────────────── */

    public function saveSeats(Organization $organization, array $data): Organization
    {
        $validator = Validator::make($data, [
            "seats_licensed" => "required|integer|min:1|max:1000000",
            "therapist_access" => "required|boolean",
            "payment_timing" => ["nullable", Rule::in(config("business.payment_timings"))],
            "bundle_sessions" => "nullable|integer|min:0|max:100000",
            "bundle_custom" => "nullable|boolean",
        ], [
            "seats_licensed.min" => "Choose at least one seat.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $uses_network = (bool) $validated["therapist_access"];
        $timing = $validated["payment_timing"] ?? "prepay";

        // Seats already handed out cannot be undercut by a later reduction.
        $used = $organization->seatsUsed();
        if ($validated["seats_licensed"] < $used) {
            throw new InvalidRequestException(
                "You have {$used} seats in use. Reduce your team before lowering the seat count below that."
            );
        }

        // A prepaid bundle only exists when the org uses the network AND prepays.
        // Postpay is pay-as-you-go, so nothing is bought up front.
        $has_bundle = $uses_network && $timing === "prepay";

        $organization->update([
            "seats_licensed" => $validated["seats_licensed"],
            "therapist_access" => $uses_network,
            "payment_timing" => $uses_network ? $timing : "prepay",
            "session_bundle_sessions" => $has_bundle ? ($validated["bundle_sessions"] ?? 0) : 0,
            "bundle_custom" => $has_bundle ? (bool) ($validated["bundle_custom"] ?? false) : false,
        ]);

        return $organization->refresh();
    }

    public function savePlan(Organization $organization, array $data): Organization
    {
        $validator = Validator::make($data, [
            "pay_method" => ["required", "string", Rule::in(config("business.pay_methods"))],
            "payment_timing" => ["nullable", Rule::in(config("business.payment_timings"))],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $update = ["pay_method" => $validated["pay_method"]];

        // The session model (prepay/postpay) is chosen on the seats screen; accept
        // it here too so the plan screen can confirm or change it in one place.
        if (!empty($validated["payment_timing"])) {
            $update["payment_timing"] = $validated["payment_timing"];
        }

        $organization->update($update);

        return $organization->refresh();
    }

    public function saveBench(Organization $organization, array $data): Organization
    {
        $validator = Validator::make($data, [
            "bench_topics" => "present|array|max:50",
            "bench_topics.*" => "required|string|max:60",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $topics = array_values(array_unique($validator->validated()["bench_topics"]));

        $organization->update(["bench_topics" => $topics]);

        return $organization->refresh();
    }

    /**
     * The "max sessions per employee, per billing cycle" cap (web §03
     * Settings → Session Policy). Disabling it clears the quota back to null
     * rather than just hiding it, so SessionCapService sees "uncapped".
     */
    public function saveSessionPolicy(Organization $organization, array $data): Organization
    {
        $validator = Validator::make($data, [
            "cap_enabled" => "required|boolean",
            "per_employee_session_quota" => "required_if:cap_enabled,true|nullable|integer|min:1|max:1000",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $organization->update([
            "per_employee_session_quota" => $validated["cap_enabled"]
                ? $validated["per_employee_session_quota"]
                : null,
        ]);

        return $organization->refresh();
    }

    /* ── Membership helpers ─────────────────────────────────────────────── */

    /** The caller's active membership, or a 403-shaped failure. */
    public static function membership(User $user): OrganizationMember
    {
        $membership = $user->organizationMember();

        if (empty($membership)) {
            throw new InvalidRequestException("You are not a member of any organization on TalkAM for Business.");
        }

        return $membership;
    }

    public static function adminMembership(User $user): OrganizationMember
    {
        $membership = self::membership($user);

        if ($membership->role !== OrganizationConstants::ROLE_ADMIN) {
            throw new InvalidRequestException("Only company admins can perform this action.");
        }

        return $membership;
    }

    /**
     * Onboarding-state + role payload the web router reads to decide which of
     * the three dashboards to land on. Additive block on v2's user/me — v1 and
     * mobile clients ignore it.
     */
    public static function context(?User $user): array
    {
        $membership = $user?->organizationMember();

        // A network therapist (has a therapist record but no org membership)
        // still belongs on the therapist dashboard — the web router reads
        // `dashboard`, so it must reflect the therapist role even without a
        // company.
        $is_therapist = !empty($user?->therapist);

        if (empty($membership) || empty($membership->organization)) {
            return [
                "is_member" => false,
                "role" => null,
                "is_therapist" => $is_therapist,
                "organization" => null,
                "dashboard" => $is_therapist ? OrganizationConstants::ROLE_THERAPIST : null,
            ];
        }

        $organization = $membership->organization;

        return [
            "is_member" => true,
            "role" => $membership->role,
            "is_therapist" => $is_therapist,
            "department" => $membership->department,
            "dashboard" => $membership->role,
            "organization" => [
                "id" => $organization->id,
                "name" => $organization->name,
                "slug" => $organization->slug,
                "logo" => $organization->logo,
                "status" => $organization->status,
                "verified_at" => $organization->verified_at?->toDateTimeString(),
                "seats_licensed" => (int) $organization->seats_licensed,
                "therapist_access" => (bool) $organization->therapist_access,
                "payment_timing" => $organization->payment_timing ?? "prepay",
                "bundle_custom" => (bool) $organization->bundle_custom,
                "pay_method" => $organization->pay_method,
            ],
            "onboarding" => [
                "domain_verified" => $organization->isVerified(),
                "seats_set" => (int) $organization->seats_licensed > 0,
                "plan_set" => !empty($organization->pay_method),
                "consent" => $user->consents()
                    ->whereIn("key", ConsentConstants::REQUIRED_KEYS)
                    ->where("granted", true)
                    ->count() === count(ConsentConstants::REQUIRED_KEYS),
                "topics" => $user->interests()->exists(),
                "self_check" => EmployeeSelfCheck::where("user_id", $user->id)->exists(),
            ],
        ];
    }
}
