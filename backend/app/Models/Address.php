<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    protected $fillable = ['user_id', 'recipient', 'phone', 'city', 'latitude', 'longitude', 'full_address', 'postal_code', 'is_default'];
    protected $casts = ['is_default' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function orders(): HasMany  { return $this->hasMany(Order::class); }
}
