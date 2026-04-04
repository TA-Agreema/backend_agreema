<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractAddendumSigner extends Model
{
    protected $table = 'contract_addendum_signers';

    protected $fillable = [
        'contract_addendum_id',
        'signer_type',
        'signed_at',
        'signature_path',
        'signer_id',
    ];

    public function contractAddendum()
    {
        return $this->belongsTo(ContractAddendum::class, 'contract_addendum_id');
    }
}
