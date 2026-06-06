<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 1;
    public const STATUS_FILLED = 2;
    public const STATUS_CANCELLED = 3;

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

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'amount' => 'decimal:8',
            'locked_usd' => 'decimal:2',
            'locked_asset' => 'decimal:8',
            'status' => 'integer',
        ];
    }

    public static function normalizeStatus(int|string|null $status): ?int
    {
        return match ($status) {
            self::STATUS_OPEN, '1', 'open' => self::STATUS_OPEN,
            self::STATUS_FILLED, '2', 'filled' => self::STATUS_FILLED,
            self::STATUS_CANCELLED, '3', 'cancelled' => self::STATUS_CANCELLED,
            default => null,
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
