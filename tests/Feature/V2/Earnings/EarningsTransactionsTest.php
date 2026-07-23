<?php

namespace Tests\Feature\V2\Earnings;

use App\Models\Therapist;
use App\Models\TherapistWalletTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EarningsTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_own_ledger_rows(): void
    {
        $therapist = Therapist::factory()->create();
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist->id,
            "type" => "credit",
            "amount" => 20000,
            "reference" => "SESSION-ABC",
        ]);

        Sanctum::actingAs($therapist->user);
        $this->getJson("/api/v2/therapist/earnings/transactions")
            ->assertStatus(200)
            ->assertJsonStructure(["data" => ["data" => [["id", "type", "amount", "status", "reference", "created_at"]], "pagination_meta"]]);
    }

    public function test_other_therapists_rows_are_excluded(): void
    {
        $therapist_a = Therapist::factory()->create();
        $therapist_b = Therapist::factory()->create();
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist_b->id,
            "type" => "credit",
            "amount" => 5000,
            "reference" => "SESSION-B",
        ]);

        Sanctum::actingAs($therapist_a->user);
        $this->assertEmpty($this->getJson("/api/v2/therapist/earnings/transactions")->json("data.data"));
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson("/api/v2/therapist/earnings/transactions")->assertStatus(401);
    }

    public function test_plain_user_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v2/therapist/earnings/transactions")->assertStatus(403);
    }
}
