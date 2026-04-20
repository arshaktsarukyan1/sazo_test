<?php

namespace App\Services\Ingestion;

use App\Models\IngestionIdempotencyKey;
use Illuminate\Database\QueryException;

/**
 * Cross-cutting idempotency for webhooks and sync jobs (insert-if-new semantics).
 */
final class IngestionIdempotency
{
    public function has(string $scope, string $externalKey): bool
    {
        return IngestionIdempotencyKey::query()
            ->where('scope', $scope)
            ->where('external_key', $externalKey)
            ->exists();
    }

    /**
     * @return bool true if this worker acquired the key (first writer), false if duplicate
     */
    public function tryAcquire(string $scope, string $externalKey): bool
    {
        try {
            IngestionIdempotencyKey::query()->create([
                'scope' => $scope,
                'external_key' => $externalKey,
            ]);

            return true;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return false;
            }

            throw $e;
        }
    }

    public function release(string $scope, string $externalKey): void
    {
        IngestionIdempotencyKey::query()
            ->where('scope', $scope)
            ->where('external_key', $externalKey)
            ->delete();
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        if ($code === '23000') {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique violation');
    }
}
