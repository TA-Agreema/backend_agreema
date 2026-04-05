<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Party extends Model
{
    protected $fillable = [
        'party_type', // individual | company
    ];

    public function individualDetail(): HasOne
    {
        return $this->hasOne(PartyIndividualDetail::class);
    }

    public function companyDetail(): HasOne
    {
        return $this->hasOne(PartyCompanyDetail::class);
    }

    public function contractParties(): HasMany
    {
        return $this->hasMany(ContractParty::class);
    }

    public function contractSigners(): HasMany
    {
        return $this->hasMany(ContractSigner::class);
    }

    /**
     * Helper accessor: return the name regardless of party type.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->party_type === 'individual') {
            return $this->individualDetail?->full_name ?? '-';
        }
        return $this->companyDetail?->company_name ?? '-';
    }
}
