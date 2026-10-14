<?php

namespace Database\Factories;

use App\Models\Flight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flight>
 */
class FlightFactory extends Factory
{
    public function definition(): array
    {
        return [
            'flight_number' => strtoupper(fake()->unique()->bothify('NC-###')),
            'airline' => 'North Coast Air',
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
            'departure_date' => now()->addDays(2)->toDateString(),
            'departure_time' => '09:00',
            'arrival_time' => '10:20',
            'aircraft' => 'ATR 72',
            'total_seats' => 72,
            'available_seats' => 52,
            'status' => Flight::STATUS_SCHEDULED,
        ];
    }
}
