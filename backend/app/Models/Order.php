<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'address_id',
        'subtotal', 'shipping', 'insurance', 'payment_fee', 'total',
        'courier_name', 'courier_eta', 'status', 'tracking_no', 'notes',
        'paid_at', 'shipped_at', 'done_at'
    ];

    protected $casts = [
        'paid_at'    => 'datetime',
        'shipped_at' => 'datetime',
        'done_at'    => 'datetime',
        'subtotal'   => 'integer',
        'shipping'   => 'integer',
        'insurance'  => 'integer',
        'payment_fee'=> 'integer',
        'total'      => 'integer',
    ];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function address(): BelongsTo  { return $this->belongsTo(Address::class); }
    public function items(): HasMany      { return $this->hasMany(OrderItem::class); }
    public function payment(): HasOne     { return $this->hasOne(Payment::class); }
}
