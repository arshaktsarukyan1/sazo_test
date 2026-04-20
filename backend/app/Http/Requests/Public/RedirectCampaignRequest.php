<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class RedirectCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'campaignSlug' => $this->route('campaignSlug'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'campaignSlug' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }
}
