<?php

namespace Database\Factories;

use App\Constants\Therapist\TherapistConstants;
use App\Models\File;
use App\Models\TherapistApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapistDocument>
 */
class TherapistDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => TherapistApplication::factory(),
            'type' => TherapistConstants::DOC_DEGREE,
            'file_id' => fn () => File::create([
                'file_group' => 'therapist-documents',
                'name' => fake()->word() . '.pdf',
                'mime_type' => 'pdf',
                'path' => 'app/media/therapist-documents/' . fake()->uuid() . '.pdf',
            ])->id,
            'status' => TherapistConstants::DOC_STATUS_PENDING,
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }
}
