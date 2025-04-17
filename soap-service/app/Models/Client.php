<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'document',
        'name',
        'email',
        'phone',
        'balance',
    ];

    /**
     * Get the transactions for the client.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
} 