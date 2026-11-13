<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(['role' => 'patient']),
            'dob' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Patient::GENDERS),
            'marital_status' => fake()->randomElement(Patient::MARITAL_STATUSES),
        ];
    }
}
