# Limit-Order Exchange Mini Engine

VirgoSoft technical assessment implementation: a small limit-order exchange built with Laravel API, Vue 3 Composition API, Tailwind, Sanctum authentication, and Laravel Broadcasting/Pusher.

## What is included

- Laravel 12 API with Sanctum authentication.
- Required tables: `users`, `assets`, `orders`, and bonus `trades`.
- Numeric order statuses required by the assessment: `open=1`, `filled=2`, `cancelled=3`.
- Atomic balance/asset updates using database transactions and row locks.
- Full-match-only engine with FIFO counter-order selection.
- Buyer-paid commission: `1.5%` of matched USD value.
- Pusher broadcast event `OrderMatched` on private user channels.
- Vue/Tailwind UI with login/logout, limit order form, wallet overview, user orders, orderbook, filters, fee preview, and real-time refresh.
- Feature tests covering authentication, balances, asset locks, matching, commission, cancellation, and orderbook behavior.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure MySQL or PostgreSQL in `.env`, then run:

```bash
php artisan migrate --seed
```

Seeded demo users:

- Buyer: `1234567890` / `password`, starts with `10000.00` USD.
- Seller: `0987654321` / `password`, starts with `1.5 BTC` and `10 ETH`.

## Pusher

Set these values in `.env`:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-id
PUSHER_APP_KEY=your-key
PUSHER_APP_SECRET=your-secret
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

Laravel `PrivateChannel('user.{id}')` is delivered by Pusher as `private-user.{id}`. The frontend subscribes with Laravel Echo using `echo.private('user.{id}')`.

## Run

```bash
php artisan serve
npm run dev
```

Open:

```text
http://127.0.0.1:8000
```

## API

All exchange endpoints require:

```text
Authorization: Bearer <sanctum-token>
Accept: application/json
```

### Login

```http
POST /api/login
```

Payload:

```json
{
  "phone": "1234567890",
  "password": "password"
}
```

### Profile

```http
GET /api/profile
```

Returns the authenticated user's USD balance, locked USD, and asset balances.

### Orderbook

```http
GET /api/orders?symbol=BTC
```

Returns all open buy and sell orders for the selected symbol.

### User orders

```http
GET /api/my-orders?symbol=BTC&side=buy&status=1
```

Returns the authenticated user's past orders. Filters are optional.

### Place order

```http
POST /api/orders
```

Payload:

```json
{
  "symbol": "BTC",
  "side": "buy",
  "price": 95000,
  "amount": 0.01
}
```

### Cancel order

```http
POST /api/orders/{id}/cancel
```

Cancels an open order owned by the authenticated user and releases locked USD/assets.

## Matching and fees

- New BUY matches the first open SELL with the same symbol and amount where `sell.price <= buy.price`.
- New SELL matches the first open BUY with the same symbol and amount where `buy.price >= sell.price`.
- Partial fills are intentionally not performed.
- Execution price is the counter-order price.
- Buyer pays `1.5%` of matched USD value.
- BUY orders reserve `price * amount + 1.5% fee` up front. This keeps matching atomic and prevents a successful match from failing later because the buyer has no available USD for the fee.
- If a BUY executes below its limit price, unused locked USD is refunded.

## Tests

```bash
php artisan test
```

The test suite uses SQLite in-memory and does not require Pusher credentials.
