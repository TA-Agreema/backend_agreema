<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalSignatureToken extends Model
{
    protected $fillable = [
        'contract_signer_id',
        'token',
        'iteration',
        'review_status',
        'review_notes',
        'expired_at',
        'used_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function contractSigner(): BelongsTo
    {
        return $this->belongsTo(ContractSigner::class);
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }
}
