<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Business\OrganizationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\OrganizationMember;
use App\Models\TherapySession;
use App\Services\Business\OrganizationBillingService;
use App\Services\Business\OrganizationInviteService;
use App\Services\Business\OrganizationService;
use App\Services\Business\OrgRosterService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Platform-wide organization list — OrganizationBillingService only ever
 * runs per-organization (the org's own admin dashboard); this reuses its
 * currentPlan() row-by-row rather than duplicating plan-bucketing logic.
 *
 * "Setup" (complete/pending) is NOT the org's active/suspended status — it's
 * Organization::billingReady() (card on file, VA created, or bundle
 * funded), the same real signal the org's own dashboard already uses to
 * decide whether billing is actually configured.
 */
class PlatformBusinessService
{
    const SETUP_COMPLETE = "active";
    const SETUP_PENDING = "pending";
    const SUSPENDED = "suspended";

    public static function list(array $filters = [], int $per_page = 20)
    {
        $query = self::scopeByTab(Organization::query()->withCount('members'), $filters['tab'] ?? null);

        if (!empty($filters['search'])) {
            $query->where('name', 'LIKE', "%{$filters['search']}%");
        }

        $organizations = $query->with('creator:id,first_name,last_name,email')->latest()->paginate($per_page);

        $organizations->getCollection()->transform(fn (Organization $org) => self::row($org));

        return $organizations;
    }

    /** Tab counts + the KPI strip — one pass since "setup" isn't a stored
     *  column, so every count means re-evaluating billingReady() per row. */
    public static function overview(): array
    {
        $orgs = Organization::all(['id', 'status', 'seats_licensed', 'card_token', 'va_account_number', 'session_bundle_funded_at']);
        $setup_complete = $orgs->filter(fn ($o) => $o->billingReady());

        $revenue_mtd = (float) OrganizationInvoice::where('status', OrganizationInvoice::STATUS_PAID)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return [
            "total_orgs" => $orgs->count(),
            "setup_complete" => $setup_complete->count(),
            "setup_pending" => $orgs->count() - $setup_complete->count() - $orgs->where('status', OrganizationConstants::STATUS_SUSPENDED)->count(),
            "suspended" => $orgs->where('status', OrganizationConstants::STATUS_SUSPENDED)->count(),
            "total_seats" => (int) $orgs->sum('seats_licensed'),
            "revenue_mtd" => $revenue_mtd,
        ];
    }

    public static function show(int $id): array
    {
        $org = Organization::withCount('members')->with('creator.country:id,name')->findOrFail($id);

        $seats = $org->seats_licensed ?: 0;
        $active = $org->seatsUsed();
        $therapist_count = OrganizationMember::where('organization_id', $org->id)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->where('role', OrganizationConstants::ROLE_THERAPIST)
            ->count() + $org->therapistNetworkMemberships()->active()->count();

        // "Amount due" = the org's current open invoice, if any — not a
        // running balance (Organization has no such column).
        $due_invoice = OrganizationInvoice::where('organization_id', $org->id)
            ->where('status', OrganizationInvoice::STATUS_DUE)
            ->latest('due_at')
            ->first();

        return array_merge(self::row($org), [
            "domain" => $org->domain,
            "industry" => $org->industry,
            "verified_at" => $org->verified_at?->toDateTimeString(),
            "admin_country" => $org->creator?->country?->name,
            "plan" => OrganizationBillingService::currentPlan($org),
            "usage" => OrganizationBillingService::usage($org),
            "utilisation_percent" => $seats > 0 ? (int) round(($active / $seats) * 100) : 0,
            "therapist_count" => $therapist_count,
            "sessions_this_month" => TherapySession::where('organization_id', $org->id)
                ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            "amount_due" => $due_invoice ? (float) $due_invoice->amount : 0,
            "amount_due_period" => $due_invoice?->period_start?->format('M'),
            "payment_method_display" => self::paymentMethodDisplay($org),
        ]);
    }

    /** Employer-vouched (OrganizationMember role=therapist, seat-holding)
     *  and TalkAM-network (OrganizationTherapist, opted-in) therapists are
     *  two genuinely different relationships — both feed session coverage
     *  (see CoverageResolver), so both belong on this list, distinguished
     *  by tag rather than merged into one undifferentiated "therapist". */
    /** Merges two genuinely different relationships (see the docblock on
     *  therapistAccess's callers) into one list, so it's paginated by hand
     *  (Laravel's query-level paginator can't span two separate queries)
     *  rather than left to render everyone on one page. */
    public static function therapistAccess(int $id, int $page = 1, int $per_page = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        $org = Organization::findOrFail($id);

        $own = OrganizationMember::where('organization_id', $id)
            ->where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->with('user:id,first_name,last_name,email')
            ->get()
            ->map(fn ($m) => [
                "id" => "member-{$m->id}",
                "name" => $m->user ? trim("{$m->user->first_name} {$m->user->last_name}") : null,
                "email" => $m->user?->email,
                "access" => "B2B-Invited",
            ]);

        $network = $org->therapistNetworkMemberships()->active()
            ->with('therapist.user:id,first_name,last_name,email')
            ->get()
            ->map(fn ($t) => [
                "id" => "network-{$t->id}",
                "name" => $t->therapist?->user ? trim("{$t->therapist->user->first_name} {$t->therapist->user->last_name}") : null,
                "email" => $t->therapist?->user?->email,
                "access" => "Network",
            ]);

        return self::paginateCollection($own->concat($network)->values(), $page, $per_page);
    }

    /**
     * OrgRosterService::roster() (shared with the org's own admin dashboard)
     * returns a plain array, so this paginates its output rather than
     * touching that shared method's contract.
     *
     * Employee identity is deliberately anonymised here — the roster's own
     * `id` field is already the "EMP-0041"-style pseudonym used
     * everywhere else the org itself sees its roster (§ same privacy model
     * as the anonymous client refs on the therapist side); this endpoint
     * additionally drops the real `email` before it reaches platform
     * staff, who have no operational need to see it.
     */
    public static function employees(int $id, array $filters = [], int $page = 1, int $per_page = 10): LengthAwarePaginator
    {
        $org = Organization::findOrFail($id);
        // Same Regular/Employee/Therapist/Business -> Platform mapping as
        // PlatformUserService::PLATFORM_BY_TYPE — an org admin seat only
        // uses the web dashboard, employee/therapist seats use both.
        $roster = collect(OrgRosterService::roster($org, $filters))
            ->map(fn ($row) => [
                ...collect($row)->except('email')->all(),
                "platform" => $row['role'] === OrganizationConstants::ROLE_ADMIN ? "Web" : "Both",
            ]);

        return self::paginateCollection($roster, $page, $per_page);
    }

    public static function employeeDetail(int $id, $memberId): array
    {
        $org = Organization::findOrFail($id);
        $detail = OrgRosterService::employeeDetail($org, $memberId);
        unset($detail['email']);
        return $detail;
    }

    public static function deactivateEmployee(int $id, $memberId): void
    {
        $org = Organization::findOrFail($id);
        (new OrgRosterService)->deactivate($org, $memberId);
    }

    public static function reactivateEmployee(int $id, $memberId): void
    {
        $org = Organization::findOrFail($id);
        (new OrgRosterService)->reactivate($org, $memberId);
    }

    /** Wraps OrganizationInviteService::invite() — the same invite flow the
     *  org's own admin uses, run by staff on the org's behalf (e.g. support
     *  helping fill a roster). `invited_by` records the acting platform
     *  admin, not a real org admin. */
    public static function inviteEmployee(int $id, array $data): array
    {
        $org = Organization::findOrFail($id);
        return (new OrganizationInviteService)->invite($org, auth()->user(), $data);
    }

    private static function paginateCollection(Collection $items, int $page, int $per_page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->forPage($page, $per_page)->values(),
            $items->count(),
            $per_page,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    public static function invoices(int $id, int $per_page = 20)
    {
        return OrganizationInvoice::where('organization_id', $id)->latest('period_start')->paginate($per_page);
    }

    /** No org action logs to activity_logs anywhere in the codebase today
     *  (confirmed by grep — every setModel() call is User/Role/Post/etc.,
     *  never Organization::class), so this starts genuinely empty. suspend()
     *  and reactivate() below are the first two writers. */
    public static function activity(int $id, int $per_page = 20)
    {
        return \App\Models\ActivityLog::where('model', Organization::class)
            ->where('model_id', $id)
            ->with('user:id,first_name,last_name,email')
            ->latest()
            ->paginate($per_page);
    }

    public static function suspend(int $id): Organization
    {
        $org = Organization::findOrFail($id);
        $org->update(['status' => OrganizationConstants::STATUS_SUSPENDED]);
        self::logStatusChange($org, 'suspended', 'Organization suspended');
        return $org->refresh();
    }

    public static function reactivate(int $id): Organization
    {
        $org = Organization::findOrFail($id);
        $org->update(['status' => OrganizationConstants::STATUS_ACTIVE]);
        // ActivityLogConstants::EVENTS is a closed whitelist — "activated"
        // is the closest real value to "reactivated" (not itself listed).
        self::logStatusChange($org, 'activated', 'Organization reactivated');
        return $org->refresh();
    }

    /** Wraps OrganizationService::register() — the same org+admin-account
     *  creation the self-serve signup wizard uses, run by staff instead
     *  (same validation, same pending-verification email to the new admin). */
    public static function create(array $data): Organization
    {
        return (new OrganizationService)->register($data)['organization'];
    }

    /** Restricted to name/industry — everything else on this record (seats,
     *  plan, billing) already has its own dedicated real flow elsewhere. */
    public static function update(int $id, array $data): Organization
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            "name" => "required|string|min:2|max:150",
            "industry" => "nullable|string|max:100",
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $org = Organization::findOrFail($id);
        $org->update($validator->validated());
        return $org->refresh();
    }

    public static function destroy(int $id): void
    {
        $org = Organization::find($id);
        if (empty($org)) {
            throw new InvalidRequestException("Organization not found");
        }

        $org->delete();
    }

    private static function logStatusChange(Organization $org, string $event, string $title): void
    {
        (new \App\Services\ActivityLog\ActivityLogService)
            ->setEvent($event)
            ->setTitle($title)
            ->setDescription((auth()->user()?->email) . " {$event} \"{$org->name}\"")
            ->setType(\App\Constants\ActivityLog\ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity($event)
            ->setModel(Organization::class, $org->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(["Organization" => $org->refresh()->toArray()])
            ->setUrl(request()?->fullUrl())
            ->log();
    }

    private static function paymentMethodDisplay(Organization $org): ?string
    {
        if ($org->card_brand && $org->card_last4) {
            return "Flutterwave · " . ucfirst($org->card_brand) . " ****{$org->card_last4}";
        }
        if ($org->va_bank_name && $org->va_account_number) {
            $last4 = substr($org->va_account_number, -4);
            return "Flutterwave · {$org->va_bank_name} ****{$last4}";
        }
        return null;
    }

    private static function row(Organization $org): array
    {
        return [
            "id" => $org->id,
            "name" => $org->name,
            "industry" => $org->industry,
            "status" => $org->status,
            "admin" => $org->creator ? trim("{$org->creator->first_name} {$org->creator->last_name}") : null,
            "admin_email" => $org->creator?->email,
            "seats_licensed" => $org->seats_licensed,
            "active_members" => $org->seatsUsed(),
            "setup_complete" => $org->billingReady(),
            "pay_method" => $org->pay_method,
            "created_at" => $org->created_at->toDateTimeString(),
        ];
    }

    private static function scopeByTab($query, ?string $tab)
    {
        return match ($tab) {
            self::SUSPENDED => $query->where('status', OrganizationConstants::STATUS_SUSPENDED),
            self::SETUP_COMPLETE => $query->where(fn ($q) => $q->whereNotNull('card_token')->orWhereNotNull('va_account_number')->orWhereNotNull('session_bundle_funded_at')),
            self::SETUP_PENDING => $query->where('status', '!=', OrganizationConstants::STATUS_SUSPENDED)
                ->whereNull('card_token')->whereNull('va_account_number')->whereNull('session_bundle_funded_at'),
            default => $query,
        };
    }
}
