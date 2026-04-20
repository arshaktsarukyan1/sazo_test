<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Campaign;
use App\Models\Click;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualConversionRequest extends FormRequest
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
            'campaign_id' => ['required', 'integer', Rule::exists(Campaign::class, 'id')],
            'amount' => ['required', 'numeric', 'min:0'],
            'converted_at' => ['required', 'date'],
            'click_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Click::class, 'id')],
            'click_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists(Click::class, 'click_uuid')],
            'offer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('offers', 'id')],
            'lander_id' => ['sometimes', 'nullable', 'integer', Rule::exists('landers', 'id')],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'source' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
