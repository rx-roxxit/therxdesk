<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name_th' => 'บริษัท ทดสอบ จำกัด',
            'legal_name_latin' => fake()->company(),
            'address_th' => '123 ถนนทดสอบ กรุงเทพมหานคร 10110',
            'address_latin' => fake()->address(),
            'tax_id' => fake()->numerify('#############'),
            'vat_id' => null,
            'contact_email' => fake()->unique()->companyEmail(),
            'phone' => null,
            'effective_from' => now()->subDay(),
            'ended_at' => null,
        ];
    }
}
