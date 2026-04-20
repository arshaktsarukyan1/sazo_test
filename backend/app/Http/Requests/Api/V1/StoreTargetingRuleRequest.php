<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTargetingRuleRequest extends FormRequest
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
            'offer_id' => ['required', 'nullable', 'integer', 'exists:offers,id'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'device_type' => ['sometimes', 'nullable', 'in:desktop,mobile,tablet'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
