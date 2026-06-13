<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->lastName().' Residence';

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'project_type' => 'pool',
            'status' => 'active',
            'phase' => 'Planning',
            'percent_complete' => fake()->numberBetween(5, 85),
            'health_status' => 'on_track',
            'contract_amount' => fake()->randomFloat(2, 120000, 500000),
            'starts_on' => now()->subMonth(),
            'estimated_completion_on' => now()->addMonths(3),
        ];
    }
}
