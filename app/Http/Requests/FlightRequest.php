<?php

namespace App\Http\Requests;

use App\Models\Flight;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()->isActive();
    }

    public function rules(): array
    {
        $flight = $this->route('flight');
        $flightId = $flight instanceof Flight ? $flight->getKey() : $flight;

        return [
            'flight_number' => ['required', 'string', 'max:20', Rule::unique('flights', 'flight_number')->ignore($flightId)],
            'airline' => ['required', 'string', 'max:100'],
            'departure_airport' => ['required', 'string', 'max:100', 'different:arrival_airport'],
            'arrival_airport' => ['required', 'string', 'max:100'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'departure_time' => ['required', 'date_format:H:i'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'aircraft' => ['nullable', 'string', 'max:100'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:10000'],
            'available_seats' => ['required', 'integer', 'min:0', 'lte:total_seats'],
            'status' => ['required', Rule::in(Flight::STATUSES)],
        ];
    }
}
