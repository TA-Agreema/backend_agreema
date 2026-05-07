<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractSigner extends Model
{
    protected $fillable = [
        'contract_id',
        'user_id',
        'external_email',
        'signer_type',
        'sequence',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signatureTokens(): HasMany
    {
        return $this->hasMany(ExternalSignatureToken::class);
    }
}
