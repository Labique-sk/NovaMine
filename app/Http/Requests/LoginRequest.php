<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'max:11'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
