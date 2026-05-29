<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSignerSignature extends Model
{
    public $timestamps = false;
    
	protected $fillable = [
		'contract_signer_id',
		'contract_version_id',
		'iteration',
		'signature_type',
		'signature_path',
		'ip_address',
		'user_agent',
		'signed_at',
	];

	protected $casts = [
		'signed_at' => 'datetime',
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
