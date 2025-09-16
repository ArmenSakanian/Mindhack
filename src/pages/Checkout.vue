<template>
  <section class="checkout-page">
    <div class="container mx-auto px-4 py-8 lg:py-10">
      <!-- Заголовок -->
      <header class="mb-6 lg:mb-8">
        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">Оформление заказа</h1>
        <p class="text-sm text-gray-400 mt-1">Укажите e‑mail для доставки материалов и подтвердите заказ.</p>
      </header>

      <!-- Пустая корзина -->
      <div v-if="!items.length" class="text-center py-16 border border-dashed border-gray-700/60 rounded-2xl bg-gray-900/40">
        <div class="text-5xl mb-3">🛒</div>
        <p class="text-lg font-medium">Корзина пуста</p>
        <p class="text-gray-400 mt-1">Вернитесь в каталог и добавьте товары.</p>
        <router-link to="/" class="inline-flex items-center justify-center mt-6 px-5 h-11 rounded-xl bg-white/10 hover:bg-white/20 transition focus:outline-none focus:ring-2 focus:ring-white/40">
          В каталог
        </router-link>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
        <!-- Форма покупателя -->
        <form class="lg:col-span-7 rounded-2xl border border-gray-800 bg-gray-900/40 p-5" @submit.prevent="submit">
          <h2 class="text-lg font-semibold">Данные покупателя</h2>

          <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="col-span-2 sm:col-span-1">
              <label class="block text-sm text-gray-400 mb-1" for="fld-name">Имя</label>
              <input id="fld-name" v-model.trim="form.name" type="text" class="w-full h-11 rounded-xl bg-transparent border border-gray-700 px-3 focus:outline-none focus:ring-2 focus:ring-white/30" placeholder="Ваше имя" />
            </div>
            <div class="col-span-2 sm:col-span-1">
              <label class="block text-sm text-gray-400 mb-1" for="fld-email">E‑mail для получения ссылок</label>
              <input id="fld-email" v-model.trim="form.email" type="email" class="w-full h-11 rounded-xl bg-transparent border border-gray-700 px-3 focus:outline-none focus:ring-2 focus:ring-white/30" placeholder="you@example.com" />
            </div>
          </div>

          <label class="mt-4 flex items-start gap-3 text-sm text-gray-300">
            <input type="checkbox" v-model="form.marketing" class="mt-1" />
            <span>Согласен получать анонсы и полезные материалы на почту</span>
          </label>

          <div v-if="error" class="mt-4 text-sm text-red-300">{{ error }}</div>

          <div class="mt-5 flex items-center gap-3">
            <button type="submit" class="inline-flex items-center justify-center px-5 h-11 rounded-xl bg-white text-gray-900 font-semibold hover:brightness-95 transition focus:outline-none focus:ring-2 focus:ring-white/40 disabled:opacity-50 disabled:pointer-events-none" :disabled="submitting">
              <span v-if="!submitting">Перейти к оплате</span>
              <span v-else>Создаём заказ…</span>
            </button>
            <router-link to="/cart" class="text-sm text-gray-400 hover:text-gray-200">Вернуться в корзину</router-link>
          </div>
        </form>

        <!-- Резюме заказа -->
        <aside class="lg:col-span-5">
          <div class="rounded-2xl border border-gray-800 bg-gray-900/40 p-5 sticky top-6">
            <h2 class="text-lg font-semibold">Ваш заказ</h2>
            <ul class="mt-3 divide-y divide-gray-800">
              <li v-for="it in items" :key="it.id" class="py-3 flex items-center gap-3">
                <div class="w-14 h-14 rounded-lg bg-gray-800 overflow-hidden flex-shrink-0">
                  <img v-if="it.image" :src="it.image" :alt="it.name" class="w-full h-full object-cover" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium truncate">{{ it.name }}</div>
                  <div class="text-xs text-gray-400">× {{ it.qty }}</div>
                </div>
                <div class="text-sm font-semibold">{{ formatMoney(it.priceKopecks * it.qty) }}</div>
              </li>
            </ul>
            <dl class="mt-4 space-y-2 text-sm">
              <div class="flex justify-between">
                <dt class="text-gray-400">Товары</dt>
                <dd>{{ formatMoney(subtotal) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-400">Скидка</dt>
                <dd>− {{ formatMoney(discount) }}</dd>
              </div>
              <div class="flex justify-between text-base font-semibold pt-2 border-t border-gray-800">
                <dt>К оплате</dt>
                <dd>{{ formatMoney(total) }}</dd>
              </div>
            </dl>
          </div>
        </aside>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">

import { reactive, computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const CART_KEY = 'cart:v1'

// ===== Корзина (тот же формат, что и на CartPage.vue)
const state = reactive({ items: [] })
function loadCart() {
  try {
    const raw = localStorage.getItem(CART_KEY)
    state.items = raw ? JSON.parse(raw) : []
  } catch { state.items = [] }
}
const items = computed(() => state.items)
const subtotal = computed(() => items.value.reduce((s, it) => s + (it.priceKopecks * it.qty), 0))
const discount = computed(() => 0)
const total = computed(() => Math.max(subtotal.value - discount.value, 0))

function formatMoney(kopecks?: number) {
  const rub = (kopecks ?? 0) / 100
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(rub)
}

// ===== Форма
const form = reactive({ name: '', email: '', marketing: true })
const submitting = ref(false)
const error = ref('')

// простая проверка e-mail
function isEmail(s: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s)
}

async function submit() {
  if (submitting.value) return
  error.value = ''

  if (!items.value.length) { router.push('/'); return }
  form.name = form.name.trim()
  form.email = form.email.trim()

  if (!form.name) { error.value = 'Укажите имя'; return }
  if (!isEmail(form.email)) { error.value = 'Укажите корректный e-mail'; return }

  submitting.value = true
  try {
    const payload = {
      customer: { name: form.name, email: form.email },
      marketing_consent: !!form.marketing,
      items: items.value.map(it => ({ product_id: it.id, qty: it.qty }))
    }

    const res = await fetch('/php/payment/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })

    let data: any = null
    try { data = await res.json() } catch { /* ignore non-JSON */ }

    if (!res.ok || !data || data.ok === false) {
      const msg = (data && data.message) ? data.message : `HTTP ${res.status}`
      throw new Error(msg)
    }

    const url = data.payment_url
    if (!url) throw new Error('Ссылка на оплату не получена')

    window.location.assign(url)
  } catch (e: any) {
    error.value = 'Не удалось создать заказ: ' + (e?.message || 'ошибка сети')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadCart()
  // если корзина пуста — можно вернуть пользователя в каталог/корзину
  // if (!items.value.length) router.push('/cart')
})
</script>


<style scoped>
/* placeholder */
.checkout-page input::placeholder { color: #9ca3af; }

/* плавные ховеры/фокусы */
.checkout-page input {
  transition: box-shadow .2s ease, border-color .2s ease;
}
.checkout-page input:focus {
  box-shadow: 0 0 0 2px rgba(255,255,255,.15);
  border-color: #6b7280; /* gray-500 */
}

/* чекбокс выравниваем красиво */
.checkout-page input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: #ffffff; /* белый чек */
}

/* блок пустой корзины — чуть мягче фон + пунктир ярче */
.checkout-page .border-dashed {
  border-color: rgba(107,114,128,.5); /* gray-500/50 */
}
.checkout-page .bg-gray-900\/40 {
  backdrop-filter: blur(6px);
}

/* список товаров в сайдбаре */
.checkout-page ul li + li {
  /* доп. отступ уже есть через divide-y, оставим минимальный */
}

/* sticky фиксация и небольшой тень */
.checkout-page .sticky {
  position: sticky;
  top: 24px;
}
.checkout-page .rounded-2xl {
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
}

/* кнопка disabled */
button:disabled {
  cursor: not-allowed;
  opacity: .6;
}

/* сообщения об ошибках */
.checkout-page .text-red-300 {
  background: rgba(239, 68, 68, .08); /* red-500/8% */
  border: 1px solid rgba(239, 68, 68, .3);
  padding: 10px 12px;
  border-radius: 12px;
}

/* мелкие экраны — межстрочные отступы */
@media (max-width: 640px) {
  .checkout-page h1 { line-height: 1.25; }
}
</style>
