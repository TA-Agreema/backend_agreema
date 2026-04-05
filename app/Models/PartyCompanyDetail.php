<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyCompanyDetail extends Model
{
    protected $fillable = [
        'party_id',
        'company_name',
        'address',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
