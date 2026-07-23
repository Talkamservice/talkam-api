<?php

namespace Tests\Feature\V2\Booking;

use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingHoldCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_holds_released_unexpired_kept(): void
    {
        $expired = TherapySession::factory()->pending()->create([
            "hold_expires_at" => now()->subMinute(),
        ]);
        $active = TherapySession::factory()->pending()->create([
            "hold_expires_at" => now()->addMinutes(10),
        ]);
        $confirmed = TherapySession::factory()->create();

        $this->artisan("bookings:release-expired-holds")->assertSuccessful();

        $this->assertSame("expired", $expired->refresh()->status);
        $this->assertSame("pending_payment", $active->refresh()->status);
        $this->assertSame("confirmed", $confirmed->refresh()->status);
    }
}
