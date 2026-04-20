<?php

namespace App\Services;

use App\Models\Domain;

final class DomainService
{
    public function paginateIndex(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Domain::query()
            ->withCount('campaigns')
            ->latest('id')
            ->paginate(20);
    }

    public function findForShow(int $id): Domain
    {
        return Domain::query()
            ->withCount('campaigns')
            ->with([
                'campaigns' => static fn ($q) => $q
                    ->select(['id', 'name', 'slug', 'status', 'domain_id'])
                    ->orderByDesc('id'),
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Domain
    {
        return Domain::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): Domain
    {
        $domain = Domain::query()->findOrFail($id);
        $domain->fill($attributes);
        $domain->save();

        return $domain;
    }

    public function delete(int $id): void
    {
        $domain = Domain::query()->findOrFail($id);
        $domain->delete();
    }
}
