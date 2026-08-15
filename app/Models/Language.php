<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasUuids;
    protected $fillable = ['code', 'name', 'native_name', 'is_default', 'is_active'];
    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class, 'language_code', 'code');
    }
}
