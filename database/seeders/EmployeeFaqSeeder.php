<?php

namespace Database\Seeders;

use App\Constants\General\StatusConstants;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the employee Help & Support FAQs so the web dashboard's Help page is
 * served by the API rather than hard-coded in the frontend.
 *
 * Copy transcribed verbatim from "TalkAM B2B Employee Dashboard.dc.html"
 * (`employeeFaqs`). Idempotent — safe to re-run. The content team owns these
 * strings from here on; editing them is a data change, not a deploy.
 */
class EmployeeFaqSeeder extends Seeder
{
    const CATEGORY = 'Employee Dashboard';

    const FAQS = [
        [
            'question' => 'Can my employer see my sessions or messages?',
            'answer' => 'No. Your employer only ever sees anonymised, aggregate trends across the whole team — never your individual sessions, mood check-ins, messages, or community activity.',
        ],
        [
            'question' => 'How do I book or reschedule a session?',
            'answer' => 'Go to My Sessions to book a new slot, or use Reschedule / Cancel on an upcoming session. Cancelling ≥24h ahead is a full refund; under 24h is 50%; no-shows are not refunded.',
        ],
        [
            'question' => 'What happens to my account if I leave the company?',
            'answer' => 'Your personal TalkAM account and session history stay yours. You’ll just move off the company plan — you can keep using TalkAM on your own, or reach out to us about continuing coverage.',
        ],
        [
            'question' => 'Is the community feed anonymous?',
            'answer' => 'Yes — posting and browsing the community happens under an anonymous username in the mobile app. Nothing you post there is tied to your work identity.',
        ],
        [
            'question' => 'How do I change my therapist?',
            'answer' => 'Open Messages or My Sessions and select "Find a different therapist" — you can browse and switch at any time, at no extra cost.',
        ],
        [
            'question' => 'I’m in crisis right now — what do I do?',
            'answer' => 'If you are in immediate danger, please contact local emergency services. You can also message your therapist directly or use the crisis resources linked in the mobile app’s Community tab.',
        ],
    ];

    public function run(): void
    {
        $category = FaqCategory::firstOrCreate(
            ['name' => self::CATEGORY],
            ['status' => StatusConstants::ACTIVE]
        );

        foreach (self::FAQS as $faq) {
            Faq::firstOrCreate(
                ['faq_category_id' => $category->id, 'question' => $faq['question']],
                ['answer' => $faq['answer'], 'status' => StatusConstants::ACTIVE]
            );
        }

        // Therapist Help & Support FAQs (web §04), transcribed from
        // "TalkAM B2B Therapist Dashboard.dc.html".
        $therapist_category = FaqCategory::firstOrCreate(
            ['name' => 'Therapist Dashboard'],
            ['status' => StatusConstants::ACTIVE]
        );

        foreach (self::THERAPIST_FAQS as $faq) {
            Faq::firstOrCreate(
                ['faq_category_id' => $therapist_category->id, 'question' => $faq['question']],
                ['answer' => $faq['answer'], 'status' => StatusConstants::ACTIVE]
            );
        }
    }

    // Transcribed verbatim from the therapist deck's `therapistFaqs` array.
    const THERAPIST_FAQS = [
        ['question' => 'How and when do I get paid?', 'answer' => 'Payouts run weekly, every Friday, straight to the bank account on file in the mobile app. You can review the full breakdown of each payout under Earnings on mobile.'],
        ['question' => 'How do I update my availability?', 'answer' => 'Go to Availability to set your recurring weekly schedule, or block specific dates when you’re away — clients can only book into open slots.'],
        ['question' => 'A client cancelled — do I still get paid?', 'answer' => 'If a client cancels ≥24h ahead, no session fee is paid. Under 24h, you’re paid 50%. No-shows are paid in full, same as a completed session.'],
        ['question' => 'How do I report a concerning client interaction?', 'answer' => 'Use "Report a client" in Profile & Account (Safety section). Our trust & safety team reviews every report and will follow up with you directly.'],
        ['question' => 'Can I take a break without losing my profile?', 'answer' => 'Yes — use "Deactivate profile temporarily" in the Danger Zone. This hides you from client search but keeps your verification, reviews, and history intact.'],
        ['question' => 'Who do I contact about my credentials or verification status?', 'answer' => 'Use the live chat below, or email credentials@talkam.net with your therapist ID — our verification team responds within one business day.'],
    ];
}
