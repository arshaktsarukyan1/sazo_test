<?php

namespace App\Services\Kpi;

final readonly class WinnerRecommendationResult
{
    public const STATUS_INSUFFICIENT_DATA = 'insufficient_data';

    public const STATUS_NO_CLEAR_WINNER = 'no_clear_winner';

    public const STATUS_RECOMMENDED = 'recommended';

    /**
     * @param list<array{variant_key: string, clicks: int, wilson_cr_lb_percent: float|null, profit: float, eligible: bool}> $variant_debug
     */
    public function __construct(
        public string $status,
        public string $label,
        public ?string $recommendedVariantKey,
        public array $variant_debug = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'label' => $this->label,
            'recommended_variant_key' => $this->recommendedVariantKey,
            'variants' => $this->variant_debug,
        ];
    }
}
