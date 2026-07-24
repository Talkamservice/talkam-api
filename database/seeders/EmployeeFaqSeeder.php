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
                [
                    'faq_category_id' => $category->id,
                    'question' => $faq['question'],
                ],
                [
                    'answer' => $faq['answer'],
                    'status' => StatusConstants::ACTIVE,
                ]
            );
        }
    }
}
