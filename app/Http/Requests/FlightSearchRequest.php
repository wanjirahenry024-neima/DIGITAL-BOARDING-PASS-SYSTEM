<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlightSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'passenger' && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'departure_airport' => ['nullable', 'string', 'max:100'],
            'arrival_airport' => ['nullable', 'string', 'max:100'],
            'departure_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
