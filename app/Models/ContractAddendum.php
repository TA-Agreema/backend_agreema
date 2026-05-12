<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAddendum extends Model
{
    protected $fillable = [
        'contract_id',
        'addendum_number',
        'title',
        'description',
        'document_path',
        'effective_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
