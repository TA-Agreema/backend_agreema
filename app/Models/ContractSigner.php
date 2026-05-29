<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractSigner extends Model
{
    protected $fillable = [
        'contract_id',
        'party_id',
        'user_id',
        'external_email',
        'signer_type',
        'sequence',
        'review_status',
        'review_note',
        'reviewed_at',
        'sign_status',
        'signature_type',
        'signature_path',
        'signed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'signed_at'   => 'datetime',
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
