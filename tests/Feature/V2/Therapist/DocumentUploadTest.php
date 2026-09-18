<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\TherapistDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_pdf_within_5mb(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "degree_certificate",
            "file" => UploadedFile::fake()->create("degree.pdf", 4000, "application/pdf"),
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("therapist_documents", ["type" => "degree_certificate"]);
        $this->assertDatabaseHas("files", ["file_group" => "therapist-documents"]);
    }

    public function test_accepts_jpg_within_5mb(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "headshot",
            "file" => UploadedFile::fake()->image("headshot.jpg")->size(3000),
        ])->assertStatus(200);

        $this->assertDatabaseHas("therapist_documents", ["type" => "headshot"]);
    }

    public function test_rejects_file_over_5mb(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "degree_certificate",
            "file" => UploadedFile::fake()->create("degree.pdf", 6000, "application/pdf"),
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, TherapistDocument::count());
    }

    public function test_rejects_wrong_mime_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "headshot",
            "file" => UploadedFile::fake()->image("headshot.png")->size(1000),
        ])->assertStatus(422);

        $this->assertSame(0, TherapistDocument::count());
    }

    public function test_rejects_unknown_document_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "selfie",
            "file" => UploadedFile::fake()->create("doc.pdf", 1000, "application/pdf"),
        ])->assertStatus(422);

        $this->assertSame(0, TherapistDocument::count());
    }

    public function test_licence_persists_expires_at(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $expires = now()->addYear()->toDateString();

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "licence",
            "file" => UploadedFile::fake()->create("licence.pdf", 1000, "application/pdf"),
            "expires_at" => $expires,
        ])->assertStatus(200);

        $this->assertDatabaseHas("therapist_documents", [
            "type" => "licence",
            "expires_at" => $expires,
        ]);
    }

    public function test_reupload_replaces_document_of_same_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "headshot",
            "file" => UploadedFile::fake()->image("one.jpg")->size(500),
        ])->assertStatus(200);

        $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "headshot",
            "file" => UploadedFile::fake()->image("two.jpg")->size(500),
        ])->assertStatus(200);

        $this->assertSame(1, TherapistDocument::where("type", "headshot")->count());
    }

    public function test_owner_can_delete_document(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "headshot",
            "file" => UploadedFile::fake()->image("headshot.jpg")->size(500),
        ])->json("data.id");

        $this->deleteJson("/api/v2/therapist/application/documents/{$id}")->assertStatus(200);
        $this->assertSame(0, TherapistDocument::count());
    }

    public function test_cannot_delete_another_users_document(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson("/api/v2/therapist/application/documents", [
            "type" => "headshot",
            "file" => UploadedFile::fake()->image("headshot.jpg")->size(500),
        ])->json("data.id");

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/v2/therapist/application/documents/{$id}")
            ->assertStatus(404);

        $this->assertSame(1, TherapistDocument::count());
    }
}
