<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartMiningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'max:120'],
            'skills' => ['nullable'],
            'industry' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'experience' => ['nullable', 'string', 'max:40'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'candidate_type' => ['nullable', 'string', 'max:40'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'profile_url' => ['nullable', 'url'],
            'sources' => ['nullable', 'array'],
            'include_demo' => ['nullable', 'boolean'],
        ];
    }
}
