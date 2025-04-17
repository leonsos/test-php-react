<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'type',
        'amount',
        'session_id',
        'token',
        'status',
    ];

    /**
     * Get the client that owns the transaction.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
} 