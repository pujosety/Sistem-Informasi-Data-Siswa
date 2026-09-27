<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nisn' => (string) random_int(1000000000, 9999999999),
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(['L', 'P']),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-19 years', '-15 years'),
            'religion' => 'Islam',
            'phone' => '08'.fake()->numerify('##########'),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'province' => 'Jawa Barat',
            'previous_school' => 'SMP Negeri '.fake()->numberBetween(1, 20),
            'graduation_year' => (string) now()->year,
            'previous_score' => fake()->numberBetween(70, 95),
            'entry_year' => (string) now()->year,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Student $student) {
            if (! AcademicYear::count()) {
                AcademicYear::create([
                    'name' => now()->year.'/'.(now()->year + 1),
                    'start_date' => now()->startOfYear(),
                    'end_date' => now()->addYear()->endOfYear(),
                    'is_active' => true,
                ]);
            }

            // Always make sure a registration exists, and always make sure its
            // document placeholders exist. `seedPlaceholders` is idempotent, so
            // calling it unconditionally is safe even when a caller has already
            // created or updated the registration itself.
            $registration = Registration::firstOrCreate(
                ['student_id' => $student->id],
                [
                    'academic_year_id' => AcademicYear::first()->id,
                    'status' => Registration::STATUS_DRAFT,
                ]
            );

            app(\App\Services\DocumentService::class)->seedPlaceholders($registration);
        });
    }
}
