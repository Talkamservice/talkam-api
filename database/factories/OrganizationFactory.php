<?php

namespace Database\Factories;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();
        $domain = Str::slug($name) . ".com";

        return [
            "name" => $name,
            "slug" => Str::slug($name) . "-" . Str::lower(Str::random(4)),
            "domain" => $domain,
            "industry" => "Banking & Finance",
            "headcount_band" => "100 - 300",
            "hr_contact_email" => "hr@" . $domain,
            "status" => OrganizationConstants::STATUS_ACTIVE,
            "seats_licensed" => 250,
            "therapist_access" => true,
            "session_bundle_sessions" => 25,
            "pay_method" => "invoice",
            "bench_topics" => [],
            "verified_at" => now(),
        ];
    }

    /** Fresh signup: domain not confirmed yet, nothing priced. */
    public function unverified(): static
    {
        return $this->state(fn () => [
            "status" => OrganizationConstants::STATUS_PENDING_VERIFICATION,
            "verified_at" => null,
            "seats_licensed" => 0,
            "session_bundle_sessions" => 0,
            "pay_method" => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ["status" => OrganizationConstants::STATUS_SUSPENDED]);
    }

    public function seats(int $seats): static
    {
        return $this->state(fn () => ["seats_licensed" => $seats]);
    }
}
