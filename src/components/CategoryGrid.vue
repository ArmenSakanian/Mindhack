<template>
  <section class="catalog" id="catalog">
    <div class="container">
      <!-- Заголовок -->
      <header class="head">
        <h2>Каталог MindHack</h2>
        <p>
          Готовые таблицы и мини-системы, которые экономят время и деньги.
          Выберите направление — внутри наборы, пресеты и шаблоны с
          автографиками и готовыми отчётами.
        </p>
      </header>

      <!-- Сетка категорий -->
      <!-- Сетка категорий -->
<div class="row">
  <article
    data-aos="fade-up"
    v-for="cat in categories"
    :key="cat.id"
    class="panel"
    role="button"
    tabindex="0"
    @click="goToCategory(cat.id)"
    @keypress.enter="goToCategory(cat.id)"
  >
    <!-- Медиа -->
    <div class="media">
      <img :src="cat.image" :alt="cat.title" loading="lazy" />
      <div class="badge" v-if="cat.icon">
        <i :class="cat.icon" aria-hidden="true"></i>
      </div>
    </div>

    <!-- Контент -->
    <div class="body">
      <div class="eyebrow" v-if="cat.eyebrow">{{ cat.eyebrow }}</div>
      <h3 class="title">{{ cat.title }}</h3>
      <p class="tagline" v-if="cat.tagline">{{ cat.tagline }}</p>

      <ul class="perks" v-if="cat.perks?.length">
        <li v-for="perk in cat.perks" :key="perk">
          <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
          {{ perk }}
        </li>
      </ul>

      <div class="meta" v-if="cat.price">
        <div class="price">
          <span class="price_text">от </span> {{ formatPrice(cat.price) }}
        </div>
      </div>

      <!-- Кнопку можешь оставить или убрать -->
      <div class="actions">
        <a
  class="btn--primary btn"
  :href="`/category/${cat.id}`"
  :aria-label="`Открыть каталог: ${cat.title}`"
  @click.stop
>
  <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
  Открыть каталог
</a>
      </div>
    </div>
  </article>
</div>

    </div>
  </section>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const API_LIST = "/php/categories/list.php";
const categories = ref([]);

function goToCategory(id) {
  window.location.href = `/category/${id}`;
}


function toArrayKeywords(k) {
  if (Array.isArray(k)) return k;
  if (typeof k === "string") {
    const s = k.trim();
    if (!s) return [];
    try {
      if (s.startsWith("[")) return JSON.parse(s);
    } catch {}
    return s
      .split(",")
      .map((x) => x.trim())
      .filter(Boolean);
  }
  return [];
}

function mapItemToCard(item) {
  return {
    id: item.id,
    title: item.title,
    eyebrow: item.subtitle || "",
    tagline: item.description || "",
    perks: toArrayKeywords(item.keywords),
    price: item.price,
    image: item.image_url || item.image || "",
    icon: "fa-solid fa-layer-group",
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

async function loadCategories() {
  try {
    const res = await fetch(`${API_LIST}?page=1&limit=100`, {
      credentials: "same-origin",
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Ошибка загрузки");
    categories.value = (data.items || []).map(mapItemToCard);
  } catch (e) {
    console.error("Не удалось загрузить категории:", e);
    categories.value = [];
  }
}

onMounted(loadCategories);
</script>

<style scoped>
/* ====== БАЗА / ФОН ====== */
.catalog {
  position: relative;
  padding: 120px 20px 90px;
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
  max-width: 1500px;
  margin: 0 auto;
}

/* ====== ЗАГОЛОВОК ====== */
.head {
  text-align: center;
  margin-bottom: 36px;
}
.head h2 {
  margin: 0 0 10px;
  font-weight: 900;
  font-size: clamp(28px, 4vw, 56px);
  color: var(--accent-color);
  line-height: 1.1;
}
.head p {
  color: #cfc7de;
  font-size: clamp(14px, 2.1vw, 20px);
  max-width: 1000px;
  margin: 0 auto;
}

/* ====== СЕТКА (адаптив) ====== */
.row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 28px;
}
@media (max-width: 1280px) {
  .row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 980px) {
  .row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
  }
}
@media (max-width: 640px) {
  .row {
    grid-template-columns: 1fr;
    gap: 18px;
  }
}

/* ====== ПАНЕЛИ ====== */
.panel {
  display: flex;
  flex-direction: column;
  background: #11111999;
  border-radius: 22px;
  overflow: hidden;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.28);
  cursor: pointer;
}
.panel:hover {
  transform: translateY(-3px);
  background: #00000099;
  border-color: rgba(255, 255, 255, 0.18);
  box-shadow: 0 16px 56px rgba(0, 0, 0, 0.35);
  transition: 0.6s;
}

/* ====== МЕДИА ====== */
.media {
  position: relative;
  width: 100%;
  overflow: hidden;
}
.media img {
  display: block;
  width: 100%;
  aspect-ratio: 16/9;
  object-fit: cover;
  transition: transform 0.45s ease;
}
@media (hover: hover) {
  .panel:hover .media img {
    transform: scale(1.05);
  }
}

/* Иконка-бейдж */
.badge {
  position: absolute;
  top: 14px;
  left: 14px;
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(
    135deg,
    rgba(255, 153, 0, 0.28),
    rgba(255, 153, 0, 0.06)
  );
  border: 1px solid rgba(255, 153, 0, 0.4);
  box-shadow: 0 6px 18px rgba(255, 153, 0, 0.18);
}
.badge i {
  font-size: 24px;
  color: #ffbb44;
}

/* ====== КОНТЕНТ ====== */
.body {
  display: flex;
  flex-direction: column;
  flex: 1; /* ВАЖНО: тянем контент на всю высоту панели */
  gap: 12px;
  padding: 22px 24px 24px;
}
.eyebrow {
  color: #cfc7de;
  font-size: 12px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-style: italic;
  font-weight: 200;
}
.title {
  margin: 0;
  font-size: clamp(20px, 2.4vw, 30px);
  font-weight: 900;
  color: var(--accent-color);
  line-height: 1.15;
}
.tagline {
  margin: 4px 0 2px;
  color: #d9d2eb;
  font-size: clamp(14px, 1.8vw, 18px);
  font-weight: 100;
}
.perks {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 16px;
  list-style: none;
  padding: 0;
  margin: 6px 0 0;
}
.perks li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #fff;
  font-size: 15px;
  line-height: 1.3;
  font-weight: 300;
}
.perks i {
  color: var(--accent-color);
}
@media (max-width: 640px) {
  .perks {
    grid-template-columns: 1fr;
  }
}

/* ====== ЦЕНА ====== */
.meta {
  margin-top: 6px;
}
.price {
  font-weight: 900;
  font-size: 20px;
  color: var(--accent-color);
}

.price_text {
  font-weight: 200;
}

/* ====== КНОПКА ====== */
.actions {
  margin-top: auto; /* прижимаем вниз */
  margin-bottom: 10px; /* зазор от нижнего края */
}

@media (max-width: 480px) {
  .btn--primary {
    width: 100%;
  }
}

/* ====== ПРЕДПОЧТЕНИЯ ПОЛЬЗОВАТЕЛЯ ====== */
@media (prefers-reduced-motion: reduce) {
  .panel,
  .media img,
  .btn--primary {
    transition: none;
  }
}
</style>
