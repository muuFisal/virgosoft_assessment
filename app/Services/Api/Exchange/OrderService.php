<?php

namespace App\Services\Api\Exchange;

use App\Models\Order;
use App\Models\User;
use App\Repositories\Api\Exchange\AssetRepository;
use App\Repositories\Api\Exchange\OrderRepository;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected AssetRepository $assetRepository,
        protected MatchingService $matchingService,
    ) {}

    /**
     * Creates a limit order and triggers matching.
     * Commission is paid by the BUYER (1.5% of executed USD value).
     */
    public function create(int $userId, array $data): array
    {
        $symbol = strtoupper((string) ($data['symbol'] ?? ''));
        $side = strtolower((string) ($data['side'] ?? ''));
        $price = (float) ($data['price'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);

        if (! in_array($symbol, ['BTC', 'ETH'], true)) {
            return ['status' => 422, 'message' => 'Invalid symbol', 'data' => []];
        }
        if (! in_array($side, [Order::SIDE_BUY, Order::SIDE_SELL], true)) {
            return ['status' => 422, 'message' => 'Invalid side', 'data' => []];
        }
        if ($price <= 0 || $amount <= 0) {
            return ['status' => 422, 'message' => 'Price and amount are required', 'data' => []];
        }

        $result = DB::transaction(function () use ($userId, $symbol, $side, $price, $amount) {
            /** @var User $user */
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();

            if ($side === Order::SIDE_BUY) {
                $usdValue = round($price * $amount, 2);
                $feeReserve = round($usdValue * 0.015, 2);
                $totalReserve = $usdValue + $feeReserve;

                if ((float) $user->balance < $totalReserve) {
                    return ['status' => 422, 'message' => 'Insufficient USD balance', 'data' => []];
                }

                $user->balance = (float) $user->balance - $totalReserve;
                $user->locked_balance = (float) $user->locked_balance + $totalReserve;
                $user->save();

                $order = $this->orderRepository->create([
                    'user_id' => $userId,
                    'symbol' => $symbol,
                    'side' => $side,
                    'price' => $price,
                    'amount' => $amount,
                    'locked_usd' => $totalReserve,
                    'status' => Order::STATUS_OPEN,
                ]);
            } else {
                $asset = $this->assetRepository->getOrCreateForUpdate($userId, $symbol);

                if ((float) $asset->amount < $amount) {
                    return ['status' => 422, 'message' => 'Insufficient asset amount', 'data' => []];
                }

                $asset->amount = (float) $asset->amount - $amount;
                $asset->locked_amount = (float) $asset->locked_amount + $amount;
                $asset->save();

                $order = $this->orderRepository->create([
                    'user_id' => $userId,
                    'symbol' => $symbol,
                    'side' => $side,
                    'price' => $price,
                    'amount' => $amount,
                    'locked_asset' => $amount,
                    'status' => Order::STATUS_OPEN,
                ]);
            }

            // Attempt to match immediately
            $match = $this->matchingService->matchOne($order->id);

            return ['status' => 201, 'message' => 'Order placed', 'data' => ['order' => $order, 'match' => $match]];
        });

        return $result;
    }

    public function cancel(int $userId, int $orderId): array
    {
        $result = DB::transaction(function () use ($userId, $orderId) {
            $order = $this->orderRepository->findOpenForUpdate($orderId, $userId);
            if (! $order) {
                return ['status' => 404, 'message' => 'Order not found', 'data' => []];
            }

            if ($order->side === Order::SIDE_BUY) {
                $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();
                $release = (float) $order->locked_usd;
                $user->locked_balance = max(0, (float) $user->locked_balance - $release);
                $user->balance = (float) $user->balance + $release;
                $user->save();
            } else {
                $asset = $this->assetRepository->getOrCreateForUpdate($userId, $order->symbol);
                $release = (float) $order->locked_asset;
                $asset->locked_amount = max(0, (float) $asset->locked_amount - $release);
                $asset->amount = (float) $asset->amount + $release;
                $asset->save();
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->save();

            return ['status' => 200, 'message' => 'Order cancelled', 'data' => ['order' => $order]];
        });

        return $result;
    }
}
