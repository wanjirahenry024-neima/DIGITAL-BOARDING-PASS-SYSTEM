<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'passenger' && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.required' => 'Please confirm that you want to check in.',
            'confirmation.accepted' => 'Please confirm that you want to check in.',
        ];
    }
}
