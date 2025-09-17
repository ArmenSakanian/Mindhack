<template>
  <section class="checkout">
    <div class="checkout__container">
      <!-- Заголовок -->
      <header class="checkout__header">
        <h1 class="checkout__title">Оформление заказа</h1>
        <p class="checkout__subtitle">
          Укажите e-mail для доставки материалов и подтвердите заказ.
        </p>
      </header>

      <!-- Пустая корзина -->
      <div v-if="!items.length" class="checkout__empty">
        <div class="checkout__empty-icon">🛒</div>
        <p class="checkout__empty-text">Корзина пуста</p>
        <p class="checkout__empty-sub">
          Вернитесь в каталог и добавьте товары.
        </p>
        <router-link to="/" class="btn btn--ghost">В каталог</router-link>
      </div>

      <!-- Контент -->
      <div v-else class="checkout__grid">
        <!-- Форма покупателя -->
        <form class="checkout__form" @submit.prevent="submit">
          <h2 class="checkout__form-title">Данные покупателя</h2>

          <div class="form-grid">
            <div class="form-field">
              <label class="form-label" for="fld-name">Имя</label>
              <input
                id="fld-name"
                v-model.trim="form.name"
                type="text"
                class="input"
                placeholder="Ваше имя"
              />
            </div>
            <div class="form-field">
              <label class="form-label" for="fld-email"
                >E-mail для получения ссылок</label
              >
              <input
                id="fld-email"
                v-model.trim="form.email"
                type="email"
                class="input"
                placeholder="you@example.com"
              />
            </div>
          </div>

          <!-- Согласия -->
          <div class="consents">
            <!-- 1) Обязательное согласие с политикой и офертой -->
            <label class="check">
              <input type="checkbox" v-model="form.termsAccepted" />
              <span>
                Я соглашаюсь с
                <router-link
                  class="check__link"
                  to="/PrivacyPolicy"
                  target="_blank"
                  >Политикой конфиденциальности</router-link
                >
                и
                <router-link
                  class="check__link"
                  to="/PublicOffer"
                  target="_blank"
                  >Публичной офертой</router-link
                >.
              </span>
            </label>

            <!-- 2) Маркетинговые рассылки (необязательное) -->
            <label class="check">
              <input type="checkbox" v-model="form.marketing" />
              <span>
                Согласен получать рекламные и информационные рассылки.
                <router-link
                  class="check__link"
                  to="/MarketingConsent"
                  target="_blank"
                  >Подробнее</router-link
                >
              </span>
            </label>
          </div>

          <div v-if="error" class="alert alert--error">{{ error }}</div>

          <div class="form-actions">
            <button
              type="submit"
              class="btn btn--primary"
              :disabled="submitting"
            >
              <span v-if="!submitting">Перейти к оплате</span>
              <span v-else>Создаём заказ…</span>
            </button>
            <router-link to="/cart" class="link"
              >Вернуться в корзину</router-link
            >
          </div>
        </form>

        <!-- Резюме заказа -->
        <aside class="summary">
          <h2 class="summary__title">Ваш заказ</h2>

          <ul class="summary__list">
            <li v-for="it in items" :key="it.id" class="summary-item">
              <div class="summary-item__image">
                <img v-if="it.image" :src="it.image" :alt="it.name" />
              </div>
              <div class="summary-item__content">
                <div class="summary-item__name" :title="it.name">
                  {{ it.name }}
                </div>
                <div class="summary-item__qty">× {{ it.qty }}</div>
              </div>
              <div class="summary-item__sum">
                {{ formatMoney(it.priceKopecks * it.qty) }}
              </div>
            </li>
          </ul>

          <dl class="summary__totals">
            <div class="summary__row">
              <dt>Товары</dt>
              <dd>{{ formatMoney(subtotal) }}</dd>
            </div>
            <div class="summary__row">
              <dt>Скидка</dt>
              <dd>− {{ formatMoney(discount) }}</dd>
            </div>
            <div class="summary__row summary__row--total">
              <dt>К оплате</dt>
              <dd>{{ formatMoney(total) }}</dd>
            </div>
          </dl>
        </aside>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { reactive, computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const CART_KEY = "cart:v1";

// ===== Корзина (тот же формат, что и на CartPage.vue)
const state = reactive<{ items: Array<any> }>({ items: [] });
function loadCart() {
  try {
    const raw = localStorage.getItem(CART_KEY);
    state.items = raw ? JSON.parse(raw) : [];
  } catch {
    state.items = [];
  }
}
const items = computed(() => state.items);
const subtotal = computed(() =>
  items.value.reduce((s, it) => s + it.priceKopecks * it.qty, 0)
);
const discount = computed(() => 0);
const total = computed(() => Math.max(subtotal.value - discount.value, 0));

function formatMoney(kopecks?: number) {
  const rub = (kopecks ?? 0) / 100;
  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 0,
  }).format(rub);
}

// ===== Форма
const form = reactive({
  name: "",
  email: "",
  termsAccepted: false, // обязательное согласие
  marketing: false, // опционально
});
const submitting = ref(false);
const error = ref("");

// простая проверка e-mail
function isEmail(s: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
}

async function submit() {
  if (submitting.value) return;
  error.value = "";

  if (!items.value.length) {
    router.push("/");
    return;
  }
  form.name = form.name.trim();
  form.email = form.email.trim();

  if (!form.name) {
    error.value = "Укажите имя";
    return;
  }
  if (!isEmail(form.email)) {
    error.value = "Укажите корректный e-mail";
    return;
  }
  if (!form.termsAccepted) {
    error.value =
      "Необходимо согласиться с Политикой конфиденциальности и Публичной офертой";
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      customer: { name: form.name, email: form.email },
      marketing_consent: !!form.marketing,
      items: items.value.map((it: any) => ({ product_id: it.id, qty: it.qty })),
    };

    const res = await fetch("/php/payment/create_order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    let data: any = null;
    try {
      data = await res.json();
    } catch {
      /* ignore non-JSON */
    }

    if (!res.ok || !data || data.ok === false) {
      const msg = data && data.message ? data.message : `HTTP ${res.status}`;
      throw new Error(msg);
    }

    const url = data.payment_url;
    if (!url) throw new Error("Ссылка на оплату не получена");

    window.location.assign(url);
  } catch (e: any) {
    error.value = "Не удалось создать заказ: " + (e?.message || "ошибка сети");
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  loadCart();
});
</script>

<style scoped>
/* ===== Базовая сетка и темы (в стиле CartPage) ===== */
.checkout {
  padding: 90px 40px;
  min-height: 100vh;
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
}
.checkout__container {
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.checkout__header {
  margin-bottom: 24px;
}
.checkout__title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: 0.2px;
}
.checkout__subtitle {
  margin-top: 6px;
  font-size: 14px;
  color: #a8b0bf;
}

/* ===== Пустая корзина (glass) ===== */
.checkout__empty {
  text-align: center;
  padding: 60px;
  border: 1px dashed rgba(255, 255, 255, 0.25);
  border-radius: 14px;
  background: rgba(17, 17, 25, 0.45);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}
.checkout__empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
}
.checkout__empty-text {
  font-size: 18px;
  font-weight: 600;
}
.checkout__empty-sub {
  font-size: 14px;
  color: #c2c7d1;
  margin-top: 4px;
}

/* ===== Контент: форма + резюме ===== */
.checkout__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}
@media (min-width: 992px) {
  .checkout__grid {
    grid-template-columns: minmax(0, 1fr) 380px;
  }
}

/* ===== Форма (glass) ===== */

.checkout__form-title {
  font-size: 18px;
  font-weight: 700;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  margin-top: 16px;
}
@media (min-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-label {
  font-size: 13px;
  color: #9aa0a6;
}
.input {
  height: 44px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(0, 0, 0, 0.15);
  color: #eee;
  padding: 0 12px;
  outline: none;
  transition: box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
}
.input::placeholder {
  color: #9aa0a6;
}
.input:focus {
  border-color: rgba(255, 255, 255, 0.35);
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.12);
  background: rgba(0, 0, 0, 0.25);
}

/* согласия */
.consents {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 14px;
}
.check {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  font-size: 14px;
  color: #ddd;
  line-height: 1.4;
}


/* ошибки/алерты */
.alert {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 12px;
  font-size: 14px;
}
.alert--error {
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f2a6a6;
}

/* действия формы */
.form-actions {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-top: 18px;
}


.summary__title {
  font-size: 18px;
  font-weight: 700;
}

.summary__list {
  margin-top: 12px;
  list-style: none;
  padding: 0;
}
.summary-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.summary-item:last-child {
  border-bottom: 0;
}
.summary-item__image {
  width: 56px;
  height: 56px;
  border-radius: 10px;
  overflow: hidden;
  background: #222;
  flex-shrink: 0;
}
.summary-item__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.summary-item__content {
  flex: 1;
  min-width: 0;
}
.summary-item__name {
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.summary-item__qty {
  font-size: 12px;
  color: #8a8f98;
  margin-top: 2px;
}
.summary-item__sum {
  font-size: 14px;
  font-weight: 700;
}

/* суммы */
.summary__totals {
  margin-top: 16px;
}
.summary__row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-bottom: 8px;
  color: #e6e8ec;
}
.summary__row dt {
  color: #b6bcc8;
}
.summary__row--total {
  font-size: 16px;
  font-weight: 800;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 10px;
  color: #fff;
}

/* ===== Кнопки и ссылки ===== */


.btn[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
}



/* ====== Адаптив ====== */

/* Планшеты и меньше */
@media (max-width: 992px) {
  .checkout {
    padding: 72px 20px;
  }
  .checkout__grid {
    grid-template-columns: 1fr;
  }
  .summary {
    order: -1; /* сначала заказ, потом форма */
  }
}

/* Средние экраны (до 768px) */
@media (max-width: 768px) {
  .checkout__title {
    font-size: 24px;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
  .form-actions {
    flex-direction: column;
    align-items: stretch;
  }
  .form-actions .btn {
    width: 100%;
  }
  .summary-item__image {
    width: 48px;
    height: 48px;
  }
}

/* Смартфоны (до 480px) */
@media (max-width: 480px) {
  .checkout {
    padding: 60px 16px;
  }
  .checkout__title {
    font-size: 20px;
  }
  .checkout__subtitle {
    font-size: 13px;
  }
  .form-label {
    font-size: 12px;
  }
  .input {
    height: 40px;
    font-size: 14px;
    border-radius: 10px;
  }
  .summary__title {
    font-size: 16px;
  }
  .summary-item__name {
    font-size: 13px;
  }
  .summary-item__sum {
    font-size: 13px;
  }
  .summary__row {
    font-size: 13px;
  }
  .summary__row--total {
    font-size: 15px;
  }
}

</style>
