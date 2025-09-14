<!-- src/pages/CartPage.vue -->
<template>
  <section class="cart-page">
    <div class="container">
      <header class="head">
        <h2>Корзина</h2>
        <p class="sub">
          Проверьте состав заказа, измените количество и переходите к
          оформлению.
        </p>
      </header>

      <!-- Пустая корзина -->
      <div v-if="!items.length" class="empty">
        <div class="empty-icon" aria-hidden="true">
          <!-- большая SVG-иконка пустой корзины -->
          <svg viewBox="0 0 64 64" role="img">
            <path
              d="M6 10h6l5 26a6 6 0 0 0 5.9 4.9h24.8a6 6 0 0 0 5.9-4.9L56 22H19"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <circle cx="26" cy="54" r="4" fill="currentColor" />
            <circle cx="46" cy="54" r="4" fill="currentColor" />
            <path
              d="M22 10h30"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
            />
          </svg>
        </div>
        <p class="empty-text">Ваша корзина пуста.</p>
        <a class="btn-cta large" href="/">Продолжить покупки</a>

      </div>

      <!-- Таблица корзины -->
      <div v-else class="cart">
        <div class="cart-header">
          <span class="c1">Товар</span>
          <span class="c2">Цена</span>
          <span class="c3">Кол-во</span>
          <span class="c4">Сумма</span>
          <span class="c5"></span>
        </div>

        <div class="cart-row" v-for="it in items" :key="it.id">
          <div class="c1 prod">
            <img
              :src="it.image_url || defaultImg"
              :alt="it.title"
              loading="lazy"
            />
            <div class="meta">
              <div class="title">{{ it.title }}</div>
              <div class="subtle">ID: {{ it.id }}</div>
            </div>
          </div>

          <div class="c2 price">{{ fmt(it.price) }}</div>

          <div class="c3 qty">
            <button class="btn-qty" @click="dec(it)">−</button>
            <input
              class="qty-input"
              type="number"
              min="1"
              :value="it.qty"
              @input="set(it, $event.target.value)"
            />
            <button class="btn-qty" @click="inc(it)">+</button>
          </div>

          <div class="c4 line">{{ fmt(it.price * it.qty) }}</div>

          <div class="c5">
            <button
              class="btn-link danger no-underline"
              @click="removeItem(it.id)"
            >
              Удалить
            </button>
          </div>
        </div>

        <!-- Итоги -->
        <div class="summary">
          <div class="left">
            <button class="btn-link no-underline" @click="clearCart">
              Очистить корзину
            </button>
            <!-- УБРАЛ ссылку "Продолжить покупки" здесь по запросу -->
          </div>
          <div class="right">
            <div class="row">
              <span>Итого ({{ totalCount }} шт.)</span>
              <strong>{{ fmt(totalAmount) }}</strong>
            </div>
            <!-- CTA оформлена как кнопка с фоном FF9900 и без подчёркивания -->
            <a class="btn-cta" href="/checkout">Перейти к оформлению</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { reactive, computed, onMounted, onBeforeUnmount, watch } from "vue";

const STORAGE_KEY = "mh_cart_v1";

// фолбэк-картинка
const defaultImg =
  "data:image/svg+xml;utf8," +
  encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='600' height='400'>
  <defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>
  <stop offset='0' stop-color='#1b1230'/><stop offset='1' stop-color='#2a1545'/></linearGradient></defs>
  <rect width='100%' height='100%' fill='url(#g)'/>
  <circle cx='120' cy='-30' r='180' fill='rgba(255,153,0,0.12)'/>
  <circle cx='520' cy='60' r='160' fill='rgba(135,77,255,0.12)'/>
  </svg>`);

const state = reactive({ items: [] });

function load() {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY));
    state.items = Array.isArray(raw) ? raw.map(sanitize) : [];
  } catch {
    state.items = [];
  }
}
function save() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state.items));
}
function sanitize(x) {
  return {
    id: Number(x.id),
    title: String(x.title || ""),
    price: Number(x.price) || 0,
    image_url: x.image_url || x.image || "",
    qty: Math.max(1, Number(x.qty) || 1),
  };
}

onMounted(() => {
  load();
  window.addEventListener("storage", onStorage);
  window.addEventListener("mh:cart-updated", onCartUpdated);
});
onBeforeUnmount(() => {
  window.removeEventListener("storage", onStorage);
  window.removeEventListener("mh:cart-updated", onCartUpdated);
});
function onStorage(e) {
  if (e.key === STORAGE_KEY) load();
}
function onCartUpdated() {
  load();
}

watch(() => state.items, save, { deep: true });

function inc(it) {
  it.qty = Math.min(99, it.qty + 1);
}
function dec(it) {
  it.qty = Math.max(1, it.qty - 1);
}
function set(it, val) {
  const n = Math.max(1, Math.min(99, Number(val) || 1));
  it.qty = n;
}
function removeItem(id) {
  state.items = state.items.filter((i) => i.id !== id);
}
function clearCart() {
  state.items = [];
}

const totalCount = computed(() => state.items.reduce((s, i) => s + i.qty, 0));
const totalAmount = computed(() =>
  state.items.reduce((s, i) => s + i.qty * i.price, 0)
);

function fmt(n) {
  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 0,
  }).format(n || 0);
}

const items = computed(() => state.items);
</script>

<style scoped>
.cart-page {
  min-height: 100vh;
  position: relative;
  padding: 120px 24px 100px;
  color: #fff;
  background: radial-gradient(1200px 600px at 10% -10%,rgba(255,153,0,.08),transparent 60%),radial-gradient(800px 400px at 90% 10%,rgba(135,77,255,.1),transparent 55%),linear-gradient(1deg,#0f0b1a,#121b30 45%,#153345 70%,#183e5a);
  overflow: hidden;
}
.container {
  max-width: 1100px;
  margin: 0 auto;
}

.head {
  text-align: center;
  margin-bottom: 28px;
}
.head h2 {
  margin: 0 0 8px;
  font-size: 42px;
color: var(--accent-color);
}
.sub {
  color: #cfc7de;
  font-size: 22px;
}

/* --- Кнопки --- */
a,
a:visited {
  color: inherit;
} /* не даём браузеру красить ссылки */
.btn-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 14px 22px;
  border-radius: 14px;
  border: none;
  font-weight: 800;
  font-size: 16px;
  line-height: 1;
  background: var(--accent-color);
  color: #1b1230;
  text-decoration: none; /* БЕЗ подчёркивания */
  box-shadow: 0 8px 20px rgba(255, 153, 0, 0.25);
  transition: transform 0.18s ease, background 0.2s ease, box-shadow 0.2s ease;
}
.btn-cta:hover {
  transform: translateY(-1px);
  background: #ffae33;
  box-shadow: 0 12px 28px rgba(255, 153, 0, 0.32);
}
.btn-cta:active {
  transform: translateY(0);
}

.btn-cta.large {
  padding: 16px 26px;
  font-size: 18px;
  border-radius: 16px;
}

.btn-link {
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 700;
  color: var(--accent-color);
  text-decoration: none; /* убрали подчёркивание */
}
.btn-link:hover {
  opacity: 0.9;
}
.btn-link.no-underline:hover {
  text-decoration: none;
} /* явный запрет на подчёркивание */
.btn-link.danger {
  color: #941b0c;
}

/* Пустая корзина — крупнее всё */
.empty {
  text-align: center;
  padding: 40px 0 24px;
}
.empty-icon {
  width: 120px;
  height: 120px;
  margin: 0 auto 16px;
  color: var(--accent-color);
  opacity: 0.9;
}
.empty-icon svg {
  width: 100%;
  height: 100%;
  display: block;
}
.empty-text {
  font-size: 22px;
  color: #e9def7;
  margin: 0 0 16px;
}

/* Таблица корзины */
.cart {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cart-header,
.cart-row {
  display: grid;
  grid-template-columns: 1.2fr 0.5fr 0.6fr 0.6fr 0.2fr;
  gap: 16px;
  align-items: center;
}
.cart-header {
  padding: 12px 16px;
  color: #cfc7de;
  font-weight: 700;
  font-size: 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}
.cart-row {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 12px;
}

.prod {
  display: flex;
  align-items: center;
  gap: 12px;
}
.prod img {
  width: 96px;
  height: 72px;
  object-fit: cover;
  border-radius: 10px;
}
.meta .title {
  font-weight: 800;
  color: #ffe7c1;
}
.meta .subtle {
  color: #cfc7de;
  font-size: 12px;
}

.price,
.line {
  font-weight: 800;
  color: var(--accent-color);
}

.qty {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-qty {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
  font-size: 18px;
  font-weight: 900;
}
.qty-input {
  width: 56px;
  text-align: center;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
  padding: 6px 8px;
  font-weight: 700;
}

.summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-top: 10px;
  padding-top: 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
}
.summary .left {
  display: flex;
  gap: 16px;
  align-items: center;
}
.summary .right {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 320px;
}
.summary .row {
  display: flex;
  justify-content: space-between;
  gap: 24px;
  font-size: 18px;
}
.summary .row strong {
  font-size: 24px;
  color: var(--accent-color);
}

/* Мобильная адаптация */
@media (max-width: 820px) {
  .head h2 {
    font-size: 34px;
  }
  .empty {
    padding: 48px 0 28px;
  }
  .empty-icon {
    width: 140px;
    height: 140px;
  }
  .empty-text {
    font-size: 20px;
  }

  .cart-header {
    display: none;
  }
  .cart-row {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .c2,
  .c3,
  .c4,
  .c5 {
    display: flex;
    justify-content: space-between;
  }
  .c2::before {
    content: "Цена";
    color: #cfc7de;
  }
  .c3::before {
    content: "Кол-во";
    color: #cfc7de;
  }
  .c4::before {
    content: "Сумма";
    color: #cfc7de;
  }
  .summary {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
