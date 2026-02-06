<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['user_id', 'name', 'balance', 'is_main'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'sender_account_id');
    }

    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'recipient_account_id');
    }
}
