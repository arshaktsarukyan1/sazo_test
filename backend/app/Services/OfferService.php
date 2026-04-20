<?php

namespace App\Services;

use App\Models\Offer;

final class OfferService
{
    public function paginateIndex(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Offer::query()->latest('id')->paginate(20);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Offer
    {
        return Offer::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): Offer
    {
        $offer = Offer::query()->findOrFail($id);
        $offer->fill($attributes)->save();

        return $offer;
    }

    public function delete(int $id): void
    {
        Offer::query()->findOrFail($id)->delete();
    }
}
