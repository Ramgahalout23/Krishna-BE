<?php

namespace App\Repositories;

use App\Models\Seo;

class SeoRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Seo::class;
    }

    public function findByPage(string $page): ?Seo
    {
        return Seo::where('page', $page)->first();
    }

    public function findByRoute(string $route): ?Seo
    {
        return Seo::where('route', $route)->first();
    }
}
