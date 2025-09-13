<template>
  <section class="checkout">
    <!-- Фон -->
    <div class="bg"></div>

    <div class="wrap">
      <header class="head">
        <h1 class="title">Оформление заказа</h1>
        <p class="subtitle">
          Укажите актуальный e-mail — на него отправим ссылку для скачивания.
        </p>
      </header>

      <div v-if="items.length === 0" class="empty">
        <p>Корзина пуста.</p>
        <a href="/" class="btn">Вернуться в каталог</a>
      </div>

      <div v-else class="grid">
        <!-- Чек -->
        <div class="card receipt">
          <div class="receipt-head">
            <h2 class="h2">Чек</h2>
            <span class="tag">Цифровой товар</span>
          </div>

          <ul class="lines">
            <li v-for="it in items" :key="it.id" class="line">
              <div class="left">
                <img v-if="it.image" :src="it.image" alt="" />
                <div class="info">
                  <div class="name">{{ it.title }}</div>
                  <div class="muted qty">× {{ it.qty }}</div>
                </div>
              </div>
              <div class="right">
                <div class="unit">{{ fmt(it.price) }}</div>
                <div class="total">{{ fmt(it.price * it.qty) }}</div>
              </div>
            </li>
          </ul>

          <div class="sum">
            <div class="grand">
              <span>Итого к оплате</span>
              <strong>{{ fmt(grandTotal) }}</strong>
            </div>
          </div>
        </div>

        <!-- Данные покупателя -->
        <div class="card sticky">
          <h2 class="h2">Данные покупателя</h2>

          <form class="form" @submit.prevent="pay">
            <label class="field">
              <span>Имя</span>
              <input
                v-model.trim="form.name"
                type="text"
                required
                placeholder="Ваше имя"
              />
            </label>

            <label class="field">
              <span>E-mail</span>
              <input
                v-model.trim="form.email"
                type="email"
                required
                :class="{ invalid: emailTouched && !validEmail }"
                @blur="emailTouched = true"
                placeholder="you@example.com"
              />
              <small class="hint big-hint">
                Проверьте e-mail — туда придёт безопасная ссылка на скачивание.
              </small>
            </label>

            <label class="field">
  <span>Телефон</span>
  <input
    v-model="form.phone"
    type="tel"
    placeholder="+7 (___) ___-__-__"
    @input="formatPhone"
  />
</label>


            <label class="check">
              <input type="checkbox" v-model="agree" />
              <span>
                Я принимаю
                <a href="/offer" target="_blank">условия оферты</a>
                и
                <a href="/privacy" target="_blank">политику конфиденциальности</a>
              </span>
            </label>

            <button
              class="btn primary big"
              :disabled="!canPay"
            >
              Оплатить {{ fmt(grandTotal) }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Модальное окно (оставил как переход к оплате, без текста про «заглушку») -->
    <div v-if="showMock" class="modal">
      <div class="modal-card">
        <h3>Переход к оплате</h3>
        <p>
          Открываем платёжного провайдера на сумму <b>{{ fmt(grandTotal) }}</b>.
        </p>
        <div class="row">
          <button class="btn" @click="showMock = false">Отмена</button>
          <button class="btn primary" @click="finishMock">
            Смоделировать успешную оплату
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

/** Если в корзине другой ключ — замени здесь */
const LS_KEY = 'mh_cart_v1'

const router = useRouter()
const items = ref([]) // [{id,title,price,image?,qty}]

function loadCart() {
  try {
    const raw = localStorage.getItem(LS_KEY)
    items.value = raw ? JSON.parse(raw) : []
  } catch {
    items.value = []
  }
}
onMounted(loadCart)

const subtotal = computed(() =>
  items.value.reduce((s, it) => s + it.price * it.qty, 0)
)
const grandTotal = computed(() => Math.max(0, subtotal.value))

function fmt(n) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(n || 0)
}

function formatPhone(e) {
  let digits = e.target.value.replace(/\D/g, '')

  // убираем возможный первый 8 или 7
  if (digits.startsWith('7') || digits.startsWith('8')) {
    digits = digits.substring(1)
  }

  // ограничим только 10 цифрами
  digits = digits.substring(0, 10)

  let formatted = '+7'
  if (digits.length > 0) {
    formatted += ' (' + digits.substring(0, 3)
  }
  if (digits.length >= 3) {
    formatted += ') ' + digits.substring(3, 6)
  }
  if (digits.length >= 6) {
    formatted += '-' + digits.substring(6, 8)
  }
  if (digits.length >= 8) {
    formatted += '-' + digits.substring(8, 10)
  }

  e.target.value = formatted
  form.value.phone = formatted
}


/* ------- Форма (без OTP) ------- */
const form = ref({
  name: '',
  email: '',
  phone: '',
})
const emailTouched = ref(false)
const validEmail = computed(() =>
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)
)

const agree = ref(false)
const showMock = ref(false)

/** К оплате допускаем: валидная почта + согласие + товары есть + имя заполнено */
const canPay = computed(() =>
  validEmail.value &&
  agree.value &&
  items.value.length > 0 &&
  form.value.name.trim().length > 1
)

function pay() {
  if (!canPay.value) return
  // Здесь позже подключим реальный платёжный провайдер
  showMock.value = true
}

function finishMock() {
  showMock.value = false
  // Очистка корзины и переход на страницу успеха
  localStorage.removeItem(LS_KEY)
  router.push({ name: 'paymentSuccess', query: { amount: grandTotal.value } })
}
</script>

<style scoped>
/* ---------- Базовая область ---------- */
.checkout {
  position: relative;
  min-height: 100vh;
  color: #ececf3;
  overflow: clip;
  padding: 40px 0;
}

/* ---------- Фон ---------- */
.bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(
      1200px 600px at 10% -10%,
      rgba(255, 153, 0, 0.08),
      transparent 60%
    ),
    radial-gradient(
      800px 400px at 90% 10%,
      rgba(135, 77, 255, 0.1),
      transparent 55%
    ),
    linear-gradient(
      160deg,
      #0f0b1a,
      #1b1230 45%,
      #2a1545 70%,
      #35185a
    );
  z-index: 0;
}

/* ---------- Контейнер ---------- */
.wrap {
  position: relative;
  z-index: 1;
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 16px;
}

/* ---------- Заголовок ---------- */
.head { margin-bottom: 18px; }
.title {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 6px;
}
.subtitle {
  font-size: 15px;
  color: #a9a9b3;
}

/* ---------- Пустая корзина ---------- */
.empty {
  display: grid;
  gap: 14px;
  background: rgba(20, 20, 28, 0.65);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 20px;
  width: min(560px, 100%);
  margin-top: 16px;
}

/* ---------- Сетка ---------- */
.grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) {
  .grid { grid-template-columns: 1fr; }
}

/* ---------- Карточки ---------- */
.card {
  background: rgba(20, 20, 28, 0.65);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 20px;
  box-shadow:
    0 8px 40px rgba(0, 0, 0, 0.35),
    0 0 0 1px rgba(255, 255, 255, 0.03) inset;
  backdrop-filter: blur(10px);
}
.sticky { position: sticky; top: 24px; }

/* ---------- Чек — усиленный стиль ---------- */
.receipt .receipt-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 8px;
}
.tag {
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.08);
}
.lines {
  display: flex; flex-direction: column; gap: 12px; margin-top: 10px;
}
.line {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center; gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.line:last-child { border-bottom: none; }
.left {
  display: flex; align-items: center; gap: 12px; min-width: 0;
}
.left img {
  width: 64px; height: 64px; object-fit: cover;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}
.info .name { font-weight: 600; }
.qty { font-size: 12px; }
.muted { color: #9aa0a6; }
.right { text-align: right; }
.unit {
  font-size: 12px; color: #9aa0a6;
}
.total {
  font-weight: 800; font-size: 16px; letter-spacing: .2px;
}

/* ---------- Итог ---------- */
.sum {
  margin-top: 14px;
  background: linear-gradient(180deg, rgba(255,153,0,0.06), rgba(135,77,255,0.06));
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 14px;
  padding: 14px 16px;
}
.grand {
  display: flex; align-items: center; justify-content: space-between;
}
.grand span { opacity: .9; }
.grand strong { font-size: 22px; }

/* ---------- Форма ---------- */
.form { margin-top: 8px; }
.field {
  display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px;
}
.field input {
  height: 48px; border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.02);
  color: #fff; padding: 0 14px;
}
.field input.invalid {
  border-color: rgba(255, 83, 83, 0.6);
  box-shadow: 0 0 0 4px rgba(255, 83, 83, 0.15);
}
.field input:focus {
  outline: none;
  border-color: rgba(135, 77, 255, 0.6);
  box-shadow: 0 0 0 4px rgba(135, 77, 255, 0.15);
}
.hint { font-size: 12px; color: #a9a9b3; }
.big-hint { font-size: 13.5px; color: #e2e2ea; }

/* ---------- Чекбокс ---------- */
.check {
  display: flex; align-items: center; gap: 10px; margin: 10px 0 16px;
}
.check input[type="checkbox"] {
  width: 18px; height: 18px;
  accent-color: #ff9900; /* #f90 */
}
.check a {
  color: #ff9900;
  text-decoration: none; /* убрали пунктир */
}
.check a:hover { text-decoration: underline; }

/* ---------- Кнопки ---------- */
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  height: 46px; padding: 0 18px; border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.05);
  color: #fff; cursor: pointer; text-decoration: none; transition: 0.25s ease;
}
.btn:hover { background: rgba(255, 255, 255, 0.1); }
.btn.primary {
  background: linear-gradient(135deg, #874dff, #ff9900);
  border: none; color: #fff; font-weight: 600;
  box-shadow: 0 8px 24px rgba(135, 77, 255, 0.4);
}
.btn.primary:hover { box-shadow: 0 12px 28px rgba(135, 77, 255, 0.55); }
.btn.big { height: 52px; font-weight: 700; letter-spacing: 0.2px; }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }

/* ---------- Модалка ---------- */
.modal {
  position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6);
  display: grid; place-items: center; z-index: 50;
}
.modal-card {
  width: min(520px, 92vw); background: rgba(20, 20, 28, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px; padding: 20px; backdrop-filter: blur(10px);
  display: grid; gap: 14px;
}
.row { display: flex; gap: 10px; justify-content: flex-end; }
</style>
