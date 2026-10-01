<?php

namespace Tests\Feature\V2\Notifications;

use App\Models\User;
use App\Models\WellnessNudgeMessage;
use App\Notifications\User\WellnessCheckinNudgeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WellnessNudgeMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = "/api/v2/platform-admin/wellness-nudges";

    private function actingAsAdmin(string $role = "Content Manager"): void
    {
        $user = User::factory()->create();
        Role::findOrCreate($role);
        $user->assignRole($role);
        Sanctum::actingAs($user);
    }

    public function test_notification_uses_a_message_from_the_active_pool(): void
    {
        WellnessNudgeMessage::query()->delete();
        WellnessNudgeMessage::create(["title" => "Hi", "message" => "Only active one", "is_active" => true]);
        WellnessNudgeMessage::create(["title" => "Off", "message" => "Disabled one", "is_active" => false]);

        $data = (new WellnessCheckinNudgeNotification)->toDatabase(User::factory()->create());

        $this->assertSame("Only active one", $data["message"]);
        $this->assertSame("Hi", $data["title"]);
        $this->assertSame("wellness_nudge", $data["type"]);
    }

    public function test_seeded_pool_produces_varied_messages(): void
    {
        $seen = collect(range(1, 40))
            ->map(fn () => (new WellnessCheckinNudgeNotification)->toDatabase(User::factory()->make())["message"])
            ->unique();

        $this->assertGreaterThan(1, $seen->count());
    }

    public function test_it_falls_back_to_the_fixed_text_when_the_pool_is_empty(): void
    {
        WellnessNudgeMessage::query()->delete();

        $data = (new WellnessCheckinNudgeNotification)->toDatabase(User::factory()->make());

        $this->assertSame("You haven't logged your mood today. How are you feeling?", $data["message"]);
    }

    public static function crudRoles(): array
    {
        return [["Super Admin"], ["Admin"], ["Content Manager"]];
    }

    /** @dataProvider crudRoles */
    public function test_admin_can_create_update_list_and_delete_messages(string $role): void
    {
        $this->actingAsAdmin($role);

        $id = $this->postJson(self::URL, ["message" => "Hello there"])->assertOk()->json("data.id");
        $this->assertDatabaseHas("wellness_nudge_messages", ["id" => $id, "message" => "Hello there", "is_active" => 1]);

        $this->putJson(self::URL . "/$id", ["is_active" => false])->assertOk()->assertJsonPath("data.is_active", false);

        $this->getJson(self::URL)->assertOk()->assertJsonFragment(["id" => $id]);

        $this->deleteJson(self::URL . "/$id")->assertOk();
        $this->assertDatabaseMissing("wellness_nudge_messages", ["id" => $id]);
    }

    public function test_validation_and_not_found(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URL, [])->assertStatus(422);
        $this->putJson(self::URL . "/999999", ["message" => "x"])->assertStatus(404);
        $this->deleteJson(self::URL . "/999999")->assertStatus(404);
    }

    public function test_non_admins_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(self::URL)->assertStatus(403);
    }

    public function test_support_staff_cannot_manage_messages(): void
    {
        $this->actingAsAdmin("Support Staff");

        $this->postJson(self::URL, ["message" => "x"])->assertStatus(403);
    }
}
