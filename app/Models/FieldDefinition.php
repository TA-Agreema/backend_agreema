<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldDefinition extends Model
{
    protected $table = 'fields_definitions';

    protected $fillable = [
        'field_key',
        'field_label',
        'field_type',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ContractFieldValue::class, 'field_definition_id');
    }
}
