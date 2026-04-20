<?php

namespace App\Services;

use App\Models\Lander;

final class LanderService
{
    public function paginateIndex(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Lander::query()->latest('id')->paginate(20);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Lander
    {
        return Lander::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): Lander
    {
        $lander = Lander::query()->findOrFail($id);
        $lander->fill($attributes)->save();

        return $lander;
    }

    public function delete(int $id): void
    {
        Lander::query()->findOrFail($id)->delete();
    }
}
