<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBoardingPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payload' => [
                'required',
                'string',
                'max:512',
            ],
            'gate' => ['nullable', 'string', 'max:50'],
            'device_identifier' => ['nullable', 'string', 'max:100'],
        ];
    }
}
