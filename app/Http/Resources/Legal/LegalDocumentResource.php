<?php

namespace App\Http\Resources\Legal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A structured legal document for the web legal pages (web §06). The underlying
 * `document` json is already the exact shape the page consumes (title,
 * lastUpdated, callout, contact, sections) — this resource just guarantees the
 * keys and their defaults.
 */
class LegalDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $doc = $this->document ?? [];

        return [
            "title" => $doc["title"] ?? null,
            "lastUpdated" => $doc["lastUpdated"] ?? null,
            "callout" => $doc["callout"] ?? null,
            "contact" => $doc["contact"] ?? null,
            "sections" => $doc["sections"] ?? [],
        ];
    }
}
