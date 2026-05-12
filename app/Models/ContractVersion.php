<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractVersion extends Model
{
    protected $fillable = [
        'contract_id',
        'version_number',
        'content',
        'created_by',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ContractFieldValue::class);
    }

    public function signerReviews(): HasMany
    {
        return $this->hasMany(ContractSignerReview::class);
    }
}
