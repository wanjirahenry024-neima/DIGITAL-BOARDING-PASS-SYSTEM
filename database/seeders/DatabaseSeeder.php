<?php

namespace Database\Seeders;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            foreach ([
                ['full_name' => 'Local Passenger', 'email' => 'passenger@example.com', 'phone' => '+15550000001', 'role' => 'passenger'],
                ['full_name' => 'Airport Staff', 'email' => 'staff@example.com', 'phone' => '+15550000002', 'role' => 'staff'],
                ['full_name' => 'Local Administrator', 'email' => 'admin@example.com', 'phone' => '+15550000003', 'role' => 'admin'],
            ] as $attributes) {
                User::updateOrCreate(
                    ['email' => $attributes['email']],
                    [...$attributes, 'password' => 'password', 'status' => 'active'],
                );
            }
        }

        foreach ([
            [
                'flight_number' => 'NC-201',
                'departure_airport' => 'RIV',
                'arrival_airport' => 'LKV',
                'departure_date' => now()->addDays(3)->toDateString(),
                'departure_time' => '08:30',
                'arrival_time' => '09:45',
                'available_seats' => 48,
                'status' => Flight::STATUS_SCHEDULED,
            ],
            [
                'flight_number' => 'NC-305',
                'departure_airport' => 'RIV',
                'arrival_airport' => 'MTN',
                'departure_date' => now()->addDays(4)->toDateString(),
                'departure_time' => '13:15',
                'arrival_time' => '14:40',
                'available_seats' => 31,
                'status' => Flight::STATUS_BOARDING,
            ],
            [
                'flight_number' => 'NC-412',
                'departure_airport' => 'LKV',
                'arrival_airport' => 'RIV',
                'departure_date' => now()->addDays(5)->toDateString(),
                'departure_time' => '16:00',
                'arrival_time' => '17:10',
                'available_seats' => 0,
                'status' => Flight::STATUS_SCHEDULED,
            ],
        ] as $flight) {
            $seededFlight = Flight::withTrashed()->updateOrCreate(
                ['flight_number' => $flight['flight_number']],
                [
                    ...$flight,
                    'airline' => 'North Coast Air',
                    'aircraft' => 'ATR 72',
                    'total_seats' => 72,
                ],
            );

            $seededFlight->restore();
        }
    }
}
