<?php

namespace App\Services\Kpi;

/**
 * Minimum evidence for comparing variants (recommendation only; does not change traffic).
 */
final readonly class WinnerThresholds
{
    public function __construct(
        public int $minClicks = 100,
        /** Wilson lower bound of CR (0–100 scale) must be at least this value to qualify. */
        public float $confidenceFloorPercent = 1.0,
        /** If the top two eligible variants' profits are within this relative band, call a tie. */
        public float $profitTieRelativeEpsilon = 0.01,
        public float $wilsonZ = 1.96,
    ) {}
}
