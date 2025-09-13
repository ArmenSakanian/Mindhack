<template>
  <header class="header" :class="{ scrolled }">
    <div class="container">
      <!-- Левая: логотип -->
      <div class="left">
  <a href="/" class="brand">
    <img class="logo" :src="logoSrc" alt="Логотип" />
  </a>
</div>


      <!-- Центр: навигация (десктоп) -->
      <nav class="nav desktop" aria-label="Главная навигация">
        <a
          href="#home"
          class="link"
          :class="{ active: active === 'home' }"
          @click.prevent="goTo('home')"
          >Главная</a
        >
        <a
          href="#catalog"
          class="link"
          :class="{ active: active === 'catalog' }"
          @click.prevent="goTo('catalog')"
          >Каталог таблиц</a
        >
        <a
          href="#about"
          class="link"
          :class="{ active: active === 'about' }"
          @click.prevent="goTo('about')"
          >О нас</a
        >
        <a
          href="#feedback"
          class="link"
          :class="{ active: active === 'feedback' }"
          @click.prevent="goTo('feedback')"
          >Обратная связь</a
        >
      </nav>

      <!-- Правая: корзина + бургер -->
      <div class="right">
        <a class="cart" aria-label="Корзина" href="/cart"><i class="fa-solid fa-cart-shopping"></i>
          <span v-if="count > 0" class="badge">{{ count }}</span></a>

        <!-- Бургер справа -->
        <button
          class="burger"
          :class="{ open: mobileOpen }"
          @click="toggleMobile"
          :aria-expanded="mobileOpen ? 'true' : 'false'"
          aria-controls="mobile-nav"
          :aria-label="mobileOpen ? 'Закрыть меню' : 'Открыть меню'"
        >
          <span class="line"></span>
          <span class="line"></span>
          <span class="line"></span>
        </button>
      </div>
    </div>

    <!-- Мобильное меню (выпадает сверху) -->
    <transition name="drop">
      <nav
        v-if="mobileOpen"
        id="mobile-nav"
        class="nav mobile"
        aria-label="Мобильное меню"
        @click.self="closeMobile"
      >
        <a
          href="#home"
          class="mlink"
          :class="{ active: active === 'home' }"
          @click.prevent="goToAndClose('home')"
          >Главная</a
        >
        <a
          href="#catalog"
          class="mlink"
          :class="{ active: active === 'catalog' }"
          @click.prevent="goToAndClose('catalog')"
          >Каталог таблиц</a
        >
        <a
          href="#about"
          class="mlink"
          :class="{ active: active === 'about' }"
          @click.prevent="goToAndClose('about')"
          >О нас</a
        >
        <a
          href="#feedback"
          class="mlink"
          :class="{ active: active === 'feedback' }"
          @click.prevent="goToAndClose('feedback')"
          >Обратная связь</a
        >
      </nav>
    </transition>
  </header>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import logoSrc from "@/assets/logo/logo.png";

const STORAGE_KEY = "mh_cart_v1";
const HEADER_H = 90;

const count = ref(0);
const active = ref("home");
const mobileOpen = ref(false);
const scrolled = ref(false);

const SECTION_IDS = ["home", "catalog", "about", "feedback"];
let sections = [];
let mo; // MutationObserver
let retryTimer; // для повторных попыток

/* ----- корзина ----- */
function updateCount() {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY));
    count.value = Array.isArray(raw)
      ? raw.reduce((s, it) => s + (Number(it.qty) || 0), 0)
      : 0;
  } catch {
    count.value = 0;
  }
}

/* ----- меню: блокировка скролла и управление ----- */
function lockBody(lock) {
  document.documentElement.style.overflow = lock ? "hidden" : "";
  document.body.style.overflow = lock ? "hidden" : "";
}

function toggleMobile() {
  mobileOpen.value = !mobileOpen.value;
  lockBody(mobileOpen.value);
}
function closeMobile() {
  if (!mobileOpen.value) return;
  mobileOpen.value = false;
  lockBody(false);
}
function goToAndClose(id) {
  goTo(id);
  closeMobile();
}
function onKeydown(e) {
  if (e.key === "Escape") closeMobile();
}

/* ----- якорный скролл ----- */
function goTo(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.scrollIntoView({ behavior: "smooth", block: "start" });
}

/* ----- секции/активный пункт ----- */
function setupAnchorsScrollMargin() {
  SECTION_IDS.forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.style.scrollMarginTop = HEADER_H + "px";
  });
}
function collectSections() {
  sections = SECTION_IDS.map((id) => document.getElementById(id)).filter(
    Boolean
  );
}
function ensureSectionsReady() {
  // 1) сразу
  collectSections();
  setupAnchorsScrollMargin();
  if (sections.length) return;

  // 2) быстрые ретраи (если контент догружается)
  let attempts = 0;
  clearInterval(retryTimer);
  retryTimer = setInterval(() => {
    attempts++;
    collectSections();
    setupAnchorsScrollMargin();
    if (sections.length || attempts > 50) {
      clearInterval(retryTimer);
      updateActiveByScroll();
    }
  }, 100);

  // 3) наблюдение DOM
  if (!mo) {
    mo = new MutationObserver(() => {
      const before = sections.length;
      collectSections();
      setupAnchorsScrollMargin();
      if (sections.length && !before) {
        updateActiveByScroll();
      }
    });
    mo.observe(document.body, { childList: true, subtree: true });
  }
}

function updateActiveByScroll() {
  scrolled.value = window.scrollY > 4;

  if (!sections.length) {
    collectSections();
    if (!sections.length) return;
  }
  const cursor = window.scrollY + HEADER_H + 6;
  const absTop = (el) => el.getBoundingClientRect().top + window.scrollY;
  const absBottom = (el) => absTop(el) + el.offsetHeight;

  let current = sections.find(
    (el) => absTop(el) <= cursor && absBottom(el) > cursor
  );
  if (!current) {
    current = sections
      .filter((el) => absTop(el) <= cursor)
      .sort((a, b) => absTop(b) - absTop(a))[0];
  }
  if (!current && window.scrollY < 20) current = sections[0];
  if (current) active.value = current.id;
}

/* ----- lifecycle ----- */
onMounted(() => {
  // корзина
  updateCount();
  window.addEventListener("storage", (e) => {
    if (e.key === STORAGE_KEY) updateCount();
  });
  window.addEventListener("mh:cart-updated", updateCount);

  // секции
  ensureSectionsReady();
  updateActiveByScroll();

  // события
  window.addEventListener("scroll", updateActiveByScroll, { passive: true });
  window.addEventListener("resize", () => {
    collectSections();
    setupAnchorsScrollMargin();
    updateActiveByScroll();
  });
  window.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener("scroll", updateActiveByScroll);
  window.removeEventListener("keydown", onKeydown);
  clearInterval(retryTimer);
  if (mo) mo.disconnect();
  lockBody(false);
});
</script>

<style scoped>
/* ----- БАЗА ----- */
.header {
  position: fixed;
  inset: 0 0 auto 0;
  height: 90px;
  z-index: 1000;
  background: #000;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.header.scrolled {
  background: rgba(0, 0, 0, 0.92);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
}

.container {
  max-width: 1200px;
  height: 100%;
  margin: 0 auto;
  padding: 0 16px;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 12px;
}

.left {
  display: flex;
  align-items: center;
}
.brand {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
}
.logo {
  height: 42px;
  width: auto;
  object-fit: contain;
}

/* ----- Навигация (десктоп) ----- */
.nav.desktop {
  display: flex;
  align-items: center;
  gap: 18px;
}
.link {
  color: #fff;
  opacity: 0.92;
  text-decoration: none;
  padding: 10px 12px;
  border-radius: 12px;
  transition: background 0.2s ease, opacity 0.2s ease, color 0.2s ease;
  user-select: none;
}
.link:hover {
  background: rgba(255, 255, 255, 0.08);
  opacity: 1;
}
.link.active {
  color: #ff9900;
  background: rgba(255, 153, 0, 0.12);
  opacity: 1;
}

/* ----- Правая зона: корзина + бургер ----- */
.right {
  justify-self: end;
  display: flex;
  align-items: center;
  gap: 12px;
}
.cart {
  position: relative;
  color: #e6e6e6;
  font-size: 22px;
  text-decoration: none;
  line-height: 1;
}
.cart:hover {
  color: #ff9900;
}
.badge {
  position: absolute;
  top: -6px;
  right: -10px;
  background: #ff9900;
  color: #1b1230;
  font-size: 13px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 12px;
}

/* ----- Бургер (мобилка/планшет) ----- */
.burger {
  display: none;
  width: 44px;
  height: 44px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 12px;
  background: transparent;
  cursor: pointer;
  align-items: center;
  justify-content: center;
  padding: 0 10px;
  transition: border-color 0.2s ease, background 0.2s ease;
}
.burger:hover {
  border-color: rgba(255, 255, 255, 0.28);
  background: rgba(255, 255, 255, 0.06);
}

.burger .line {
  display: block;
  width: 100%;
  height: 2px;
  background: #fff;
  margin: 5px 0;
  border-radius: 2px;
  transition: transform 0.25s ease, opacity 0.2s ease;
  transform-origin: 50% 50%;
}

/* крестик */
.burger.open .line:nth-child(1) {
  transform: translateY(7px) rotate(45deg);
}
.burger.open .line:nth-child(2) {
  opacity: 0;
}
.burger.open .line:nth-child(3) {
  transform: translateY(-7px) rotate(-45deg);
}

/* ----- Мобильное меню (дроп сверху) ----- */
.nav.mobile {
  position: absolute;
  top: 90px;
  left: 0;
  right: 0;
  background: #0b0b0b;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  flex-direction: column;
  padding: 8px 16px 16px;
  gap: 6px;
  z-index: 999;
}
.mlink {
  padding: 12px 10px;
  text-decoration: none;
  color: #fff;
  border-radius: 10px;
  opacity: 0.95;
}
.mlink:hover {
  background: rgba(255, 255, 255, 0.08);
  opacity: 1;
}
.mlink.active {
  color: #ff9900;
  background: rgba(255, 153, 0, 0.12);
}

/* Анимация выпадения */
.drop-enter-active,
.drop-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.drop-enter-from,
.drop-leave-to {
  transform: translateY(-8px);
  opacity: 0;
}

/* ----- Адаптив ----- */
@media (max-width: 1024px) {
  .nav.desktop {
    display: none;
  }
  .burger {
    display: inline-flex;
    position: absolute;
        right: 15px;
  }
}
</style>
