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
          <article class="product-card pop-in" v-for="p in products" :key="p.id">
            <!-- Левая половина: текст -->
            <div class="content">
              <div class="eyebrow" v-if="p.eyebrow">{{ p.eyebrow }}</div>
            
              <!-- TITLE: 'до :' жирно + ':' , 'после :' обычным -->
              <!-- TITLE -->
<h3 class="title">
  <template v-if="colonize(p.title).has">
    <strong class="colon-strong">{{ colonize(p.title).before }}:</strong>
    {{ ' ' + colonize(p.title).after }}
  </template>
  <template v-else>
    {{ p.title }}
  </template>
</h3>

<!-- TAGLINE -->
<p class="tagline" v-if="p.tagline">
  <template v-if="colonize(p.tagline).has">
    <strong class="colon-strong">{{ colonize(p.tagline).before }}:</strong>
    {{ ' ' + colonize(p.tagline).after }}
  </template>
  <template v-else>
    {{ p.tagline }}
  </template>
</p>

<!-- FEATURES -->
<ul class="features" v-if="p.features?.length">
  <li v-for="f in p.features" :key="f">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    <template v-if="colonize(String(f)).has">
      <span class="feature-line">
        <strong class="colon-strong">{{ colonize(String(f)).before }}:</strong>
        {{ ' ' + colonize(String(f)).after }}
      </span>
    </template>
    <template v-else>
      <span class="feature-line">{{ f }}</span>
    </template>
  </li>
</ul>

              <div class="meta">
                <div class="price" v-if="p.price != null">
                  {{ formatPrice(p.price) }}
                </div>

                <!-- Кнопки действий -->
                <div class="actions">
                  <button v-if="!inCart(p.id)" class="btn-primary" @click="addToCart(p)">
                    <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
                    Добавить в корзину
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

            <!-- Правая половина: медиа/слайдер -->
            <div class="media" :data-pid="p.id" @mouseenter="onHover(true, p.id)" @mouseleave="onHover(false, p.id)"
              @touchstart.passive="onTouchStart($event, p.id)" @touchmove.passive="onTouchMove($event)"
              @touchend.passive="onTouchEnd(p)">
              <!-- Изображение (слайд) -->
              <img :src="currentSlideUrl(p)" :alt="p.title" loading="lazy" decoding="async" :class="imgAnimClass(p.id)" />
              <div class="glow"></div>

              <!-- Стрелки -->
              <button v-if="p.images.length > 1" class="nav left" aria-label="Предыдущее фото" @click="prev(p)">‹</button>
              <button v-if="p.images.length > 1" class="nav right" aria-label="Следующее фото" @click="next(p)">›</button>

              <!-- Точки -->
              <div v-if="p.images.length > 1" class="dots">
                <button v-for="(img, i) in p.images" :key="i" :class="['dot', { active: slideIndex.get(p.id) === i }]"
                  @click="goTo(p, i)" aria-label="Переключить слайд" />
              </div>
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
  categoryId: { type: [String, Number], default: null },
});
const route = useRoute();
const router = useRouter();

/** ===== Заголовок / состояние ===== */
const categoryTitle = ref("");
const loading = ref(false);
const error = ref("");
const toast = ref("");

/** ===== Данные продуктов из API ===== */
const products = ref([]);

/** ===== Слайдер состояния ===== */
const slideIndex = ref(new Map());           // Map<pid, idx>
const animSet = ref(new Set());            // кто сейчас анимируется
const animDir = ref(new Map());            // Map<pid, 'left'|'right'>
const animPhase = ref(new Map());            // Map<pid, 'out'|'in'|''>
const pausedSet = ref(new Set());            // пауза по ховеру/свайпу
let autoplayTimer = null;

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

/** ===== Хелперы нормализации ===== */
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
  return new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 0 }).format(n) + " ₽";
}
const headerTitle = computed(() => categoryTitle.value || "Каталог");

/** ===== Слайдер API ===== */
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
  // если карточка на паузе — просто сменить без анимации
  if (pausedSet.value.has(pid)) { changeIndexFn(); return; }

  animDir.value.set(pid, dir);
  animPhase.value.set(pid, 'out');
  animSet.value.add(pid);

  // фаза "уезжает"
  setTimeout(() => {
    changeIndexFn();                  // меняем слайд в середине
    animPhase.value.set(pid, 'in');   // фаза "приезжает"
    setTimeout(() => {
      animSet.value.delete(pid);
      animPhase.value.set(pid, '');
    }, 220); // длительность заезда
  }, 160);   // длительность выезда
}

function next(p) {
  const pid = p.id;
  ensureIndex(pid);
  const arr = p.images || [];
  if (arr.length < 2) return;
  animateSwitch(pid, 'left', () => {
    slideIndex.value.set(pid, (slideIndex.value.get(pid) + 1) % arr.length);
  });
}
function prev(p) {
  const pid = p.id;
  ensureIndex(pid);
  const arr = p.images || [];
  if (arr.length < 2) return;
  animateSwitch(pid, 'right', () => {
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
  const dir = i > cur ? 'left' : 'right';
  animateSwitch(pid, dir, () => {
    slideIndex.value.set(pid, Math.max(0, Math.min(i, arr.length - 1)));
  });
}

/** Классы для img по pid */
function imgAnimClass(pid) {
  const is = animSet.value.has(pid);
  const phase = animPhase.value.get(pid) || '';
  const dir = animDir.value.get(pid) || 'left';
  return {
    sliding: is,
    'phase-out': is && phase === 'out',
    'phase-in': is && phase === 'in',
    'dir-left': is && dir === 'left',
    'dir-right': is && dir === 'right',
  };
}

/** ===== Автоплей (3 сек), с паузой по ховеру/свайпу ===== */
function startAutoplay() {
  stopAutoplay();
  autoplayTimer = setInterval(() => {
    // двигаем только те карточки, которые не на паузе и имеют ≥2 фото
    for (const p of products.value) {
      if (!p.images || p.images.length < 2) continue;
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

/** ===== Свайп на мобилке (тоже ставит паузу) ===== */
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
  pausedSet.value.add(pid); // пауза пока жест активен
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
  const THRESH = 40; // px
  if (Math.abs(touch.dx) > Math.abs(touch.dy) && Math.abs(touch.dx) > THRESH) {
    if (touch.dx < 0) next(p); else prev(p);
  }
  pausedSet.value.delete(p.id);
  touch.active = false;
  touch.pid = null;
}

function colonize(s) {
  if (typeof s !== 'string') return { before: '', after: '', has: false }
  const idx = s.indexOf(':')
  if (idx === -1) return { before: s, after: '', has: false }
  return {
    before: s.slice(0, idx).trim(),
    after: s.slice(idx + 1).trim(),
    has: true,
  }
}

/** ===== Загрузка ===== */
async function load() {
  error.value = "";
  products.value = [];
  categoryTitle.value = "";

  const categoryId = route.params.id || route.query.category_id || props.categoryId;
  if (!categoryId) { error.value = "Не указан идентификатор категории в маршруте."; return; }

  loading.value = true;
  try {
    const url = `/php/products/list.php?category_id=${encodeURIComponent(categoryId)}&page=1&limit=100`;
    const res = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.ok === false) throw new Error(data.message || "Сервер вернул ошибку");

    const list = Array.isArray(data.items) ? data.items : [];
    if (list.length && list[0].category_title) categoryTitle.value = list[0].category_title;

    const mapped = list.map(normalizeProduct);
    products.value = mapped;

    // Индексы в 0, паузы/анимации очищаем, запускаем автоплей
    slideIndex.value = new Map(mapped.map((p) => [p.id, 0]));
    animSet.value.clear(); animDir.value.clear(); animPhase.value.clear(); pausedSet.value.clear();

    startAutoplay();
  } catch (e) {
    error.value = "Не удалось загрузить продукты. " + (e?.message || "");
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch(() => route.fullPath, load);
onBeforeUnmount(() => { stopAutoplay(); });

/** ===== Гостевая корзина (localStorage) ===== */
const STORAGE_KEY = "mh_cart_v1";
const cartIds = ref(new Set());
function readCart() { try { const raw = JSON.parse(localStorage.getItem(STORAGE_KEY)); return Array.isArray(raw) ? raw : []; } catch { return []; } }
function writeCart(arr) { localStorage.setItem(STORAGE_KEY, JSON.stringify(arr)); }
function refreshCartIds() { cartIds.value = new Set(readCart().map((i) => Number(i.id))); }
function inCart(id) { return cartIds.value.has(Number(id)); }

function addToCart(p, qty = 1) {
  const cart = readCart();
  const id = Number(p.id);
  const idx = cart.findIndex((it) => Number(it.id) === id);
  const cover = currentSlideUrl(p);
  if (idx < 0) cart.push({ id, title: p.title, price: Number(p.price) || 0, image_url: cover || defaultImg, qty });
  writeCart(cart); refreshCartIds(); broadcastCart(cart); showToast(`Добавлено в корзину: «${p.title}»`);
}
function removeFromCart(p) {
  const id = Number(p.id);
  const cart = readCart().filter((it) => Number(it.id) !== id);
  writeCart(cart); refreshCartIds(); broadcastCart(cart); showToast(`Удалено из корзины: «${p.title}»`);
}
function broadcastCart(cart) {
  try { window.dispatchEvent(new StorageEvent("storage", { key: STORAGE_KEY, newValue: JSON.stringify(cart) })); } catch { }
  window.dispatchEvent(new CustomEvent("mh:cart-updated", { detail: { count: cart.reduce((s, i) => s + (Number(i.qty) || 0), 0) } }));
}
function goToCart() { router.push("/checkout"); }

/** ===== Простая нотификация ===== */
let toastTimer = null;
function showToast(text) { toast.value = text; if (toastTimer) clearTimeout(toastTimer); toastTimer = setTimeout(() => (toast.value = ""), 1800); }

/** ===== Слушатели корзины ===== */
function onAnyCartUpdate() { refreshCartIds(); }
onMounted(() => {
  refreshCartIds();
  window.addEventListener("mh:cart-updated", onAnyCartUpdate);
  window.addEventListener("storage", (e) => { if (e.key === STORAGE_KEY) refreshCartIds(); });
});
onBeforeUnmount(() => { window.removeEventListener("mh:cart-updated", onAnyCartUpdate); });
</script>

<style scoped>
/* ===== Фон страницы ===== */
.category-products {
  position: relative;
  min-height: 100vh;
  padding: 120px 24px 100px;
  color: #fff;
  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(255, 153, 0, .08), transparent 60%),
    radial-gradient(800px 400px at 90% 10%, rgba(135, 77, 255, .1), transparent 55%),
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

/* ===== Карточка продукта ===== */
.product-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: stretch;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 22px;
  overflow: hidden;
  min-height: 360px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.28);
  transition: transform .22s ease, border-color .22s ease, box-shadow .22s ease, background .22s ease;
}

.product-card:hover {
  transform: translateY(-3px);
  border-color: rgba(255, 255, 255, .18);
  background: rgba(255, 255, 255, .08);
  box-shadow: 0 18px 56px rgba(0, 0, 0, .34);
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
  letter-spacing: .02em;
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
  transition: transform .18s ease, background .2s ease, box-shadow .2s ease;
}

.btn-primary:hover {
  transform: translateY(-1px);
  background: #ffae33;
  box-shadow: 0 10px 26px rgba(0, 0, 0, .3);
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
  transition: transform .18s ease, background .2s ease, border-color .2s ease;
}

.btn-outline:hover {
  transform: translateY(-1px);
  background: rgba(255, 153, 0, .12);
  border-color: #ffae33;
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, .18);
  background: rgba(255, 255, 255, .06);
  color: #ffd2a3;
  transition: transform .18s ease, background .2s ease, border-color .2s ease, color .2s ease;
}

.btn-ghost:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, .1);
  border-color: rgba(255, 255, 255, .28);
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

/* Правая половина (медиа/слайдер) */
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
  /* базовая лёгкая реакция на ховер */
  transition: transform .45s ease, opacity .24s ease;
}

.product-card:hover .media img {
  transform: scale(1.04);
}

/* Анимированные классы (directional slide) */
.media img.sliding.phase-out.dir-left {
  animation: slideOutLeft .16s ease both;
}

.media img.sliding.phase-in.dir-left {
  animation: slideInLeft .22s ease both;
}

.media img.sliding.phase-out.dir-right {
  animation: slideOutRight .16s ease both;
}

.media img.sliding.phase-in.dir-right {
  animation: slideInRight .22s ease both;
}

@keyframes slideOutLeft {
  from {
    opacity: 1;
    transform: translateX(0) scale(1.00);
  }

  to {
    opacity: .0;
    transform: translateX(-24px) scale(.98);
  }
}

@keyframes slideInLeft {
  from {
    opacity: .0;
    transform: translateX(24px) scale(.98);
  }

  to {
    opacity: 1;
    transform: translateX(0) scale(1.00);
  }
}

@keyframes slideOutRight {
  from {
    opacity: 1;
    transform: translateX(0) scale(1.00);
  }

  to {
    opacity: .0;
    transform: translateX(24px) scale(.98);
  }
}

@keyframes slideInRight {
  from {
    opacity: .0;
    transform: translateX(-24px) scale(.98);
  }

  to {
    opacity: 1;
    transform: translateX(0) scale(1.00);
  }
}

.media .glow {
  position: absolute;
  inset: 0;
  background: radial-gradient(600px 240px at 90% 10%, rgba(135, 77, 255, .12), transparent 60%);
  pointer-events: none;
}

/* Стрелки */
.nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 42px;
  height: 42px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, .25);
  background: rgba(0, 0, 0, .35);
  color: #fff;
  font-size: 26px;
  line-height: 1;
  display: grid;
  place-items: center;
  cursor: pointer;
  user-select: none;
  transition: background .18s ease, transform .1s ease, border-color .18s ease;
}

.nav:hover {
  background: rgba(255, 255, 255, .18);
  border-color: rgba(255, 255, 255, .45);
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
  background: rgba(0, 0, 0, .28);
  border: 1px solid rgba(255, 255, 255, .12);
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, .5);
  border: 1px solid rgba(0, 0, 0, .3);
  cursor: pointer;
  transition: transform .12s ease, background .12s ease;
}

.dot:hover {
  transform: scale(1.2);
}

.dot.active {
  background: #fff;
}

/* Анимации появления карточек */
@keyframes popIn {
  from {
    opacity: 0;
    transform: translateY(6px) scale(.98);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.pop-in {
  animation: popIn .22s ease both;
}

/* Нотификация */
.toast {
  position: fixed;
  right: 16px;
  bottom: 16px;
  background: rgba(255, 153, 0, .95);
  color: #1b1230;
  padding: 12px 16px;
  border-radius: 12px;
  font-weight: 800;
  box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
  z-index: 9999;
}
/* подчёркнутая (левая) часть — жирная и/или цветом */
.colon-strong { font-weight: 800; /* color: var(--accent-color); */ }

/* строка внутри features — один инлайн-блок, чтобы не «разъезжалось» */
.feature-line {
  display: inline;           /* критично: НЕ block/inline-block */
  white-space: normal;       /* разрешаем обычные переносы */
  line-height: inherit;
}

/* сам li уже flex; убедимся, что иконка + текст в одну линию */
.features li {
  display: flex;
  align-items: baseline;     /* чтобы двоеточие и текст были на одной линии */
  gap: 10px;
  flex-wrap: wrap;           /* длинные фразы переносятся, но как одна строка текста */
}

.features i { flex: 0 0 auto; }

/* (необязательно) Если хотите другой цвет именно в title/tagline: */
/* .title .colon-before   { color: var(--accent-color); }
.tagline .colon-before { color: #ffe7c1; } */

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
  }

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
}</style>