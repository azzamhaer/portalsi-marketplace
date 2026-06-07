<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'slug', 'username', 'username_changed_at',
        'city', 'latitude', 'longitude', 'full_address',
        'description', 'avatar', 'banner', 'ktp_image', 'verification_status', 'verification_note',
        'bank_name', 'bank_account', 'bank_holder',
        'rating', 'total_sold', 'followers', 'is_official', 'badge'
    ];

    protected $casts = [
        'is_official'         => 'boolean',
        'rating'              => 'float',
        'latitude'            => 'float',
        'longitude'           => 'float',
        'username_changed_at' => 'datetime',
    ];

    protected $hidden = ['ktp_image']; // jangan expose KTP di public response

    public function user(): BelongsTo               { return $this->belongsTo(User::class); }
    public function products(): HasMany             { return $this->hasMany(Product::class); }
    public function orderItems(): HasMany           { return $this->hasMany(OrderItem::class); }
    public function chatThreads(): HasMany          { return $this->hasMany(ChatThread::class); }
    public function withdrawals(): HasMany          { return $this->hasMany(Withdrawal::class); }
    public function followerRecords(): HasMany      { return $this->hasMany(VendorFollower::class); }

    public function followerUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vendor_followers')->withTimestamps();
    }
}
