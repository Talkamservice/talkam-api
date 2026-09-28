<?php

namespace Tests\Feature\V2\Legal;

use App\Models\PrivacyPolicy;
use App\Models\TermAndCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public web legal pages (web §06): structured Privacy / Terms documents,
 * served off the existing legal tables without disturbing the mobile `body`.
 */
class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function seedPrivacy(): void
    {
        PrivacyPolicy::create([
            "body" => "mobile blob",
            "status" => "active",
            "document" => [
                "title" => "Privacy Policy",
                "lastUpdated" => "July 9, 2026",
                "callout" => "TalkAM is compliant with the NDPA 2023.",
                "contact" => ["lead" => "Contact our DPO at", "email" => "privacy@talkam.net"],
                "sections" => [
                    ["title" => "1. What We Collect", "body" => "Account information."],
                    ["title" => "2. How We Use Your Data", "body" => "To operate the platform."],
                ],
            ],
        ]);
    }

    private function seedTerms(): void
    {
        TermAndCondition::create([
            "body" => "mobile blob",
            "status" => "active",
            "document" => [
                "title" => "Terms of Use",
                "lastUpdated" => "July 9, 2026",
                "callout" => null,
                "contact" => ["lead" => "Contact us at", "email" => "legal@talkam.net"],
                "sections" => [
                    ["title" => "1. Acceptance of Terms", "body" => "By using TalkAM..."],
                ],
            ],
        ]);
    }

    public function test_privacy_returns_the_structured_document(): void
    {
        $this->seedPrivacy();

        $response = $this->getJson("/api/v2/legal/documents/privacy");

        $response->assertOk();
        $data = $response->json("data");

        $this->assertSame("Privacy Policy", $data["title"]);
        $this->assertStringContainsString("NDPA", $data["callout"]);
        $this->assertSame("privacy@talkam.net", $data["contact"]["email"]);
        $this->assertCount(2, $data["sections"]);
        $this->assertSame("1. What We Collect", $data["sections"][0]["title"]);
    }

    public function test_terms_returns_the_structured_document_with_null_callout(): void
    {
        $this->seedTerms();

        $response = $this->getJson("/api/v2/legal/documents/terms");

        $response->assertOk();
        $data = $response->json("data");

        $this->assertSame("Terms of Use", $data["title"]);
        $this->assertNull($data["callout"]);
        $this->assertSame("legal@talkam.net", $data["contact"]["email"]);
    }

    public function test_unknown_slug_is_not_found(): void
    {
        $this->getJson("/api/v2/legal/documents/cookies")->assertNotFound();
    }

    public function test_missing_document_is_not_found(): void
    {
        // A row with no structured document (mobile-only) is not a web document.
        PrivacyPolicy::create(["body" => "mobile only", "status" => "active"]);

        $this->getJson("/api/v2/legal/documents/privacy")->assertNotFound();
    }

    public function test_returns_the_latest_document(): void
    {
        PrivacyPolicy::create([
            "body" => "old",
            "status" => "active",
            "document" => ["title" => "Old Policy", "sections" => []],
        ]);
        PrivacyPolicy::create([
            "body" => "new",
            "status" => "active",
            "document" => ["title" => "New Policy", "sections" => []],
        ]);

        $data = $this->getJson("/api/v2/legal/documents/privacy")->json("data");

        $this->assertSame("New Policy", $data["title"]);
    }

    public function test_mobile_privacy_policy_endpoint_is_unchanged(): void
    {
        $this->seedPrivacy();

        // The mobile/v1 shape stays {id, body} — the structured document must not
        // leak into it.
        $data = $this->getJson("/api/v2/user/privacy-policies")->json("data");

        $this->assertSame(["body", "id"], collect(array_keys($data))->sort()->values()->all());
        $this->assertArrayNotHasKey("document", $data);
    }
}
