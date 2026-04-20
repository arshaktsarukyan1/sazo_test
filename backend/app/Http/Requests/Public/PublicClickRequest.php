<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class PublicClickRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'campaign' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_-]+$/'],
            'sid' => ['nullable', 'string', 'max:80'],
        ];
    }
}
