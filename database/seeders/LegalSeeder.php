<?php

namespace Database\Seeders;

use App\Models\PrivacyPolicy;
use App\Models\TermAndCondition;
use Illuminate\Database\Seeder;

/**
 * Seeds the structured web legal documents (web §06) onto the existing legal
 * tables' `document` column.
 *
 * Content lives in `database/data/legal_documents.json`, transcribed verbatim
 * from "TalkAM Privacy Policy.dc.html" and "TalkAM Terms of Use.dc.html". The
 * wording must only ever change with Legal's sign-off. Idempotent — refreshes
 * the single latest row per document (creating one if none exists), leaving the
 * mobile `body` blob untouched.
 */
class LegalSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path("data/legal_documents.json");

        if (!is_file($path)) {
            return;
        }

        $docs = json_decode(file_get_contents($path), true) ?: [];

        if (!empty($docs["privacy"])) {
            $this->upsertDocument(PrivacyPolicy::class, $docs["privacy"]);
        }

        if (!empty($docs["terms"])) {
            $this->upsertDocument(TermAndCondition::class, $docs["terms"]);
        }
    }

    private function upsertDocument(string $model, array $document): void
    {
        $record = $model::latest()->first() ?? new $model();

        // The web reads `document`; keep any existing mobile `body` intact, and
        // give a fresh row a plain-text fallback body derived from the sections.
        if (empty($record->body)) {
            $record->body = collect($document["sections"] ?? [])
                ->map(fn ($s) => ($s["title"] ?? "") . "\n" . ($s["body"] ?? ""))
                ->implode("\n\n");
        }

        $record->document = $document;

        if (empty($record->status)) {
            $record->status = "active";
        }

        $record->save();
    }
}
