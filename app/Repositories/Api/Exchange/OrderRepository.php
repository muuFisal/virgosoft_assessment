<?php

namespace App\Repositories\Api\Exchange;

use App\Models\Order;

class OrderRepository
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function findOpenForUpdate(int $orderId, int $userId): ?Order
    {
        return Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->where('status', Order::STATUS_OPEN)
            ->lockForUpdate()
            ->first();
    }

    public function getOrderbook(string $symbol): array
    {
        $buys = Order::where('symbol', $symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('side', Order::SIDE_BUY)
            ->orderByDesc('price')
            ->orderBy('id')
            ->get();

        $sells = Order::where('symbol', $symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('side', Order::SIDE_SELL)
            ->orderBy('price')
            ->orderBy('id')
            ->get();

        return ['buy' => $buys, 'sell' => $sells];
    }

    public function listByUser(int $userId, ?string $symbol = null)
    {
        $q = Order::where('user_id', $userId)->latest('id');
        if ($symbol) {
            $q->where('symbol', $symbol);
        }
        return $q->paginate(20);
    }

    /**
     * Find the first valid counter order for matching (FULL match only).
     */
    public function firstCounterForUpdate(Order $incoming): ?Order
    {
        if ($incoming->side === Order::SIDE_BUY) {
            return Order::where('symbol', $incoming->symbol)
                ->where('status', Order::STATUS_OPEN)
                ->where('side', Order::SIDE_SELL)
                ->where('amount', $incoming->amount) // FULL match only
                ->where('price', '<=', $incoming->price)
                ->orderBy('price')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
        }

        return Order::where('symbol', $incoming->symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('side', Order::SIDE_BUY)
            ->where('amount', $incoming->amount) // FULL match only
            ->where('price', '>=', $incoming->price)
            ->orderByDesc('price')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
