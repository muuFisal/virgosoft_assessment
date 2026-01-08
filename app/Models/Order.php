<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_FILLED = 'filled';
    public const STATUS_CANCELLED = 'cancelled';

    public const SIDE_BUY = 'buy';
    public const SIDE_SELL = 'sell';

    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'locked_usd',
        'locked_asset',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'amount' => 'decimal:8',
        'locked_usd' => 'decimal:2',
        'locked_asset' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
