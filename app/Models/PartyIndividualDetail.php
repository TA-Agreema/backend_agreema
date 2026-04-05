<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyIndividualDetail extends Model
{
    protected $fillable = [
        'party_id',
        'full_name',
        'identity_number',
        'birth_place',
        'birth_date',
        'gender',
        'phone',
        'email',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
