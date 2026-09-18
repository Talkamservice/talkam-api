<?php

namespace Tests\Feature\V2;

use App\Models\Country;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfraSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_and_factories_work(): void
    {
        $user = User::factory()->create();
        $pin = Pin::factory()->create(['user_id' => $user->id]);
        $country = Country::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('pins', ['id' => $pin->id]);
        $this->assertDatabaseHas('countries', ['id' => $country->id]);
    }
}
