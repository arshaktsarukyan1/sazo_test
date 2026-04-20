<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReportKpiRequest extends FormRequest
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
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'device_type' => ['sometimes', 'nullable', 'in:desktop,mobile,tablet'],
            'traffic_source_id' => ['sometimes', 'nullable', 'integer', 'exists:traffic_sources,id'],
            'campaign_id' => ['sometimes', 'nullable', 'integer', 'exists:campaigns,id'],
        ];
    }
}
