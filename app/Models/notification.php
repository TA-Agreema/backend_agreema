<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'recipient_id',
        'recipient_type',
        'contract_id',
        'message',
        'is_read',
        'created_at',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
}
