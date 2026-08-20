<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Therapist;
use App\Models\TherapistPayoutAccount;
use App\Models\TherapySession;
use App\Models\TherapistWalletTransaction;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TherapistEarningsSeeder extends Seeder
{
    /**
     * Seed therapist earnings data for mikebingpseventh@gmail.com
     * 
     * This creates:
     * - Therapist user account
     * - Payout account (bank details)
     * - Multiple therapy sessions (completed)
     * - Wallet transactions (credits from sessions)
     * - Payout transactions (debits)
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding therapist earnings data...');

        // Step 1: Create or get therapist user
        $therapistUser = User::firstOrCreate(
            ['email' => 'mikebingpseventh@gmail.com'],
            [
                'first_name' => 'Mike',
                'last_name' => 'Bing Seventh',
                'password' => bcrypt('password123'),
                'user_type' => 'therapist',
                'email_verified_at' => now(),
                'role' => 'User',
                'country_id' => 160, // Nigeria
                'date_of_birth' => '1985-05-15',
                'gender' => 'Male',
                'phone_number' => '+2348012345678',
            ]
        );

        $this->command->info("✅ User created/found: {$therapistUser->email}");

        // Step 2: Create therapist profile
        $therapist = Therapist::firstOrCreate(
            ['user_id' => $therapistUser->id],
            [
                'status' => 'Active',
                'credential_type' => 'Licensed',
                'session_rate' => 30000,
                'session_formats' => ['video', 'audio'],
                'session_duration' => 60,
                'buffer_minutes' => 15,
                'years_experience' => 8,
                'verified_at' => now(),
            ]
        );

        $this->command->info("✅ Therapist created: ID {$therapist->id}");

        // Step 3: Create payout account (bank details)
        $payoutAccount = TherapistPayoutAccount::firstOrCreate(
            ['user_id' => $therapistUser->id],
            [
                'bank_name' => 'Guaranty Trust Bank',
                'bank_code' => '058',
                'account_number' => '0123456789',
                'account_name' => 'MIKE BING SEVENTH',
                'provider' => 'flutterwave',
                'verified_at' => now(),
            ]
        );

        $this->command->info("✅ Payout account created");

        // Step 4: Create sample clients for sessions
        $clients = [];
        for ($i = 1; $i <= 5; $i++) {
            $client = User::firstOrCreate(
                ['email' => "client{$i}@example.com"],
                [
                    'first_name' => "Client",
                    'last_name' => "User {$i}",
                    'password' => bcrypt('password123'),
                    'user_type' => 'consumer',
                    'email_verified_at' => now(),
                    'role' => 'User',
                    'country_id' => 160,
                ]
            );
            $clients[] = $client;
        }

        $this->command->info("✅ Created 5 sample clients");

        // Step 5: Create completed therapy sessions with payments
        $sessionAmounts = [25000, 30000, 35000, 40000]; // Different session prices
        $sessions = [];
        $totalEarnings = 0;

        // Create 15 completed sessions over the last 2 months
        for ($i = 1; $i <= 15; $i++) {
            $client = $clients[array_rand($clients)];
            $amount = $sessionAmounts[array_rand($sessionAmounts)];
            $sessionDate = now()->subDays(rand(1, 60))->setTime(rand(9, 17), 0, 0);

            // Create payment first
            $payment = Payment::create([
                'user_id' => $client->id,
                'type' => 'one-off',
                'currency' => 'NGN',
                'amount' => $amount,
                'reference' => 'TK-SESS-' . strtoupper(Str::random(10)),
                'activity' => 'PAYMENT_FOR_SESSION',
                'status' => 'Completed',
                'description' => 'Therapy session payment',
                'metadata' => json_encode([
                    'activity' => 'session_payment',
                ]),
                'created_at' => $sessionDate->copy()->subMinutes(30),
                'updated_at' => $sessionDate->copy()->subMinutes(25),
            ]);

            // Create therapy session
            $session = TherapySession::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $client->id,
                'therapist_id' => $therapist->id,
                'organization_id' => null,
                'starts_at' => $sessionDate,
                'duration_minutes' => 60,
                'format' => 'video',
                'status' => 'completed',
                'coverage' => 'self',
                'amount' => $amount,
                'billed_amount' => $amount,
                'currency' => 'NGN',
                'payment_id' => $payment->id,
                'created_at' => $sessionDate->copy()->subHour(),
                'updated_at' => $sessionDate->copy()->addMinutes(60),
            ]);

            // Update payment metadata with booking_id
            $payment->update([
                'metadata' => json_encode([
                    'activity' => 'session_payment',
                    'booking_id' => $session->id,
                ]),
            ]);

            // Calculate therapist earnings (80% of session amount)
            $therapistEarning = $amount * 0.8; // 80% to therapist, 20% platform fee
            
            // Create wallet transaction (credit)
            TherapistWalletTransaction::create([
                'therapist_id' => $therapist->id,
                'type' => 'credit',
                'session_id' => $session->id,
                'amount' => $therapistEarning,
                'status' => 'completed',
                'reference' => "SESSION-{$session->id}-EARNING",
                'created_at' => $sessionDate->copy()->addMinutes(65), // After session completion
                'updated_at' => $sessionDate->copy()->addMinutes(65),
            ]);

            $totalEarnings += $therapistEarning;
            $sessions[] = $session;
        }

        $this->command->info("✅ Created 15 completed sessions with earnings");
        $this->command->info("   Total Earnings: ₦" . number_format($totalEarnings, 2));

        // Step 6: Create some payout transactions
        $payoutCount = 3;
        $totalPayouts = 0;

        for ($i = 1; $i <= $payoutCount; $i++) {
            $payoutAmount = rand(50000, 150000); // Random payout amounts
            $payoutDate = now()->subDays(rand(5, 50));

            // Create payout
            $payout = Payout::create([
                'therapist_id' => $therapist->id,
                'payout_account_id' => $payoutAccount->id,
                'amount' => $payoutAmount,
                'provider' => 'flutterwave',
                'provider_ref' => 'FLW-' . rand(1000000, 9999999),
                'status' => $i < 3 ? 'completed' : 'pending', // Last one pending
                'initiated_by' => 'system',
                'completed_at' => $i < 3 ? $payoutDate->copy()->addHours(2) : null,
                'created_at' => $payoutDate,
                'updated_at' => $i < 3 ? $payoutDate->copy()->addHours(2) : $payoutDate,
            ]);

            // Create wallet transaction (debit)
            TherapistWalletTransaction::create([
                'therapist_id' => $therapist->id,
                'type' => 'debit',
                'payout_id' => $payout->id,
                'amount' => $payoutAmount,
                'status' => 'completed',
                'reference' => $payout->provider_ref,
                'created_at' => $payoutDate,
                'updated_at' => $payoutDate,
            ]);

            $totalPayouts += $payoutAmount;
        }

        $this->command->info("✅ Created {$payoutCount} payout transactions");
        $this->command->info("   Total Payouts: ₦" . number_format($totalPayouts, 2));

        // Step 7: Calculate and display summary
        $currentBalance = $totalEarnings - $totalPayouts;

        $this->command->info("\n" . str_repeat("=", 60));
        $this->command->info("📊 SUMMARY");
        $this->command->info(str_repeat("=", 60));
        $this->command->info("Therapist: {$therapistUser->first_name} {$therapistUser->last_name}");
        $this->command->info("Email: {$therapistUser->email}");
        $this->command->info("Therapist ID: {$therapist->id}");
        $this->command->info("");
        $this->command->info("Total Sessions: 15");
        $this->command->info("Total Earnings: ₦" . number_format($totalEarnings, 2));
        $this->command->info("Total Payouts: ₦" . number_format($totalPayouts, 2));
        $this->command->info("Current Balance: ₦" . number_format($currentBalance, 2));
        $this->command->info(str_repeat("=", 60));

        // Step 8: Show recent transactions
        $this->command->info("\n💰 RECENT WALLET TRANSACTIONS:");
        $this->command->info(str_repeat("-", 60));

        $recentTransactions = TherapistWalletTransaction::where('therapist_id', $therapist->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTransactions as $txn) {
            $symbol = $txn->type === 'credit' ? '💚' : '🔴';
            $sign = $txn->type === 'credit' ? '+' : '-';
            $this->command->info("{$symbol} {$txn->created_at->format('Y-m-d')} | " . 
                strtoupper($txn->type) . " | {$sign}₦" . number_format($txn->amount, 2));
        }

        $this->command->info(str_repeat("-", 60));
        $this->command->info("\n✅ Seeding completed successfully!");
        
        $this->command->info("\n📝 To verify, run:");
        $this->command->info("php artisan tinker");
        $this->command->info("\$user = User::where('email', 'mikebingpseventh@gmail.com')->first();");
        $this->command->info("\$transactions = \$user->therapist->walletTransactions;");
    }
}
