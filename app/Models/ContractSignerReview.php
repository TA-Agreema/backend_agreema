<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSignerReview extends Model
{
    protected $fillable = [
        'contract_signer_id',
        'contract_version_id',
        'iteration',
        'status',
        'notes',
        'review_document_path',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function contractSigner(): BelongsTo
    {
        return $this->belongsTo(ContractSigner::class);
    }

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }
}
