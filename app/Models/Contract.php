<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'contract_number',
        'external_contract_number',
        'title',
        'paper_size',
        'start_date',
        'end_date',
        'status',
        'template_id',
        'created_by',
        'signed_document_path',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
    ];

    //  Relations

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function childContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'parent_contract_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ContractVersion::class)->latestOfMany('version_number');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(ContractSigner::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(ContractParty::class);
    }

    public function addendums(): HasMany
    {
        return $this->hasMany(ContractAddendum::class);
    }

    public function termination(): HasOne
    {
        return $this->hasOne(ContractTermination::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ContractStatusLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    protected static function booted()
    {
        static::updated(function ($contract) {
            if ($contract->isDirty('status')) {
                ContractStatusLog::create([
                    'contract_id' => $contract->id,
                    'old_status' => $contract->getOriginal('status'),
                    'new_status' => $contract->status,
                    'changed_by' => Auth::id() ?? $contract->created_by,
                ]);
            }
        });
    }
}
