<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractSigner extends Model
{
    protected $table = 'contract_signers';

    protected $fillable = [
        'contract_id',
        'email',
        'user_id',
        'signer_type',
        'sequence',
        'reviewed_at',
        'sign_status',
        'signature_type',
        'signature_path',
        'signed_at',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
