<?php

namespace Database\Seeders;

use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Payment;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CreateTestCallSessionSeeder extends Seeder
{
    /**
     * Creates a CONFIRMED, fully-paid 1-hour VOICE therapy session starting
     * 10 minutes from now (override with CALL_TEST_START_IN_MINUTES /
     * CALL_TEST_FORMAT), so the Agora join/token flow can be exercised
     * immediately (SessionLifecycleService::join() requires status
     * confirmed|in_progress and now() >= starts_at - join_early_minutes).
     * Each run adds a NEW session between the same two accounts — safe to
     * re-run.
     *
     * A call needs two participants, so this seeds both sides — log in as
     * the therapist on one device/simulator and the client on another.
     *
     * Run:
     *   php artisan db:seed --class=CreateTestCallSessionSeeder
     *
     * Override start time / format:
     *   CALL_TEST_START_IN_MINUTES=10 CALL_TEST_FORMAT=video php artisan db:seed --class=CreateTestCallSessionSeeder
     *
     * Clear it afterwards:
     *   php artisan db:seed --class=ClearTestCallSessionsSeeder
     */
    private const THERAPIST_EMAIL = 'mikebingpseventh@gmail.com';
    private const CLIENT_EMAIL = 'call-test-client@talkam.test';
    private const PASSWORD = 'password123';
    private const AMOUNT = 15000;

    public function run(): void
    {
        $startInMinutes = (int) env('CALL_TEST_START_IN_MINUTES', 1);
        $format = env('CALL_TEST_FORMAT', TherapistConstants::FORMAT_VIDEO);
        if (!in_array($format, TherapistConstants::SESSION_FORMATS, true)) {
            $format = TherapistConstants::FORMAT_VIDEO;
        }

        $this->command->info("🎥 Seeding a test {$format} call session starting in {$startInMinutes} minute(s)...");

        $therapistUser = User::firstOrCreate(
            ['email' => self::THERAPIST_EMAIL],
            [
                'first_name' => 'Mike',
                'last_name' => 'Bing Seventh',
                'password' => bcrypt(self::PASSWORD),
                'user_type' => 'therapist',
                'email_verified_at' => now(),
                'role' => 'User',
                'country_id' => 160, // Nigeria
                'date_of_birth' => '1985-05-15',
                'gender' => 'Male',
                'phone_number' => '+2348012345678',
            ]
        );

        $therapist = Therapist::firstOrCreate(
            ['user_id' => $therapistUser->id],
            [
                'status' => 'Active',
                'credential_type' => 'Licensed',
                'session_rate' => self::AMOUNT,
                'session_formats' => [TherapistConstants::FORMAT_VIDEO, TherapistConstants::FORMAT_VOICE],
                'session_duration' => 60,
                'buffer_minutes' => 15,
                'years_experience' => 8,
                'verified_at' => now(),
            ]
        );

        $client = User::firstOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'first_name' => 'Call',
                'last_name' => 'Tester',
                'password' => bcrypt(self::PASSWORD),
                'user_type' => 'consumer',
                'email_verified_at' => now(),
                'role' => 'User',
                'country_id' => 160,
            ]
        );

        $startsAt = now()->addMinutes($startInMinutes);

        $payment = Payment::create([
            'user_id' => $client->id,
            'type' => 'one-off',
            'currency' => 'NGN',
            'amount' => self::AMOUNT,
            'reference' => 'TK-CALLTEST-' . strtoupper(Str::random(10)),
            'activity' => 'PAYMENT_FOR_SESSION',
            'status' => 'Completed',
            'description' => 'Therapy session payment (call test seed)',
            'metadata' => json_encode(['activity' => 'session_payment']),
        ]);

        $session = TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $client->id,
            'therapist_id' => $therapist->id,
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'format' => $format,
            'status' => TherapistConstants::SESSION_CONFIRMED,
            'coverage' => Cov::CONSUMER,
            'amount' => self::AMOUNT,
            'billed_amount' => 0,
            'currency' => 'NGN',
            'payment_id' => $payment->id,
        ]);

        $payment->update([
            'metadata' => json_encode([
                'activity' => 'session_payment',
                'booking_id' => $session->id,
            ]),
        ]);

        $this->command->info(str_repeat('=', 60));
        $this->command->info('✅ Test call session ready');
        $this->command->info(str_repeat('=', 60));
        $this->command->info("Session ID: {$session->id}  (uuid: {$session->uuid})");
        $this->command->info("Starts at:  {$startsAt->format('Y-m-d H:i:s')} (~{$startInMinutes} minute(s) from now)");
        $this->command->info("Duration:   60 minutes, format: {$format}");
        $this->command->info('');
        $this->command->info('Log in as BOTH sides to actually test the call (one device each):');
        $this->command->info("  Therapist: " . self::THERAPIST_EMAIL . " / " . self::PASSWORD);
        $this->command->info("  Client:    " . self::CLIENT_EMAIL . " / " . self::PASSWORD);
        $this->command->info('');
        $this->command->info('Open "My sessions" on each once the minute has passed and tap Join.');
        $this->command->info(str_repeat('=', 60));
    }
}
