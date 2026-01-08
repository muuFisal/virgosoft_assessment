<?php

namespace App\Services\Api\Exchange;

use App\Events\OrderMatched;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Api\Exchange\AssetRepository;
use App\Repositories\Api\Exchange\OrderRepository;
use App\Repositories\Api\Exchange\TradeRepository;
use Illuminate\Support\Facades\DB;

class MatchingService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected AssetRepository $assetRepository,
        protected TradeRepository $tradeRepository,
    ) {}

    /**
     * Matches a single order (FULL match only). Returns match info or null.
     */
    public function matchOne(int $incomingOrderId): ?array
    {
        return DB::transaction(function () use ($incomingOrderId) {
            /** @var Order|null $incoming */
            $incoming = Order::where('id', $incomingOrderId)
                ->where('status', Order::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if (! $incoming) {
                return null;
            }

            $counter = $this->orderRepository->firstCounterForUpdate($incoming);
            if (! $counter) {
                return null;
            }

            // Determine buyer/seller and execution price
            if ($incoming->side === Order::SIDE_BUY) {
                $buy = $incoming;
                $sell = $counter;
                $executionPrice = (float) $sell->price; // execute at counter price
            } else {
                $sell = $incoming;
                $buy = $counter;
                $executionPrice = (float) $buy->price;
            }

            $amount = (float) $buy->amount; // FULL match: amounts equal
            $usdValue = round($executionPrice * $amount, 2);
            $fee = round($usdValue * 0.015, 2); // buyer pays fee

            // Lock users and assets
            /** @var User $buyer */
            $buyer = User::where('id', $buy->user_id)->lockForUpdate()->firstOrFail();
            /** @var User $seller */
            $seller = User::where('id', $sell->user_id)->lockForUpdate()->firstOrFail();

            $buyerAsset = $this->assetRepository->getOrCreateForUpdate($buyer->id, $buy->symbol);
            $sellerAsset = $this->assetRepository->getOrCreateForUpdate($seller->id, $sell->symbol);

            // Settle BUY side funds
            $buyerLocked = (float) $buy->locked_usd;
            $buyerNeed = $usdValue + $fee;
            if ($buyerLocked + 0.0001 < $buyerNeed) {
                // Not enough reserved (shouldn't happen if order creation reserved correctly)
                return null;
            }

            // Decrease buyer locked balance by full reserved, then refund remainder
            $buyer->locked_balance = max(0, (float) $buyer->locked_balance - $buyerLocked);
            $refund = round($buyerLocked - $buyerNeed, 2);
            if ($refund > 0) {
                $buyer->balance = (float) $buyer->balance + $refund;
            }
            $buyer->save();

            // Credit buyer asset
            $buyerAsset->amount = (float) $buyerAsset->amount + $amount;
            $buyerAsset->save();

            // Release seller locked asset and credit seller USD
            $sellerAsset->locked_amount = max(0, (float) $sellerAsset->locked_amount - (float) $sell->locked_asset);
            $sellerAsset->save();

            $seller->balance = (float) $seller->balance + $usdValue;
            $seller->save();

            // Mark orders filled
            $buy->status = Order::STATUS_FILLED;
            $sell->status = Order::STATUS_FILLED;
            $buy->save();
            $sell->save();

            $trade = $this->tradeRepository->create([
                'buy_order_id' => $buy->id,
                'sell_order_id' => $sell->id,
                'symbol' => $buy->symbol,
                'price' => $executionPrice,
                'amount' => $amount,
                'usd_value' => $usdValue,
                'fee_usd' => $fee,
            ]);

            // Broadcast to both parties
            event(new OrderMatched($buyer->id, [
                'trade' => $trade,
                'orders' => ['buy' => $buy->id, 'sell' => $sell->id],
            ]));
            event(new OrderMatched($seller->id, [
                'trade' => $trade,
                'orders' => ['buy' => $buy->id, 'sell' => $sell->id],
            ]));

            return [
                'trade_id' => $trade->id,
                'usd_value' => $usdValue,
                'fee_usd' => $fee,
                'price' => $executionPrice,
                'amount' => $amount,
            ];
        });
    }
}
