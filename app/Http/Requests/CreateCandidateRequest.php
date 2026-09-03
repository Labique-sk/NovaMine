<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'designation' => ['required', 'string', 'max:160'],
            'company' => ['nullable', 'string', 'max:160'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'industry' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
            'professional_email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'profile_url' => ['nullable', 'url', 'max:2048'],
            'portfolio_url' => ['nullable', 'url', 'max:2048'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:120'],
            'education' => ['nullable', 'array'],
            'education.*.institution' => ['nullable', 'string', 'max:160'],
            'education.*.credential' => ['nullable', 'string', 'max:160'],
            'education.*.field' => ['nullable', 'string', 'max:120'],
            'education.*.year' => ['nullable', 'integer', 'min:1950', 'max:2030'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
