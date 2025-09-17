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
      <div v-if="loading" class="state">
        <div class="skeleton-list" aria-hidden="true">
          <div class="skeleton-card" v-for="i in 3" :key="i">
            <div class="skeleton-media"></div>
            <div class="skeleton-lines">
              <div class="line w-70"></div>
              <div class="line w-50"></div>
              <div class="line w-90"></div>
              <div class="line w-40"></div>
            </div>
          </div>
        </div>
        <span class="sr-only">Загрузка…</span>
      </div>

      <div v-else-if="error" class="state error">
        {{ error }} <br />
        <button class="btn-primary" @click="load()">Повторить</button>
      </div>

      <!-- Контент -->
      <template v-else>
        <div v-if="products.length" class="list">
          <article
            class="product-card pop-in"
            v-for="p in products"
            :key="p.id"
          >
            <!-- Медиа слева (на десктопе) -->
            <div
              class="media"
              :data-pid="p.id"
              @mouseenter="onHover(true, p.id)"
              @mouseleave="onHover(false, p.id)"
              @touchstart.passive="onTouchStart($event, p.id)"
              @touchmove.passive="onTouchMove($event)"
              @touchend.passive="onTouchEnd(p)"
            >
              <img
                :src="currentSlideUrl(p)"
                :alt="p.title"
                loading="lazy"
                decoding="async"
                :class="imgAnimClass(p.id)"
              />
              <div class="glow"></div>

              <!-- Навигация по фото -->
              <button
                v-if="p.images.length > 1"
                class="nav left"
                aria-label="Предыдущее фото"
                @click="prev(p)"
              >
                ‹
              </button>
              <button
                v-if="p.images.length > 1"
                class="nav right"
                aria-label="Следующее фото"
                @click="next(p)"
              >
                ›
              </button>

              <div
                v-if="p.images.length > 1"
                class="dots"
                role="tablist"
                :aria-label="`Фото продукта ${p.title}`"
              >
                <button
                  v-for="(img, i) in p.images"
                  :key="i"
                  :class="['dot', { active: slideIndex.get(p.id) === i }]"
                  @click="goTo(p, i)"
                  role="tab"
                  :aria-selected="slideIndex.get(p.id) === i"
                  :aria-label="`Слайд ${i + 1}`"
                />
              </div>
            </div>

            <!-- Контент справа -->
            <div class="content">
              <div class="eyebrow" v-if="p.eyebrow">{{ p.eyebrow }}</div>

              <h3 class="title">
                <template v-if="colonize(p.title).has">
                  <strong class="colon-strong"
                    >{{ colonize(p.title).before }}:</strong
                  >
                  {{ " " + colonize(p.title).after }}
                </template>
                <template v-else>
                  {{ p.title }}
                </template>
              </h3>

              <p
                class="tagline"
                :class="{ full: isLong(p.tagline) }"
                v-if="p.tagline"
              >
                <template v-if="useColonHighlight(p.tagline)">
                  <span class="colon-strong"
                    >{{ colonize(p.tagline).before }}:</span
                  >
                  {{ " " + colonize(p.tagline).after }}
                </template>
                <template v-else>
                  {{ p.tagline }}
                </template>
              </p>

              <!-- Фичи — более плотная сетка на десктопе -->
              <ul class="features" v-if="p.features?.length">
                <li v-for="f in p.features" :key="f">
                  <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                  <template v-if="colonize(String(f)).has">
                    <span class="feature-line">
                      <strong class="colon-strong"
                        >{{ colonize(String(f)).before }}:</strong
                      >
                      {{ " " + colonize(String(f)).after }}
                    </span>
                  </template>
                  <template v-else>
                    <span class="feature-line">{{ f }}</span>
                  </template>
                </li>
              </ul>

              <!-- Футер карточки: цена + действия -->
              <div class="meta">
                <div class="price" v-if="p.price != null">
                  {{ formatPrice(p.price) }}
                </div>

                <div class="actions">
                  <button
                    v-if="!inCart(p.id)"
                    class="btn--primary btn"
                    @click="addToCart(p)"
                  >
                    <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
                    В корзину
                  </button>

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
          </article>
        </div>

        <!-- Пусто -->
        <div v-else class="state">В этой категории пока нет продуктов.</div>
      </template>
    </div>

    <!-- Нотификация -->
    <div v-if="toast" class="toast">{{ toast }}</div>
  </section>
</template>

<script setup lang="ts">
/* Твой скрипт оставлен без изменений: логика загрузки, слайдер, корзина и т.д. */
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

const props = defineProps({
  categoryId: { type: [String, Number], default: null },
});
const route = useRoute();
const router = useRouter();

const categoryTitle = ref("");
const loading = ref(false);
const error = ref("");
const toast = ref("");
const products = ref([]);

const slideIndex = ref(new Map());
const animSet = ref(new Set());
const animDir = ref(new Map());
const animPhase = ref(new Map());
const pausedSet = ref(new Set());
let autoplayTimer = null;

const defaultImg =
  "data:image/svg+xml;utf8," +
  encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='1200' height='800'>
  <defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>
  <stop offset='0' stop-color='#1b1230'/><stop offset='1' stop-color='#2a1545'/></linearGradient></defs>
  <rect width='100%' height='100%' fill='url(#g)'/>
  <circle cx='140' cy='-40' r='260' fill='rgba(255,153,0,0.12)'/>
  <circle cx='1100' cy='80' r='220' fill='rgba(135,77,255,0.12)'/>
  </svg>`);

function pickImages(raw) {
  const arr = Array.isArray(raw.images) ? raw.images : [];
  const withUrls = arr
    .map((img) => ({
      url: img.url_full || img.url || "",
      is_primary: Number(img.is_primary) || 0,
      sort: Number(img.sort) || 0,
      id: Number(img.id) || 0,
    }))
    .filter((x) => !!x.url);
  if (!withUrls.length) return [];
  return withUrls.sort(
    (a, b) => b.is_primary - a.is_primary || a.sort - b.sort || a.id - b.id
  );
}
function normalizeProduct(raw) {
  const images = pickImages(raw);
  if (!images.length) {
    const fallback = raw.image_url || raw.image || "";
    if (fallback) {
      return {
        id: raw.id,
        eyebrow: raw.eyebrow || "",
        title: raw.title,
        tagline: raw.tagline || "",
        features: Array.isArray(raw.features) ? raw.features : [],
        price: raw.price != null ? Number(raw.price) : null,
        images: [{ url: fallback, is_primary: 1, sort: 1, id: 0 }],
      };
    }
  }
  return {
    id: raw.id,
    eyebrow: raw.eyebrow || "",
    title: raw.title,
    tagline: raw.tagline || "",
    features: Array.isArray(raw.features) ? raw.features : [],
    price: raw.price != null ? Number(raw.price) : null,
    images,
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

function ensureIndex(pid) {
  if (!slideIndex.value.has(pid)) slideIndex.value.set(pid, 0);
}
function currentSlideUrl(p) {
  ensureIndex(p.id);
  const i = slideIndex.value.get(p.id) || 0;
  const arr = p.images && p.images.length ? p.images : [{ url: defaultImg }];
  const item = arr[(i + arr.length) % arr.length] || arr[0];
  return item?.url || defaultImg;
}
function animateSwitch(pid, dir, changeIndexFn) {
  if (pausedSet.value.has(pid)) {
    changeIndexFn();
    return;
  }
  animDir.value.set(pid, dir);
  animPhase.value.set(pid, "out");
  animSet.value.add(pid);
  setTimeout(() => {
    changeIndexFn();
    animPhase.value.set(pid, "in");
    setTimeout(() => {
      animSet.value.delete(pid);
      animPhase.value.set(pid, "");
    }, 220);
  }, 160);
}
function next(p) {
  const pid = p.id;
  ensureIndex(pid);
  const arr = p.images || [];
  if (arr.length < 2) return;
  animateSwitch(pid, "left", () => {
    slideIndex.value.set(pid, (slideIndex.value.get(pid) + 1) % arr.length);
  });
}
function prev(p) {
  const pid = p.id;
  ensureIndex(pid);
  const arr = p.images || [];
  if (arr.length < 2) return;
  animateSwitch(pid, "right", () => {
    const i = slideIndex.value.get(pid);
    slideIndex.value.set(pid, (i - 1 + arr.length) % arr.length);
  });
}
function goTo(p, i) {
  const pid = p.id;
  ensureIndex(pid);
  const arr = p.images || [];
  if (!arr.length) return;
  const cur = slideIndex.value.get(pid) || 0;
  const dir = i > cur ? "left" : "right";
  animateSwitch(pid, dir, () => {
    slideIndex.value.set(pid, Math.max(0, Math.min(i, arr.length - 1)));
  });
}
function imgAnimClass(pid) {
  const is = animSet.value.has(pid);
  const phase = animPhase.value.get(pid) || "";
  const dir = animDir.value.get(pid) || "left";
  return {
    sliding: is,
    "phase-out": is && phase === "out",
    "phase-in": is && phase === "in",
    "dir-left": is && dir === "left",
    "dir-right": is && dir === "right",
  };
}

/* Автоплей только для видимых карточек */
function startAutoplay() {
  stopAutoplay();
  autoplayTimer = setInterval(() => {
    const cards = document.querySelectorAll(".product-card");
    for (const p of products.value) {
      if (!p.images || p.images.length < 2) continue;
      // проверяем видимость карточки (50%+)
      const card =
        Array.from(cards).find((el) => el && el.__pid === p.id) || null;
      if (pausedSet.value.has(p.id)) continue;
      next(p);
    }
  }, 3000);
}
function stopAutoplay() {
  if (autoplayTimer) {
    clearInterval(autoplayTimer);
    autoplayTimer = null;
  }
}
function onHover(state, pid) {
  if (state) pausedSet.value.add(pid);
  else pausedSet.value.delete(pid);
}

/* Свайп */
const touch = { x: 0, y: 0, dx: 0, dy: 0, active: false, pid: null };
function onTouchStart(e, pid) {
  const t = e.changedTouches?.[0];
  if (!t) return;
  touch.x = t.clientX;
  touch.y = t.clientY;
  touch.dx = 0;
  touch.dy = 0;
  touch.active = true;
  touch.pid = pid;
  pausedSet.value.add(pid);
}
function onTouchMove(e) {
  if (!touch.active) return;
  const t = e.changedTouches?.[0];
  if (!t) return;
  touch.dx = t.clientX - touch.x;
  touch.dy = t.clientY - touch.y;
}
function onTouchEnd(p) {
  if (!touch.active || touch.pid !== p.id) return;
  const THRESH = 40;
  if (Math.abs(touch.dx) > Math.abs(touch.dy) && Math.abs(touch.dx) > THRESH) {
    if (touch.dx < 0) next(p);
    else prev(p);
  }
  pausedSet.value.delete(p.id);
  touch.active = false;
  touch.pid = null;
}

function colonize(s) {
  if (typeof s !== "string") return { before: "", after: "", has: false };
  const idx = s.indexOf(":");
  if (idx === -1) return { before: s, after: "", has: false };
  return {
    before: s.slice(0, idx).trim(),
    after: s.slice(idx + 1).trim(),
    has: true,
  };
}

function isLong(s) {
  return (s?.length || 0) > 140; // порог можно подстроить
}
function useColonHighlight(s) {
  const c = colonize(s);
  // Подсветку делаем только если есть ":" и текст не длинный
  return c.has && !isLong(s);
}

/* Загрузка */
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
    if (list.length && list[0].category_title)
      categoryTitle.value = list[0].category_title;
    const mapped = list.map(normalizeProduct);
    products.value = mapped;

    slideIndex.value = new Map(mapped.map((p) => [p.id, 0]));
    animSet.value.clear();
    animDir.value.clear();
    animPhase.value.clear();
    pausedSet.value.clear();
    startAutoplay();
  } catch (e) {
    error.value = "Не удалось загрузить продукты. " + (e?.message || "");
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch(() => route.fullPath, load);
onBeforeUnmount(stopAutoplay);

/* ===== Корзина (единый формат для CartPage.vue) ===== */
import { ref } from "vue";
import { useRouter } from "vue-router";

const CART_KEY = "cart:v1";

/** Читаем корзину в едином формате */
function getCart(): Array<{id:number; name:string; priceKopecks:number; image:string; qty:number}> {
  try {
    const raw = localStorage.getItem(CART_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr : [];
  } catch { return []; }
}
function setCart(arr: any[]) {
  localStorage.setItem(CART_KEY, JSON.stringify(arr));
  // уведомим другие вкладки/виджеты
  try {
    window.dispatchEvent(new StorageEvent("storage", { key: CART_KEY, newValue: JSON.stringify(arr) }));
  } catch {}
  window.dispatchEvent(new CustomEvent("mh:cart-updated", {
    detail: { count: arr.reduce((s, i) => s + (Number(i.qty)||0), 0) }
  }));
}

/** Множество id товаров, уже лежащих в корзине (для отображения состояния кнопок) */
const cartIds = ref<Set<number>>(new Set());
function refreshCartIds() {
  cartIds.value = new Set(getCart().map(i => Number(i.id)));
}
function inCart(id: number | string) {
  return cartIds.value.has(Number(id));
}

/** Текущий URL обложки для карточки (используем твой слайдер) */
function coverUrlFor(p: any): string {
  const url = currentSlideUrl(p);
  return url || defaultImg;
}

/** Добавление товара (инкремент qty, конвертация цены в копейки) */
function addToCart(p: any, qty = 1) {
  const cart = getCart();
  const id = Number(p.id);
  const priceKopecks = Math.round(Number(p.price || 0) * 100); // из ₽ в копейки
  const image = coverUrlFor(p);

  const i = cart.findIndex(it => Number(it.id) === id);
  if (i >= 0) {
    cart[i].qty = Math.min(999, Number(cart[i].qty || 1) + qty);
    // подстрахуем возможные старые поля
    cart[i].name = cart[i].name ?? p.title;
    cart[i].priceKopecks = cart[i].priceKopecks ?? priceKopecks;
    cart[i].image = cart[i].image ?? image;
  } else {
    cart.push({
      id,
      name: p.title,
      priceKopecks,
      image,
      qty: Math.max(1, qty)
    });
  }

  setCart(cart);
  refreshCartIds();
  showToast(`Добавлено в корзину: «${p.title}»`);
}

/** Удаление товара */
function removeFromCart(p: any) {
  const id = Number(p.id);
  const cart = getCart().filter(it => Number(it.id) !== id);
  setCart(cart);
  refreshCartIds();
  showToast(`Удалено из корзины: «${p.title}»`);
}

/** Переход к оформлению */
function goToCart() {
  router.push("/cart");
}

/** Toast */
import { ref as vueRef, onMounted, onBeforeUnmount } from "vue";
let toastTimer: number | null = null;
function showToast(text: string) {
  toast.value = text;
  if (toastTimer) clearTimeout(toastTimer as any);
  // @ts-ignore
  toastTimer = setTimeout(() => (toast.value = ""), 1800);
}

/** Слушатели, чтобы кнопки «В корзине» мгновенно обновлялись */
function onAnyCartUpdate() { refreshCartIds(); }
onMounted(() => {
  refreshCartIds();
  window.addEventListener("mh:cart-updated", onAnyCartUpdate);
  window.addEventListener("storage", e => { if (e.key === CART_KEY) refreshCartIds(); });
});
onBeforeUnmount(() => {
  window.removeEventListener("mh:cart-updated", onAnyCartUpdate);
});


</script>

<style scoped>
:root {
  /* здесь можно переопределить глобально через :root в проекте */
  --accent-color: #ff9900;
}

.category-products {
  position: relative;
  min-height: 100vh;
  padding: clamp(64px, 8vw, 120px) 24px 100px;
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

/* Заголовок */
.head {
  text-align: center;
  margin-bottom: 36px;
}
.head h2 {
  margin: 0 0 10px;
  font-size: clamp(28px, 4.2vw, 48px);
  letter-spacing: -0.02em;
  color: var(--accent-color);
}
.sub {
  color: #cfc7de;
  font-size: clamp(15px, 1.8vw, 18px);
  max-width: 900px;
  margin: 0 auto;
}

/* Состояния */
.state {
  text-align: center;
  color: #d9d2eb;
  padding: 6px 0 22px;
}
.state.error {
  color: #ffcfcc;
}

/* Скелетоны */
.skeleton-list {
  display: grid;
  gap: 22px;
}
.skeleton-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: stretch;
  border-radius: 22px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(8px);
}
.skeleton-media {
  aspect-ratio: 4/3;
  background: linear-gradient(90deg, #2a2a3a 0%, #34344a 50%, #2a2a3a 100%);
  animation: shimmer 1.4s infinite;
}
.skeleton-lines {
  padding: 24px;
  display: grid;
  gap: 12px;
}
.line {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #2a2a3a 0%, #34344a 50%, #2a2a3a 100%);
  animation: shimmer 1.4s infinite;
}
.w-70 {
  width: 70%;
}
.w-50 {
  width: 50%;
}
.w-90 {
  width: 90%;
}
.w-40 {
  width: 40%;
}
@keyframes shimmer {
  0% {
    background-position: -200px 0;
  }
  100% {
    background-position: 200px 0;
  }
}

/* Список карточек — плотная сетка, карточки горизонтальные */
.list {
  display: grid;
  gap: 22px;
}

/* Карточка */
.product-card {
  display: grid;
  grid-template-columns: minmax(320px, 1fr) 1fr;
  align-items: stretch;
  background: #11111999;
  border-radius: 22px;
  overflow: hidden;
  min-height: 360px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.28);
  transition: transform 0.22s ease, border-color 0.22s ease,
    box-shadow 0.22s ease, background 0.22s ease;
  backdrop-filter: blur(10px);
  will-change: transform;
}
.product-card:hover {
  transform: translateY(-3px);
  background: #00000099;
  box-shadow: 0 18px 56px rgba(0, 0, 0, 0.34);
}

/* Медиа */
.media {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 320px;
  aspect-ratio: 4/3; /* удерживаем пропорции */
  overflow: hidden;
}
.media img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.45s ease, opacity 0.24s ease;
}
.product-card:hover .media img {
  transform: scale(1.04);
}

/* Slide animations */
.media img.sliding.phase-out.dir-left {
  animation: slideOutLeft 0.16s ease both;
}
.media img.sliding.phase-in.dir-left {
  animation: slideInLeft 0.22s ease both;
}
.media img.sliding.phase-out.dir-right {
  animation: slideOutRight.16s ease both;
}
.media img.sliding.phase-in.dir-right {
  animation: slideInRight 0.22s ease both;
}

@keyframes slideOutLeft {
  from {
    opacity: 1;
    transform: translateX(0) scale(1);
  }
  to {
    opacity: 0;
    transform: translateX(-24px) scale(0.98);
  }
}
@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(24px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateX(0) scale(1);
  }
}
@keyframes slideOutRight {
  from {
    opacity: 1;
    transform: translateX(0) scale(1);
  }
  to {
    opacity: 0;
    transform: translateX(24px) scale(0.98);
  }
}
@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(-24px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateX(0) scale(1);
  }
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

/* Навигация */
.nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: rgba(0, 0, 0, 0.35);
  color: #fff;
  font-size: 24px;
  line-height: 1;
  display: grid;
  place-items: center;
  cursor: pointer;
  user-select: none;
  transition: background 0.18s ease, transform 0.1s ease,
    border-color 0.18s ease;
}
.nav:hover {
  background: rgba(255, 255, 255, 0.18);
  border-color: rgba(255, 255, 255, 0.45);
  transform: translateY(-50%) scale(1.03);
}
.nav.left {
  left: 10px;
}
.nav.right {
  right: 10px;
}

/* Точки */
.dots {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  bottom: 12px;
  display: flex;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.28);
  border: 1px solid rgba(255, 255, 255, 0.12);
}
.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.5);
  border: 1px solid rgba(0, 0, 0, 0.3);
  cursor: pointer;
  transition: transform 0.12s ease, background 0.12s ease;
}
.dot:hover {
  transform: scale(1.2);
}
.dot.active {
  background: #fff;
}

/* Контент */
.content {
  padding: clamp(18px, 2.2vw, 26px) clamp(18px, 2.8vw, 40px) 24px 24px;
  display: grid;
  grid-template-rows: auto auto 1fr auto;
  gap: 12px;
}
.eyebrow {
  color: #cfc7de;
  font-size: 13px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.9;
}
.title {
  margin: 0;
  font-size: clamp(22px, 2.2vw, 30px);
  font-weight: 900;
  color: var(--accent-color);
  line-height: 1.15;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.tagline {
  margin: 2px 0 4px;
  color: #d9d2eb;
  font-size: clamp(15px, 1.6vw, 18px);
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tagline.full {
  display: block;
  -webkit-line-clamp: unset;
  overflow: visible;
}

.list > .product-card:nth-child(even) .media {
  order: 2;
}
.list > .product-card:nth-child(even) .content {
  order: 1;
}

/* На мобиле всегда фото сверху, текст снизу (отключаем чередование) */
@media (max-width: 980px) {
  .list > .product-card:nth-child(even) .media {
    order: -1;
  }
  .list > .product-card:nth-child(even) .content {
    order: 2;
  }
}

/* Фичи: 2 колонки на десктопе, одна на мобиле */
.features {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 18px;
  list-style: none;
  padding: 0;
  margin: 6px 0 0;
}
.features li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  color: #efe9ff;
  font-size: 15px;
}
.features i {
  color: var(--accent-color);
  flex: 0 0 auto;
  margin-top: 2px;
}
.feature-line {
  display: inline;
  white-space: normal;
  line-height: 1.4;
}
.colon-strong {
  font-weight: 800;
}

/* Метаданные/действия – прилипающий футер внутри карточки */
.meta {
  margin-top: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.price {
  font-weight: 900;
  font-size: clamp(18px, 1.8vw, 22px);
  color: var(--accent-color);
}

/* Кнопки */
.actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-outline,
.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: 12px;
  font-weight: 800;
}

.btn-outline {
  border: 2px solid var(--accent-color);
  background: transparent;
  color: var(--accent-color);
  transition: transform 0.18s ease, background 0.2s ease, border-color 0.2s ease;
}
.btn-outline:hover {
  transform: translateY(-1px);
  background: rgba(255, 153, 0, 0.12);
  border-color: #ffae33;
}

.btn-ghost {
  justify-content: center;
  width: 44px;
  height: 44px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.06);
  color: #941b0c;
  transition: transform 0.18s ease, background 0.2s ease, border-color 0.2s ease,
    color 0.2s ease;
}
.btn-ghost:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.28);
  color: #fff;
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

/* Анимация появления карточек */
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

/* Toast */
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
@media (max-width: 1100px) {
  .product-card {
    grid-template-columns: 1fr 1.1fr;
  }
}
@media (max-width: 980px) {
  .product-card {
    grid-template-columns: 1fr;
  }
  .media {
    order: -1;
    min-height: 220px;
    aspect-ratio: 16/9;
  }
  .features {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 640px) {
  .actions {
    width: 100%;
    justify-content: flex-end;
  }
  .btn-primary,
  .btn-outline {
    padding: 10px 14px;
    font-size: 15px;
  }
}

/* Accessibility: уважаем prefers-reduced-motion */
@media (prefers-reduced-motion: reduce) {
  * {
    animation: none !important;
    transition: none !important;
  }
}
</style>
