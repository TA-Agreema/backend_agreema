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
        'signer_name',
        'signer_role',
        'sequence',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signatureTokens(): HasMany
    {
        return $this->hasMany(ExternalSignatureToken::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ContractSignerReview::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ContractSignerSignature::class);
    }
}
