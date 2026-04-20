<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesCampaignSplits;
use App\Services\WeightedDistributionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCampaignRequest extends FormRequest
{
    use ValidatesCampaignSplits;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'domain_id' => ['sometimes', 'nullable', 'integer', 'exists:domains,id'],
            'traffic_source_id' => ['sometimes', 'required', 'integer', 'exists:traffic_sources,id'],
            'external_traffic_campaign_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:100', 'alpha_dash', 'unique:campaigns,slug,' . $id],
            'status' => ['sometimes', 'required', 'in:draft,active,paused,archived'],
            'destination_url' => ['sometimes', 'required', 'url', 'max:2048'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'daily_budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'landers' => ['sometimes', 'array'],
            'landers.*.id' => ['required_with:landers', 'integer', 'distinct', 'exists:landers,id'],
            'landers.*.weight_percent' => ['required_with:landers', 'integer', 'between:1,100'],
            'landers.*.is_active' => ['sometimes', 'boolean'],
            'offers' => ['sometimes', 'array'],
            'offers.*.id' => ['required_with:offers', 'integer', 'distinct', 'exists:offers,id'],
            'offers.*.weight_percent' => ['required_with:offers', 'integer', 'between:1,100'],
            'offers.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCampaignSplits($validator, app(WeightedDistributionService::class));
        });
    }
}
