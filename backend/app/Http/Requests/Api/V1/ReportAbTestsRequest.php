<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReportAbTestsRequest extends FormRequest
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
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'device_type' => ['sometimes', 'nullable', 'in:desktop,mobile,tablet'],
        ];
    }
}
