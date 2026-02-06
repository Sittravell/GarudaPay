<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['sender_account_id', 'recipient_account_id', 'amount', 'type', 'status'];

    public function sender()
    {
        return $this->belongsTo(Account::class, 'sender_account_id');
    }

    public function recipient()
    {
        return $this->belongsTo(Account::class, 'recipient_account_id');
    }
}
