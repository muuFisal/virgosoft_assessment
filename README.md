# Limit-Order Exchange Mini Engine (Laravel + Vue)

This project implements the **VirgoSoft Technical Assessment** requirements:

- **Laravel 12 API** (Sanctum auth)
- **Controller → Service → Repository** pattern
- **Atomic (transactional) matching** with row locks
- **Full match only** (no partial fills)
- **Commission**: **1.5%** of executed USD value (**paid by BUYER**)
- **Real-time** updates via **Laravel Broadcasting + Pusher**
- **Vue 3 (Composition API) + Tailwind** UI (2 screens + simple login)

## Quick start

### 1) Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 2) Configure database

Set your DB credentials in `.env`, then:

```bash
php artisan migrate --seed
```

### 3) Configure Pusher

In `.env` set:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### 4) Run

```bash
php artisan serve
npm run dev
```

Open UI:

- `http://127.0.0.1:8000/exchange`

## Seeded users

After seeding:

- **Buyer**: `phone=1234567890`, `password=password`, `USD balance=10000`
- **Seller**: `phone=0987654321`, `password=password`, has `BTC=1.5` and `ETH=10`

## API endpoints (required)

All endpoints require `Authorization: Bearer <token>` (Sanctum).

- `GET /api/profile`
  - returns USD balance and asset balances
- `GET /api/orders?symbol=BTC`
  - returns authenticated user orders
- `GET /api/orders/orderbook?symbol=BTC`
  - returns open orders orderbook (buy & sell)
- `POST /api/orders`
  - creates a limit order
  - payload: `{ symbol: "BTC|ETH", side: "buy|sell", price: number, amount: number }`
- `POST /api/orders/{id}/cancel`
  - cancels open order and releases locked USD/asset

Optional test endpoint:

- `POST /api/orders/{id}/match`

## Matching rules (implemented)

- BUY matches first SELL where `sell.price <= buy.price` and amounts are equal
- SELL matches first BUY where `buy.price >= sell.price` and amounts are equal
- FIFO within the eligible counter orders (by `id`)

Execution price = counter (maker) price:

- incoming BUY executes at SELL price
- incoming SELL executes at BUY price

Fee = `usd_value * 0.015` (buyer pays). Seller receives full `usd_value`.

## Notes

- Matching is triggered automatically after placing an order.
- Broadcasting event: `OrderMatched` on private channel `user.{id}`.
