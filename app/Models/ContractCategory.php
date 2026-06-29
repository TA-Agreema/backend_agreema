<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractCategory extends Model
{
    protected $fillable = [
        'name',
        'number_prefix',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'category_id');
    }
}
