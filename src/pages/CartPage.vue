<template>
  <section class="cart" aria-labelledby="cart-title">
    <div class="cart__container">
      <!-- Заголовок -->
      <header class="cart__header">
        <h1 id="cart-title" class="cart__title">Корзина</h1>
        <p class="cart__subtitle">
          Проверьте состав заказа и перейдите к оформлению.
        </p>
      </header>

      <!-- Пустое состояние -->
      <div
        v-if="!items.length"
        class="cart__empty"
        role="status"
        aria-live="polite"
      >
        <div class="cart__empty-icon">🛒</div>
        <p class="cart__empty-text">В вашей корзине пока пусто</p>
        <p class="cart__empty-sub">
          Добавьте товары из каталога и вернитесь сюда.
        </p>
        <router-link to="/" class="btn btn--ghost"
          >Вернуться в каталог</router-link
        >
      </div>

      <!-- Контент корзины -->
      <div v-else class="cart__grid">
        <!-- Список товаров -->
        <div class="cart__list">
          <div v-for="(it, idx) in items" :key="it.id" class="cart-item">
            <!-- Фото -->
            <div class="cart-item__image" aria-hidden="true">
              <img
                v-if="it.image"
                :src="it.image"
                :alt="it.name"
                loading="lazy"
              />
            </div>

            <!-- Контент -->
            <div class="cart-item__content">
              <h3 class="cart-item__name">{{ it.name }}</h3>
              <p class="cart-item__id">ID: {{ it.id }}</p>

              <div class="cart-item__row">
                <span
                  class="cart-item__price"
                  :aria-label="`Цена за единицу: ${formatMoney(
                    it.priceKopecks
                  )}`"
                >
                  {{ formatMoney(it.priceKopecks) }} / шт.
                </span>

                <div class="qty" role="group" aria-label="Количество">
                  <button
                    class="qty__btn"
                    @click="decrement(idx)"
                    aria-label="Уменьшить количество"
                    type="button"
                  >
                    −
                  </button>
                  <input
                    class="qty__input"
                    type="number"
                    min="1"
                    :value="it.qty"
                    @input="onQtyInput($event, idx)"
                    inputmode="numeric"
                    aria-label="Количество товара"
                  />
                  <button
                    class="qty__btn"
                    @click="increment(idx)"
                    aria-label="Увеличить количество"
                    type="button"
                  >
                    +
                  </button>
                </div>

                <span
                  class="cart-item__total"
                  :aria-label="`Сумма позиции: ${formatMoney(
                    it.priceKopecks * it.qty
                  )}`"
                >
                  {{ formatMoney(it.priceKopecks * it.qty) }}
                </span>
              </div>

              <div class="cart-item__actions">
                <button
                  @click="remove(idx)"
                  class="link link--danger"
                  type="button"
                >
                  Удалить
                </button>
              </div>
            </div>
          </div>

          <!-- Нижняя панель -->
          <div class="cart__controls">
            <button @click="clearCart" class="link" type="button">
              Очистить корзину
            </button>
          </div>
        </div>

        <!-- Итог -->
        <aside class="cart__summary" aria-labelledby="summary-title">
          <h2 id="summary-title">Итого</h2>

          <!-- Состав заказа -->
          <div class="summary-block">
            <div class="summary-block__head">Состав заказа</div>
            <ul class="summary-items" role="list">
              <li v-for="it in items" :key="`s-${it.id}`" class="summary-item">
                <span class="summary-item__name" :title="it.name">{{
                  it.name
                }}</span>
                <span class="summary-item__price">{{
                  linePrice(it.qty, it.priceKopecks)
                }}</span>
              </li>
            </ul>
          </div>

          <!-- Общая сумма -->
          <div
            class="cart__summary-row cart__summary-row--total"
            aria-live="polite"
          >
            <dt>К оплате</dt>
            <dd>{{ formatMoney(total) }}</dd>
          </div>

          <router-link
            to="/checkout"
            class="btn btn--primary btn--full"
            :class="{ 'btn--disabled': !items.length }"
          >
            Перейти к оформлению
          </router-link>

          <!-- Профессиональное сообщение -->
          <p class="cart__note">
            На следующем шаге вы укажете e-mail для доставки материалов и
            завершите оплату. Передача данных происходит по защищённому
            соединению.
          </p>
        </aside>
      </div>
    </div>
  </section>
</template>

<script setup>
import { reactive, computed, watch, onMounted } from "vue";
import { useRouter } from "vue-router";

const STORAGE_KEY = "cart:v1";
const router = useRouter();

const state = reactive({ items: [] });

function loadCart() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    state.items = raw ? JSON.parse(raw) : [];
  } catch {
    state.items = [];
  }
}
function persist() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state.items));
  // Сообщаем всем компонентам (в т.ч. Header) что корзина обновилась
  window.dispatchEvent(new CustomEvent("cart:change"));
}
onMounted(loadCart);
watch(() => state.items, persist, { deep: true });

const items = computed(() => state.items);

function formatMoney(kopecks) {
  const rub = (kopecks ?? 0) / 100;
  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 0,
  }).format(rub);
}

// "2×450 ₽" из цены за единицу и qty
function linePrice(qty, priceKopecks) {
  const unitRub = Math.round((priceKopecks ?? 0) / 100);
  const unitStr = new Intl.NumberFormat("ru-RU").format(unitRub) + " ₽";
  return `${qty}×${unitStr}`;
}

const subtotal = computed(() =>
  items.value.reduce((s, it) => s + it.priceKopecks * it.qty, 0)
);
const total = computed(() => subtotal.value);

// Количество
function increment(idx) {
  const it = items.value[idx];
  if (!it) return;
  it.qty = Math.min(999, (it.qty || 1) + 1);
}
function decrement(idx) {
  const it = items.value[idx];
  if (!it) return;
  it.qty = Math.max(1, (it.qty || 1) - 1);
}
function onQtyInput(e, idx) {
  const raw = String(e.target.value || "");
  const digits = raw.replace(/[^\d]/g, "");
  const num = digits ? parseInt(digits, 10) : 1;
  const v = Math.max(1, Math.min(999, num));
  items.value[idx].qty = v;
}

function remove(idx) {
  items.value.splice(idx, 1);
}
function clearCart() {
  state.items = [];
}

// Глобальная функция добавления в корзину
if (!window.addToCart) {
  window.addToCart = (product) => {
    const existing = state.items.find((i) => i.id === product.id);
    if (existing)
      existing.qty = Math.min(999, existing.qty + (product.qty || 1));
    else
      state.items.push({
        id: product.id,
        name: product.name,
        priceKopecks: product.priceKopecks,
        image: product.image || "",
        qty: product.qty || 1,
      });
    persist();
  };
}
</script>

<style scoped>
/* ====== Страница и фон ====== */
.cart {
  padding: 90px 40px;
  color: #eee;
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
  min-height: 100vh;
}

.cart__notice {
  margin: 12px 0 0;
  padding: 10px 12px;
  border: 1px solid rgba(255, 153, 0, 0.35);
  background: rgba(255, 153, 0, 0.12);
  color: #ffd9a8;
  border-radius: 10px;
  font-size: 13px;
}


.cart__container {
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.cart__header {
  margin-bottom: 24px;
}
.cart__title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: 0.2px;
  color: var(--accent-color);
}
.cart__subtitle {
  font-size: 14px;
  color: #a8b0bf;
  margin-top: 6px;
}

/* ====== Пустое состояние ====== */
.cart__empty {
  text-align: center;
  padding: 60px;
  border: 1px dashed rgba(255, 255, 255, 0.25);
  border-radius: 14px;
  background: rgba(17, 17, 25, 0.45);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}
.cart__empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
}
.cart__empty-text {
  font-size: 18px;
  font-weight: 600;
}
.cart__empty-sub {
  font-size: 14px;
  color: #c2c7d1;
  margin-top: 4px;
}

/* ====== Сетка ====== */
.cart__grid {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
}
.cart__list {
  flex: 1 1 65%;
}

/* ====== Итог ====== */
.cart__summary {
  flex: 1 1 30%;
}
.cart__summary h2 {
  font-size: 18px;
  margin-bottom: 12px;
}

.summary-block {
  margin-bottom: 12px;
}
.summary-block__head {
  font-size: 12px;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  color: #9ea6b6;
  margin-bottom: 8px;
}

/* Список позиций в итогах */
.summary-items {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 220px; /* прокрутка, если много позиций */
  overflow: auto;
}
.summary-item {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
}
.summary-item:last-child {
  border-bottom: none;
}
.summary-item__name {
  font-size: 14px;
  color: #e6e8ec;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.summary-item__price {
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
}

/* Общая сумма */
.cart__summary-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-top: 8px;
  color: #e6e8ec;
}
.cart__summary-row dt {
  color: #b6bcc8;
}
.cart__summary-row--total {
  font-size: 16px;
  font-weight: 800;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 10px;
  color: #fff;
  margin-top: 12px;
}

.cart__note {
  font-size: 12px;
  color: #b1b6c2;
  margin-top: 10px;
}

/* ====== Элементы списка ====== */
.cart-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.cart-item__image {
  width: 90px;
  height: 90px;
  flex-shrink: 0;
  border-radius: 12px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.25);
}
.cart-item__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.cart-item__name {
  font-size: 16px;
  font-weight: 700;
}
.cart-item__id {
  font-size: 12px;
  color: #b1b6c2;
  margin-top: 2px;
}

/* Ряд со стоимостью и qty */
.cart-item__row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}
.cart-item__price {
  font-size: 14px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;
  padding: 6px 10px;
  background: rgba(17, 17, 25, 0.45);
  backdrop-filter: blur(6px);
}
.cart-item__total {
  margin-left: auto;
  font-weight: 800;
}

/* Qty */
.qty {
  display: inline-flex;
  align-items: center;
  gap: 0;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  overflow: hidden;
  background: rgba(17, 17, 25, 0.45);
  backdrop-filter: blur(6px);
}
.qty__btn {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  color: #eef1f6;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s ease, transform 0.05s ease;
}
.qty__btn:hover {
  background: rgba(255, 255, 255, 0.08);
}
.qty__btn:active {
  transform: translateY(1px);
}
.qty__input {
  width: 60px;
  height: 36px;
  text-align: center;
  border: none;
  background: transparent;
  color: #fff;
  font-weight: 700;
  -moz-appearance: textfield;
}
.qty__input::-webkit-outer-spin-button,
.qty__input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Действия строки */
.cart-item__actions {
  margin-top: 10px;
  display: flex;
  gap: 12px;
}

.link--danger {
  color: #941b0c;
  transition: color 0.2s ease;
}
.link--danger:hover {
  color: #ff2e2e;
}

/* Низ списка */
.cart__controls {
  display: flex;
  justify-content: space-between;
  margin-top: 16px;
}

/* Кнопки */

.btn--full {
  width: 100%;
  margin-top: 16px;
}
.btn--disabled {
  opacity: 0.5;
  pointer-events: none;
}

/* ====== Адаптив ====== */
@media (max-width: 980px) {
  .cart {
    padding: 72px 20px;
  }
  .cart__grid {
    flex-direction: column;
  }
  .cart__list,
  .cart__summary {
    flex: 1 1 100%;
  }
  .cart-item {
    padding: 14px 8px;
  }
  .cart-item__image {
    width: 76px;
    height: 76px;
  }
  .summary-items {
    max-height: 180px;
  }
}

@media (max-width: 768px) {
  .cart {
    padding: 60px 16px;
  }
  .cart__title {
    font-size: 22px;
  }
  .cart-item {
    flex-direction: column;
    align-items: flex-start;
  }
  .cart-item__row {
    flex-wrap: wrap;
  }
  .cart-item__total {
    margin-left: 0;
  }
  .cart__summary h2 {
    font-size: 16px;
  }
}

@media (max-width: 480px) {
  .cart__title {
    font-size: 20px;
  }
  .cart-item__image {
    width: 64px;
    height: 64px;
  }
  .cart-item__name {
    font-size: 14px;
  }
  .cart-item__price,
  .cart-item__total,
  .summary-item__price,
  .summary-item__name {
    font-size: 12px;
  }
  .qty__btn {
    width: 30px;
    height: 30px;
    font-size: 16px;
  }
  .qty__input {
    width: 40px;
    height: 30px;
    font-size: 14px;
  }
  .cart__note {
    font-size: 11px;
  }
}

</style>
