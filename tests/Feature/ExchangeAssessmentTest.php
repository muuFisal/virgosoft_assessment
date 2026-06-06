<?php

namespace Tests\Feature;

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExchangeAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication_and_returns_usd_and_assets(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();

        $user = $this->createUser(balance: 1200, lockedBalance: 75);
        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => 0.25,
            'locked_amount' => 0.05,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.usd.balance', 1200)
            ->assertJsonPath('data.usd.locked_balance', 75)
            ->assertJsonPath('data.assets.0.symbol', 'BTC');
    }

    public function test_buy_order_reserves_usd_value_and_commission_then_appears_in_orderbook(): void
    {
        $buyer = $this->createUser(balance: 2000);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000,
            'amount' => 0.01,
        ])
            ->assertCreated()
            ->assertJsonPath('data.order.status', Order::STATUS_OPEN)
            ->assertJsonPath('data.order.locked_usd', '1015.00');

        $buyer->refresh();
        $this->assertSame('985.00', $buyer->balance);
        $this->assertSame('1015.00', $buyer->locked_balance);

        $this->getJson('/api/orders?symbol=BTC')
            ->assertOk()
            ->assertJsonPath('data.buy.0.side', Order::SIDE_BUY)
            ->assertJsonPath('data.buy.0.status', Order::STATUS_OPEN)
            ->assertJsonPath('data.sell', []);
    }

    public function test_sell_order_moves_asset_amount_to_locked_amount(): void
    {
        $seller = $this->createUser();
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'ETH',
            'amount' => 2,
            'locked_amount' => 0,
        ]);

        Sanctum::actingAs($seller);

        $this->postJson('/api/orders', [
            'symbol' => 'ETH',
            'side' => 'sell',
            'price' => 3000,
            'amount' => 0.5,
        ])->assertCreated();

        $asset = Asset::where('user_id', $seller->id)->where('symbol', 'ETH')->firstOrFail();
        $this->assertSame('1.50000000', $asset->amount);
        $this->assertSame('0.50000000', $asset->locked_amount);
    }

    public function test_full_match_settles_balances_assets_commission_trade_and_events_atomically(): void
    {
        Event::fake([OrderMatched::class]);

        $seller = $this->createUser();
        $buyer = $this->createUser(balance: 2000);
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 1,
            'locked_amount' => 0,
        ]);

        Sanctum::actingAs($seller);
        $sellResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000,
            'amount' => 0.01,
        ])->assertCreated();

        Sanctum::actingAs($buyer);
        $buyResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000,
            'amount' => 0.01,
        ])
            ->assertCreated()
            ->assertJsonPath('data.match.usd_value', 950)
            ->assertJsonPath('data.match.fee_usd', 14.25);

        $sellOrderId = $sellResponse->json('data.order.id');
        $buyOrderId = $buyResponse->json('data.order.id');

        $this->assertDatabaseHas('orders', ['id' => $sellOrderId, 'status' => Order::STATUS_FILLED]);
        $this->assertDatabaseHas('orders', ['id' => $buyOrderId, 'status' => Order::STATUS_FILLED]);
        $this->assertDatabaseHas('trades', [
            'buy_order_id' => $buyOrderId,
            'sell_order_id' => $sellOrderId,
            'symbol' => 'BTC',
            'usd_value' => 950,
            'fee_usd' => 14.25,
        ]);

        $buyer->refresh();
        $seller->refresh();
        $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->firstOrFail();
        $sellerAsset = Asset::where('user_id', $seller->id)->where('symbol', 'BTC')->firstOrFail();

        $this->assertSame('1035.75', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);
        $this->assertSame('0.01000000', $buyerAsset->amount);
        $this->assertSame('950.00', $seller->balance);
        $this->assertSame('0.99000000', $sellerAsset->amount);
        $this->assertSame('0.00000000', $sellerAsset->locked_amount);
        $this->assertSame(1, Trade::count());

        Event::assertDispatched(OrderMatched::class, 2);
    }

    public function test_partial_amounts_do_not_match_and_keep_orders_open(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser(balance: 5000);
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 1,
            'locked_amount' => 0,
        ]);

        Sanctum::actingAs($seller);
        $sellResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 90000,
            'amount' => 0.02,
        ])->assertCreated();

        Sanctum::actingAs($buyer);
        $buyResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 95000,
            'amount' => 0.01,
        ])->assertCreated();

        $this->assertDatabaseHas('orders', ['id' => $sellResponse->json('data.order.id'), 'status' => Order::STATUS_OPEN]);
        $this->assertDatabaseHas('orders', ['id' => $buyResponse->json('data.order.id'), 'status' => Order::STATUS_OPEN]);
        $this->assertSame(0, Trade::count());
    }

    public function test_cancel_releases_locked_usd_and_assets(): void
    {
        $buyer = $this->createUser(balance: 2000);
        Sanctum::actingAs($buyer);
        $buyOrderId = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000,
            'amount' => 0.01,
        ])->json('data.order.id');

        $this->postJson("/api/orders/{$buyOrderId}/cancel")->assertOk();
        $buyer->refresh();
        $this->assertSame('2000.00', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);
        $this->assertDatabaseHas('orders', ['id' => $buyOrderId, 'status' => Order::STATUS_CANCELLED]);

        $seller = $this->createUser();
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'ETH',
            'amount' => 2,
            'locked_amount' => 0,
        ]);
        Sanctum::actingAs($seller);
        $sellOrderId = $this->postJson('/api/orders', [
            'symbol' => 'ETH',
            'side' => 'sell',
            'price' => 3000,
            'amount' => 0.5,
        ])->json('data.order.id');

        $this->postJson("/api/orders/{$sellOrderId}/cancel")->assertOk();
        $asset = Asset::where('user_id', $seller->id)->where('symbol', 'ETH')->firstOrFail();
        $this->assertSame('2.00000000', $asset->amount);
        $this->assertSame('0.00000000', $asset->locked_amount);
        $this->assertDatabaseHas('orders', ['id' => $sellOrderId, 'status' => Order::STATUS_CANCELLED]);
    }

    private function createUser(float $balance = 0, float $lockedBalance = 0): User
    {
        return User::factory()->create([
            'status' => 1,
            'balance' => $balance,
            'locked_balance' => $lockedBalance,
        ]);
    }
}
