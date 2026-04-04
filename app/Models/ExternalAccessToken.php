<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalAccessToken extends Model
{
    protected $table = 'external_access_tokens';

    protected $fillable = [
        'contract_signer_id',
        'token',
        'expired_at',
        'used_at',
    ];

    public function contractSigner()
    {
        return $this->belongsTo(contractSigner::class, 'contract_signer_id');
    }
}
