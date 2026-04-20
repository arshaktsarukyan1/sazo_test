<?php

namespace App\Http\Requests\Concerns;

use App\Services\WeightedDistributionService;
use Illuminate\Validation\Validator;

trait ValidatesCampaignSplits
{
    protected function validateCampaignSplits(Validator $validator, WeightedDistributionService $weightedDistribution): void
    {
        $data = $validator->getData();
        foreach (['landers', 'offers'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            if (count($data[$key]) === 0) {
                continue;
            }
            try {
                $weightedDistribution->validateSplitConfig($data[$key]);
            } catch (\InvalidArgumentException $exception) {
                $validator->errors()->add($key, $exception->getMessage());
            }
        }
    }
}
