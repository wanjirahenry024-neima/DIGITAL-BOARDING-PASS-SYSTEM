<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_reference' => 'DBP-'.strtoupper((string) Str::ulid()),
            'passenger_id' => User::factory(),
            'flight_id' => Flight::factory(),
            'booking_status' => Booking::STATUS_CONFIRMED,
            'booking_date' => now(),
            'number_of_passengers' => 1,
        ];
    }
}
