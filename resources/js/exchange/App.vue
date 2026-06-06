<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

axios.defaults.baseURL = '/api';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

function setToken(t) {
  if (t) {
    localStorage.setItem('token', t);
    axios.defaults.headers.common['Authorization'] = `Bearer ${t}`;
    return;
  }

  localStorage.removeItem('token');
  delete axios.defaults.headers.common['Authorization'];
}

const existingToken = localStorage.getItem('token');
if (existingToken) setToken(existingToken);

const authPhone = ref('1234567890');
const authPassword = ref('password');
const loading = ref(false);
const error = ref('');
const message = ref('');
const token = ref(existingToken || '');

const symbol = ref('BTC');
const side = ref('buy');
const price = ref(65000);
const amount = ref(0.01);
const orderSideFilter = ref('');
const orderStatusFilter = ref('');

const profile = ref(null);
const orders = ref([]);
const orderbook = ref({ buy: [], sell: [] });
const trades = ref([]);

const isAuthed = computed(() => !!token.value);
const notional = computed(() => Number(price.value || 0) * Number(amount.value || 0));
const feePreview = computed(() => notional.value * 0.015);
const totalBuyReserve = computed(() => notional.value + feePreview.value);

let echo = null;
let subscribed = false;

const statusLabel = (status) => ({
  1: 'open',
  2: 'filled',
  3: 'cancelled',
  open: 'open',
  filled: 'filled',
  cancelled: 'cancelled',
}[status] || String(status));

const statusClass = (status) => {
  const label = statusLabel(status);
  if (label === 'open') return 'bg-yellow-900/40 text-yellow-200';
  if (label === 'filled') return 'bg-emerald-900/40 text-emerald-200';
  return 'bg-gray-800 text-gray-200';
};

const initEcho = () => {
  if (!import.meta.env.VITE_PUSHER_APP_KEY) return;

  try {
    window.Pusher = Pusher;
    echo = new Echo({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY,
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
      forceTLS: true,
      authEndpoint: '/broadcasting/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      },
    });
  } catch (_) {
    echo = null;
  }
};

const subscribeRealtime = () => {
  const uid = profile.value?.user_id;
  if (!echo || !uid || subscribed) return;

  subscribed = true;
  echo
    .private(`user.${uid}`)
    .listen('.OrderMatched', async (event) => {
      trades.value.unshift(event.trade || event.payload?.trade || event);
      trades.value = trades.value.slice(0, 8);
      message.value = 'Order matched in real time.';
      await refreshAll();
    });
};

const fetchProfile = async () => {
  const { data } = await axios.get('/profile');
  profile.value = data.data;
  subscribeRealtime();
};

const fetchOrders = async () => {
  const params = {
    symbol: symbol.value,
    side: orderSideFilter.value || undefined,
    status: orderStatusFilter.value || undefined,
  };
  const { data } = await axios.get('/my-orders', { params });
  orders.value = data.data || [];
};

const fetchOrderbook = async () => {
  const { data } = await axios.get('/orders', { params: { symbol: symbol.value } });
  orderbook.value = { buy: data.data.buy || [], sell: data.data.sell || [] };
};

const refreshAll = async () => {
  if (!isAuthed.value) return;
  await Promise.all([fetchProfile(), fetchOrders(), fetchOrderbook()]);
};

const login = async () => {
  loading.value = true;
  error.value = '';
  message.value = '';
  try {
    const { data } = await axios.post('/login', {
      phone: authPhone.value,
      password: authPassword.value,
    });
    const t = data?.data?.token;
    if (!t) throw new Error('No token returned');
    token.value = t;
    setToken(t);
    subscribed = false;
    initEcho();
    await refreshAll();
    message.value = 'Logged in.';
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Login failed';
  } finally {
    loading.value = false;
  }
};

const logout = () => {
  setToken(null);
  token.value = '';
  profile.value = null;
  orders.value = [];
  orderbook.value = { buy: [], sell: [] };
  trades.value = [];
  subscribed = false;
  try {
    echo?.disconnect();
  } catch (_) {}
};

const placeOrder = async () => {
  loading.value = true;
  error.value = '';
  message.value = '';
  try {
    const { data } = await axios.post('/orders', {
      symbol: symbol.value,
      side: side.value,
      price: Number(price.value),
      amount: Number(amount.value),
    });
    message.value = data?.data?.match ? 'Order placed and matched.' : 'Order placed.';
    await refreshAll();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to place order';
  } finally {
    loading.value = false;
  }
};

const cancelOrder = async (id) => {
  loading.value = true;
  error.value = '';
  try {
    await axios.post(`/orders/${id}/cancel`);
    message.value = 'Order cancelled.';
    await refreshAll();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to cancel';
  } finally {
    loading.value = false;
  }
};

watch([symbol, orderSideFilter, orderStatusFilter], async () => {
  if (!isAuthed.value) return;
  await refreshAll();
});

onMounted(async () => {
  if (isAuthed.value) {
    initEcho();
    await refreshAll();
  }
});
</script>

<template>
  <div class="mx-auto max-w-7xl p-6">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="text-2xl font-semibold">Limit-Order Exchange Mini Engine</div>
        <div class="text-sm text-gray-400">Laravel API, Vue 3 Composition API, Tailwind, Sanctum, Pusher</div>
      </div>
      <button
        v-if="isAuthed"
        @click="logout"
        class="rounded bg-gray-800 px-3 py-2 hover:bg-gray-700"
      >
        Logout
      </button>
    </header>

    <section v-if="!isAuthed" class="max-w-md rounded-lg border border-gray-800 bg-gray-900/60 p-5">
      <div class="mb-3 font-semibold">Login</div>
      <div class="space-y-3">
        <input
          v-model="authPhone"
          placeholder="phone"
          class="w-full rounded border border-gray-800 bg-gray-950 px-3 py-2"
        />
        <input
          v-model="authPassword"
          type="password"
          placeholder="password"
          class="w-full rounded border border-gray-800 bg-gray-950 px-3 py-2"
        />
        <button
          @click="login"
          :disabled="loading"
          class="w-full rounded bg-blue-600 px-3 py-2 hover:bg-blue-500 disabled:opacity-50"
        >
          Login
        </button>
      </div>
      <div v-if="error" class="mt-3 text-sm text-red-400">{{ error }}</div>
      <div class="mt-3 text-xs text-gray-400">
        Seeded accounts: Buyer 1234567890/password, Seller 0987654321/password.
      </div>
    </section>

    <main v-else class="grid grid-cols-1 gap-6 lg:grid-cols-[420px_1fr]">
      <section class="rounded-lg border border-gray-800 bg-gray-900/60 p-5">
        <div class="mb-3 font-semibold">Limit Order</div>

        <div class="grid grid-cols-2 gap-3">
          <label class="text-sm text-gray-300">
            Symbol
            <select v-model="symbol" class="mt-1 w-full rounded border border-gray-800 bg-gray-950 px-3 py-2">
              <option value="BTC">BTC</option>
              <option value="ETH">ETH</option>
            </select>
          </label>

          <label class="text-sm text-gray-300">
            Side
            <select v-model="side" class="mt-1 w-full rounded border border-gray-800 bg-gray-950 px-3 py-2">
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
            </select>
          </label>

          <label class="text-sm text-gray-300">
            Price USD
            <input v-model="price" type="number" step="0.01" class="mt-1 w-full rounded border border-gray-800 bg-gray-950 px-3 py-2" />
          </label>

          <label class="text-sm text-gray-300">
            Amount
            <input v-model="amount" type="number" step="0.00000001" class="mt-1 w-full rounded border border-gray-800 bg-gray-950 px-3 py-2" />
          </label>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2 text-xs text-gray-400">
          <div class="rounded border border-gray-800 bg-gray-950 p-2">
            <div>Volume</div>
            <div class="mt-1 text-gray-100">{{ notional.toFixed(2) }}</div>
          </div>
          <div class="rounded border border-gray-800 bg-gray-950 p-2">
            <div>Fee 1.5%</div>
            <div class="mt-1 text-gray-100">{{ feePreview.toFixed(2) }}</div>
          </div>
          <div class="rounded border border-gray-800 bg-gray-950 p-2">
            <div>Buy reserve</div>
            <div class="mt-1 text-gray-100">{{ totalBuyReserve.toFixed(2) }}</div>
          </div>
        </div>

        <button
          @click="placeOrder"
          :disabled="loading"
          class="mt-4 w-full rounded bg-emerald-600 px-3 py-2 hover:bg-emerald-500 disabled:opacity-50"
        >
          Place Order
        </button>

        <div v-if="message" class="mt-3 text-sm text-emerald-300">{{ message }}</div>
        <div v-if="error" class="mt-3 text-sm text-red-400">{{ error }}</div>
      </section>

      <section class="rounded-lg border border-gray-800 bg-gray-900/60 p-5">
        <div class="mb-3 font-semibold">Wallet Overview</div>
        <div v-if="profile" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div class="rounded border border-gray-800 bg-gray-950 p-3">
            <div class="text-sm text-gray-400">USD</div>
            <div class="text-lg font-semibold">{{ Number(profile.usd.balance).toFixed(2) }}</div>
            <div class="text-xs text-gray-500">Locked {{ Number(profile.usd.locked_balance).toFixed(2) }}</div>
          </div>

          <div
            v-for="a in profile.assets"
            :key="a.symbol"
            class="rounded border border-gray-800 bg-gray-950 p-3"
          >
            <div class="text-sm text-gray-400">{{ a.symbol }}</div>
            <div class="text-lg font-semibold">{{ Number(a.amount).toFixed(8) }}</div>
            <div class="text-xs text-gray-500">Locked {{ Number(a.locked_amount).toFixed(8) }}</div>
          </div>
        </div>
        <div v-else class="text-sm text-gray-400">Loading...</div>

        <div v-if="trades.length" class="mt-4">
          <div class="mb-2 text-sm text-gray-400">Recent real-time matches</div>
          <div class="space-y-2">
            <div
              v-for="(trade, idx) in trades"
              :key="trade.id || idx"
              class="rounded border border-gray-800 bg-gray-950 p-2 text-xs text-gray-300"
            >
              {{ trade.symbol }} @ {{ Number(trade.price || 0).toFixed(2) }}
              x {{ Number(trade.amount || 0).toFixed(8) }}
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-lg border border-gray-800 bg-gray-900/60 p-5 lg:col-span-2">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="font-semibold">Orders and Wallet Activity</div>
            <div class="text-xs text-gray-400">Full-match only, FIFO, buyer pays 1.5% commission</div>
          </div>
          <div class="flex gap-2">
            <select v-model="orderSideFilter" class="rounded border border-gray-800 bg-gray-950 px-2 py-2 text-sm">
              <option value="">All sides</option>
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
            </select>
            <select v-model="orderStatusFilter" class="rounded border border-gray-800 bg-gray-950 px-2 py-2 text-sm">
              <option value="">All statuses</option>
              <option value="1">Open</option>
              <option value="2">Filled</option>
              <option value="3">Cancelled</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400">
              <tr>
                <th class="py-2 text-left">ID</th>
                <th class="text-left">Symbol</th>
                <th class="text-left">Side</th>
                <th class="text-left">Price</th>
                <th class="text-left">Amount</th>
                <th class="text-left">Status</th>
                <th class="text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in orders" :key="o.id" class="border-t border-gray-800">
                <td class="py-2">#{{ o.id }}</td>
                <td>{{ o.symbol }}</td>
                <td>
                  <span :class="o.side === 'buy' ? 'text-emerald-300' : 'text-rose-300'">
                    {{ o.side.toUpperCase() }}
                  </span>
                </td>
                <td>{{ Number(o.price).toFixed(2) }}</td>
                <td>{{ Number(o.amount).toFixed(8) }}</td>
                <td>
                  <span class="rounded px-2 py-1 text-xs" :class="statusClass(o.status)">
                    {{ statusLabel(o.status) }}
                  </span>
                </td>
                <td class="text-right">
                  <button
                    v-if="statusLabel(o.status) === 'open'"
                    @click="cancelOrder(o.id)"
                    class="rounded bg-gray-800 px-3 py-1 hover:bg-gray-700"
                  >
                    Cancel
                  </button>
                </td>
              </tr>
              <tr v-if="!orders.length">
                <td colspan="7" class="py-5 text-center text-gray-500">No orders yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rounded-lg border border-gray-800 bg-gray-900/60 p-5 lg:col-span-2">
        <div class="mb-3 font-semibold">Orderbook ({{ symbol }})</div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <div class="mb-2 text-sm text-gray-400">BUY</div>
            <div class="space-y-2">
              <div
                v-for="b in orderbook.buy"
                :key="b.id"
                class="flex justify-between rounded border border-gray-800 bg-gray-950 p-2"
              >
                <span class="text-emerald-300">{{ Number(b.price).toFixed(2) }}</span>
                <span class="text-gray-200">{{ Number(b.amount).toFixed(8) }}</span>
              </div>
              <div v-if="!orderbook.buy.length" class="rounded border border-gray-800 bg-gray-950 p-2 text-sm text-gray-500">No buy orders.</div>
            </div>
          </div>

          <div>
            <div class="mb-2 text-sm text-gray-400">SELL</div>
            <div class="space-y-2">
              <div
                v-for="s in orderbook.sell"
                :key="s.id"
                class="flex justify-between rounded border border-gray-800 bg-gray-950 p-2"
              >
                <span class="text-rose-300">{{ Number(s.price).toFixed(2) }}</span>
                <span class="text-gray-200">{{ Number(s.amount).toFixed(8) }}</span>
              </div>
              <div v-if="!orderbook.sell.length" class="rounded border border-gray-800 bg-gray-950 p-2 text-sm text-gray-500">No sell orders.</div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
