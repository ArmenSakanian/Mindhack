<template>
  <section class="category-products">
    <div class="container">
      <!-- Заголовок категории -->
      <header class="head">
        <h2>{{ headerTitle }}</h2>
        <p class="sub">
          Готовые мини-системы и шаблоны внутри категории. Выберите продукт —
          внутри описания, пресеты и готовые отчёты.
        </p>
      </header>

      <!-- Состояния загрузки / ошибки -->
      <div v-if="loading" class="state">Загрузка…</div>
      <div v-else-if="error" class="state error">
        {{ error }} <br />
        <button class="btn-primary" @click="load()">Повторить</button>
      </div>

      <!-- Контент -->
      <template v-else>
        <!-- Список продуктов -->
        <div v-if="products.length" class="list">
          <article
            class="product-card pop-in"
            v-for="p in products"
            :key="p.id"
          >
            <!-- Левая половина: текст -->
            <div class="content">
              <div class="eyebrow" v-if="p.eyebrow">{{ p.eyebrow }}</div>
              <h3 class="title">{{ p.title }}</h3>
              <p class="tagline" v-if="p.tagline">{{ p.tagline }}</p>

              <ul class="features" v-if="p.features?.length">
                <li v-for="f in p.features" :key="f">
                  <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                  {{ f }}
                </li>
              </ul>

              <div class="meta">
                <div class="price" v-if="p.price != null">
                {{ formatPrice(p.price) }}
                </div>

                <!-- Кнопки действий -->
                <div class="actions">
                  <!-- Нет в корзине -->
                  <button
                    v-if="!inCart(p.id)"
                    class="btn-primary"
                    @click="addToCart(p)"
                  >
                    <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
                    Добавить в корзину
                  </button>

                  <!-- Уже в корзине -->
                  <template v-else>
                    <button class="btn-outline" @click="goToCart">
                      <i class="fa-solid fa-check" aria-hidden="true"></i>
                      В корзине
                    </button>
                    <button class="btn-ghost" @click="removeFromCart(p)">
                      <i class="fa-solid fa-trash" aria-hidden="true"></i>
                      <span class="sr-only">Удалить из корзины</span>
                    </button>
                  </template>
                </div>
              </div>
            </div>

            <!-- Правая половина: изображение -->
            <div class="media">
              <img
                :src="p.image_url || p.image || defaultImg"
                :alt="p.title"
                loading="lazy"
              />
              <div class="glow"></div>
            </div>
          </article>
        </div>

        <!-- Пусто -->
        <div v-else class="state">В этой категории пока нет продуктов.</div>
      </template>
    </div>

    <!-- Нотификация о добавлении / удалении -->
    <div v-if="toast" class="toast">{{ toast }}</div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

/* ===== Проп (на случай встраивания без роутера) ===== */
const props = defineProps({
  categoryId: {
    type: [String, Number],
    default: null,
  },
});

const route = useRoute();
const router = useRouter();

/** ===== Заголовок / состояние ===== */
const categoryTitle = ref("");
const loading = ref(false);
const error = ref("");
const toast = ref(""); // краткая нотификация

/** ===== Данные продуктов из API ===== */
const products = ref([]);

/** ===== Фолбэк-картинка ===== */
const defaultImg =
  "data:image/svg+xml;utf8," +
  encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='1200' height='800'>
  <defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>
  <stop offset='0' stop-color='#1b1230'/><stop offset='1' stop-color='#2a1545'/></linearGradient></defs>
  <rect width='100%' height='100%' fill='url(#g)'/>
  <circle cx='140' cy='-40' r='260' fill='rgba(255,153,0,0.12)'/>
  <circle cx='1100' cy='80' r='220' fill='rgba(135,77,255,0.12)'/>
  </svg>`);

/** ===== Хелперы ===== */
function normalizeProduct(raw) {
  return {
    id: raw.id,
    eyebrow: raw.eyebrow || "",
    title: raw.title,
    tagline: raw.tagline || "",
    features: Array.isArray(raw.features) ? raw.features : [],
    price: raw.price != null ? Number(raw.price) : null,
    image_url: raw.image_url || raw.image || "",
  };
}

function formatPrice(val) {
  const n = Number(val);
  if (!Number.isFinite(n)) return val;
  return (
    new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 0 }).format(n) +
    " ₽"
  );
}

const headerTitle = computed(() => categoryTitle.value || "Каталог");

/** ===== Загрузка ===== */
async function load() {
  error.value = "";
  products.value = [];
  categoryTitle.value = "";

  const categoryId =
    route.params.id || route.query.category_id || props.categoryId;
  if (!categoryId) {
    error.value = "Не указан идентификатор категории в маршруте.";
    return;
  }

  loading.value = true;
  try {
    const url = `/php/products/list.php?category_id=${encodeURIComponent(
      categoryId
    )}&page=1&limit=100`;
    const res = await fetch(url, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    if (data.ok === false)
      throw new Error(data.message || "Сервер вернул ошибку");

    const list = Array.isArray(data.items) ? data.items : [];

    if (list.length && list[0].category_title) {
      categoryTitle.value = list[0].category_title;
    }

    products.value = list.map(normalizeProduct);
  } catch (e) {
    error.value = "Не удалось загрузить продукты. " + (e?.message || "");
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch(() => route.fullPath, load);

/** ====== Гостевая корзина (localStorage) ====== */
const STORAGE_KEY = "mh_cart_v1";

// Храним id товаров из корзины для быстрого чекера
const cartIds = ref(new Set());

function readCart() {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY));
    return Array.isArray(raw) ? raw : [];
  } catch {
    return [];
  }
}
function writeCart(arr) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
}
function refreshCartIds() {
  cartIds.value = new Set(readCart().map((i) => Number(i.id)));
}
function inCart(id) {
  return cartIds.value.has(Number(id));
}

function addToCart(p, qty = 1) {
  const cart = readCart();
  const id = Number(p.id);
  const idx = cart.findIndex((it) => Number(it.id) === id);

  if (idx >= 0) {
    // если уже есть — просто не трогаем количество (по требованию qty не нужен)
  } else {
    cart.push({
      id,
      title: p.title,
      price: Number(p.price) || 0,
      image_url: p.image_url || p.image || "",
      qty,
    });
  }

  writeCart(cart);
  refreshCartIds();
  broadcastCart(cart);
  showToast(`Добавлено в корзину: «${p.title}»`);
}

function removeFromCart(p) {
  const id = Number(p.id);
  const cart = readCart().filter((it) => Number(it.id) !== id);
  writeCart(cart);
  refreshCartIds();
  broadcastCart(cart);
  showToast(`Удалено из корзины: «${p.title}»`);
}

function broadcastCart(cart) {
  // кросс-вкладочный (часто не стреляет в той же вкладке, но ок)
  try {
    window.dispatchEvent(
      new StorageEvent("storage", {
        key: STORAGE_KEY,
        newValue: JSON.stringify(cart),
      })
    );
  } catch {}
  // наш кастомный
  window.dispatchEvent(
    new CustomEvent("mh:cart-updated", {
      detail: { count: cart.reduce((s, i) => s + (Number(i.qty) || 0), 0) },
    })
  );
}

// переход к корзине/оформлению
function goToCart() {
  // поменяй путь/роутер по необходимости
  router.push("/checkout");
}

/** ===== Простая нотификация ===== */
let toastTimer = null;
function showToast(text) {
  toast.value = text;
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toast.value = ""), 1800);
}

/** ===== Слушатели, чтобы кнопки сразу реагировали ===== */
function onAnyCartUpdate(e) {
  // не важно, что внутри — просто перечитываем состояние
  refreshCartIds();
}
onMounted(() => {
  refreshCartIds();
  window.addEventListener("mh:cart-updated", onAnyCartUpdate);
  window.addEventListener("storage", (e) => {
    if (e.key === STORAGE_KEY) refreshCartIds();
  });
});
onBeforeUnmount(() => {
  window.removeEventListener("mh:cart-updated", onAnyCartUpdate);
});
</script>

<style scoped>
/* ===== Фон страницы — такой же, как у категорий ===== */
.category-products {
  position: relative;
  min-height: 100vh;
  padding: 120px 24px 100px;
  color: #fff;
  background: radial-gradient(
      1200px 600px at 10% -10%,
      rgba(255, 153, 0, 0.08),
      transparent 60%
    ),
    radial-gradient(
      800px 400px at 90% 10%,
      rgba(135, 77, 255, 0.1),
      transparent 55%
    ),
    linear-gradient(1deg, #0f0b1a, #121b30 45%, #153345 70%, #183e5a);
  overflow: hidden;
}
.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* ===== Заголовок ===== */
.head {
  text-align: center;
  margin-bottom: 36px;
}
.head h2 {
  margin: 0 0 10px;
  font-size: 48px;
  color: var(--accent-color);
}
.sub {
  color: #cfc7de;
  font-size: 18px;
  max-width: 900px;
  margin: 0 auto;
}

/* ===== Состояния ===== */
.state {
  text-align: center;
  color: #d9d2eb;
  padding: 22px 0;
}
.state.error {
  color: #ffcfcc;
}

/* ===== Список карточек ===== */
.list {
  display: grid;
  gap: 22px;
}

/* ===== Карточка продукта (горизонтальная) ===== */
.product-card {
  display: grid;
  grid-template-columns: 1fr 1fr; /* слева текст, справа фото */
  align-items: stretch;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 22px;
  overflow: hidden;
  min-height: 360px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.28);
  transition: transform 0.22s ease, border-color 0.22s ease,
    box-shadow 0.22s ease, background 0.22s ease;
}
.product-card:hover {
  transform: translateY(-3px);
  border-color: rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.08);
  box-shadow: 0 18px 56px rgba(0, 0, 0, 0.34);
}

/* Левая половина (контент) */
.content {
  padding: 26px 40px 24px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.eyebrow {
  color: #cfc7de;
  font-size: 14px;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.title {
  margin: 0;
  font-size: 30px;
  font-weight: 900;
  color: var(--accent-color);
}
.tagline {
  margin: 2px 0 6px;
  color: #d9d2eb;
  font-size: 18px;
  line-height: 1.5;
}

.features {
  display: flex;
  flex-direction: column;
  gap: 20px 16px;
  list-style: none;
  padding: 0;
  margin: 4px 0 0;
}
.features li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #efe9ff;
  font-size: 15px;
}
.features i {
  color: var(--accent-color);
}

.meta {
  margin-top: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.price {
  font-weight: 900;
  font-size: 22px;
  color: var(--accent-color);
}

/* Кнопки действий */
.actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 14px;
  border: none;
  font-weight: 800;
  font-size: 16px;
  background: var(--accent-color);
  color: #1b1230;
  transition: transform 0.18s ease, background 0.2s ease, box-shadow 0.2s ease;
}
.btn-primary:hover {
  transform: translateY(-1px);
  background: #ffae33;
  box-shadow: 0 10px 26px rgba(0, 0, 0, 0.3);
}

.btn-outline {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 14px;
  font-weight: 800;
  font-size: 16px;
  border: 2px solid var(--accent-color);
  background: transparent;
  color: #ffcc80;
  transition: transform 0.18s ease, background 0.2s ease, border-color 0.2s ease;
}
.btn-outline:hover {
  transform: translateY(-1px);
  background: rgba(255, 153, 0, 0.12);
  border-color: #ffae33;
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.06);
  color: #ffd2a3;
  transition: transform 0.18s ease, background 0.2s ease, border-color 0.2s ease,
    color 0.2s ease;
}
.btn-ghost:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.28);
  color: #ffffff;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Правая половина (медиа) */
.media {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 320px;
}
.media img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.45s ease, opacity 0.3s ease;
}
.product-card:hover .media img {
  transform: scale(1.04);
}
.media .glow {
  position: absolute;
  inset: 0;
  background: radial-gradient(
    600px 240px at 90% 10%,
    rgba(135, 77, 255, 0.12),
    transparent 60%
  );
  pointer-events: none;
}

/* Анимации */
@keyframes popIn {
  from {
    opacity: 0;
    transform: translateY(6px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
.pop-in {
  animation: popIn 0.22s ease both;
}

/* Нотификация */
.toast {
  position: fixed;
  right: 16px;
  bottom: 16px;
  background: rgba(255, 153, 0, 0.95);
  color: #1b1230;
  padding: 12px 16px;
  border-radius: 12px;
  font-weight: 800;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
  z-index: 9999;
}

/* Адаптив */
@media (max-width: 980px) {
  .head h2 {
    font-size: 42px;
  }
  .sub {
    font-size: 17px;
  }
  .product-card {
    grid-template-columns: 1fr;
  }
  .media {
    order: -1;
    min-height: 220px;
  } /* на мобильных изображение сверху */
  .features {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 640px) {
  .title {
    font-size: 26px;
  }
  .tagline {
    font-size: 16px;
  }
  .price {
    font-size: 20px;
  }
}
</style>
