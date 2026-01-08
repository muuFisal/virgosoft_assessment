<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Axios defaults
axios.defaults.baseURL = '/api';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

function setToken(t) {
  if (t) {
    localStorage.setItem('token', t);
    axios.defaults.headers.common['Authorization'] = `Bearer ${t}`;
  } else {
    localStorage.removeItem('token');
    delete axios.defaults.headers.common['Authorization'];
  }
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

const profile = ref(null);
const orders = ref([]);
const orderbook = ref({ buy: [], sell: [] });

const isAuthed = computed(() => !!token.value);

let echo = null;
let subscribed = false;

const initEcho = () => {
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
    // Optional: app should still work without realtime
    echo = null;
  }
};

const fetchProfile = async () => {
  const { data } = await axios.get('/profile');
  profile.value = data.data;

  // Subscribe after we know user id
  const uid = profile.value?.user_id;
  if (echo && uid && !subscribed) {
    subscribed = true;
    echo
      .private(`user.${uid}`)
      .listen('.OrderMatched', async () => {
        await refreshAll();
      });
  }
};

const fetchOrders = async () => {
  const { data } = await axios.get('/orders', { params: { symbol: symbol.value } });
  orders.value = data.data || [];
};

const fetchOrderbook = async () => {
  const { data } = await axios.get('/orders/orderbook', { params: { symbol: symbol.value } });
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
    await axios.post('/orders', {
      symbol: symbol.value,
      side: side.value,
      price: Number(price.value),
      amount: Number(amount.value),
    });
    message.value = 'Order placed.';
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
    await refreshAll();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to cancel';
  } finally {
    loading.value = false;
  }
};

watch(symbol, async () => {
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
  <div class="max-w-6xl mx-auto p-6">
    <div class="flex items-center justify-between gap-4 mb-6">
      <div>
        <div class="text-2xl font-semibold">Limit-Order Exchange Mini Engine</div>
        <div class="text-gray-400 text-sm">Vue 3 + Tailwind + Laravel API + Pusher</div>
      </div>
      <button
        v-if="isAuthed"
        @click="logout"
        class="px-3 py-2 rounded bg-gray-800 hover:bg-gray-700"
      >
        Logout
      </button>
    </div>

    <div v-if="!isAuthed" class="max-w-md bg-gray-900/60 border border-gray-800 rounded-xl p-5">
      <div class="font-semibold mb-3">Login</div>
      <div class="space-y-3">
        <input
          v-model="authPhone"
          placeholder="phone"
          class="w-full px-3 py-2 rounded bg-gray-950 border border-gray-800"
        />
        <input
          v-model="authPassword"
          type="password"
          placeholder="password"
          class="w-full px-3 py-2 rounded bg-gray-950 border border-gray-800"
        />
        <button
          @click="login"
          :disabled="loading"
          class="w-full px-3 py-2 rounded bg-blue-600 hover:bg-blue-500 disabled:opacity-50"
        >
          Login
        </button>
      </div>
      <div v-if="error" class="mt-3 text-red-400 text-sm">{{ error }}</div>
      <div class="mt-3 text-gray-400 text-xs">
        Seeded accounts: Buyer (1234567890/password) has USD, Seller (0987654321/password) has BTC/ETH.
      </div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-5">
        <div class="font-semibold mb-3">Limit Order</div>

        <div class="grid grid-cols-2 gap-3">
          <label class="text-sm text-gray-300">
            Symbol
            <select v-model="symbol" class="mt-1 w-full px-3 py-2 rounded bg-gray-950 border border-gray-800">
              <option value="BTC">BTC</option>
              <option value="ETH">ETH</option>
            </select>
          </label>

          <label class="text-sm text-gray-300">
            Side
            <select v-model="side" class="mt-1 w-full px-3 py-2 rounded bg-gray-950 border border-gray-800">
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
            </select>
          </label>

          <label class="text-sm text-gray-300">
            Price (USD)
            <input v-model="price" type="number" step="0.01" class="mt-1 w-full px-3 py-2 rounded bg-gray-950 border border-gray-800" />
          </label>

          <label class="text-sm text-gray-300">
            Amount
            <input v-model="amount" type="number" step="0.00000001" class="mt-1 w-full px-3 py-2 rounded bg-gray-950 border border-gray-800" />
          </label>
        </div>

        <button
          @click="placeOrder"
          :disabled="loading"
          class="mt-4 w-full px-3 py-2 rounded bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50"
        >
          Place Order
        </button>

        <div v-if="message" class="mt-3 text-emerald-300 text-sm">{{ message }}</div>
        <div v-if="error" class="mt-3 text-red-400 text-sm">{{ error }}</div>
      </div>

      <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-5">
        <div class="font-semibold mb-3">Wallet Overview</div>
        <div v-if="profile" class="space-y-3">
          <div class="bg-gray-950 border border-gray-800 rounded-lg p-3">
            <div class="text-sm text-gray-400">USD Balance</div>
            <div class="text-lg font-semibold">{{ profile.usd.balance.toFixed(2) }}</div>
            <div class="text-xs text-gray-500">Locked: {{ profile.usd.locked_balance.toFixed(2) }}</div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div
              v-for="a in profile.assets"
              :key="a.symbol"
              class="bg-gray-950 border border-gray-800 rounded-lg p-3"
            >
              <div class="text-sm text-gray-400">{{ a.symbol }}</div>
              <div class="text-lg font-semibold">{{ Number(a.amount).toFixed(8) }}</div>
              <div class="text-xs text-gray-500">Locked: {{ Number(a.locked_amount).toFixed(8) }}</div>
            </div>
          </div>
        </div>
        <div v-else class="text-gray-400 text-sm">Loading...</div>
      </div>

      <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-5 lg:col-span-2">
        <div class="flex items-center justify-between gap-4 mb-3">
          <div class="font-semibold">Orders ({{ symbol }})</div>
          <div class="text-xs text-gray-400">Full-match only · FIFO · Commission 1.5% (buyer pays)</div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400">
              <tr>
                <th class="text-left py-2">ID</th>
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
                <td>
                  <span :class="o.side === 'buy' ? 'text-emerald-300' : 'text-rose-300'">
                    {{ o.side.toUpperCase() }}
                  </span>
                </td>
                <td>{{ Number(o.price).toFixed(2) }}</td>
                <td>{{ Number(o.amount).toFixed(8) }}</td>
                <td>
                  <span
                    class="px-2 py-1 rounded text-xs"
                    :class="o.status === 'open' ? 'bg-yellow-900/40 text-yellow-200' : (o.status === 'filled' ? 'bg-emerald-900/40 text-emerald-200' : 'bg-gray-800 text-gray-200')"
                  >
                    {{ o.status }}
                  </span>
                </td>
                <td class="text-right">
                  <button
                    v-if="o.status === 'open'"
                    @click="cancelOrder(o.id)"
                    class="px-3 py-1 rounded bg-gray-800 hover:bg-gray-700"
                  >
                    Cancel
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-5 lg:col-span-2">
        <div class="font-semibold mb-3">Orderbook ({{ symbol }})</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-sm text-gray-400 mb-2">BUY</div>
            <div class="space-y-2">
              <div
                v-for="b in orderbook.buy"
                :key="b.id"
                class="flex justify-between bg-gray-950 border border-gray-800 rounded-lg p-2"
              >
                <span class="text-emerald-300">{{ Number(b.price).toFixed(2) }}</span>
                <span class="text-gray-200">{{ Number(b.amount).toFixed(8) }}</span>
              </div>
            </div>
          </div>

          <div>
            <div class="text-sm text-gray-400 mb-2">SELL</div>
            <div class="space-y-2">
              <div
                v-for="s in orderbook.sell"
                :key="s.id"
                class="flex justify-between bg-gray-950 border border-gray-800 rounded-lg p-2"
              >
                <span class="text-rose-300">{{ Number(s.price).toFixed(2) }}</span>
                <span class="text-gray-200">{{ Number(s.amount).toFixed(8) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
