<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'plan' => 'demo',
            'subscription_status' => 'trial',
            'brand_primary_color' => '#0b1020',
            'brand_accent_color' => '#d6b36a',
            'feature_flags' => [
                'media_messaging' => true,
                'payments_visible' => true,
                'document_uploads' => true,
                'subcontractor_access' => true,
                'branded_portal' => true,
                'ai_update_writer' => false,
                'custom_domain' => false,
            ],
        ];
    }
}
