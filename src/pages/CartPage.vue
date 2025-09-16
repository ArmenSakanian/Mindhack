<template>
  <section class="cart-page">
    <div class="container mx-auto px-4 py-8 lg:py-10">
      <!-- Заголовок -->
      <header class="mb-6 lg:mb-8">
        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">Корзина</h1>
        <p class="text-sm text-gray-400 mt-1">Проверьте товары и перейдите к оформлению заказа.</p>
      </header>

      <!-- Пустое состояние -->
      <div v-if="!items.length" class="text-center py-16 border border-dashed border-gray-700/60 rounded-2xl bg-gray-900/40">
        <div class="text-5xl mb-3">🛒</div>
        <p class="text-lg font-medium">В вашей корзине пока пусто</p>
        <p class="text-gray-400 mt-1">Добавьте товары из каталога и вернитесь сюда.</p>
        <router-link to="/" class="inline-flex items-center justify-center mt-6 px-5 h-11 rounded-xl bg-white/10 hover:bg-white/20 transition focus:outline-none focus:ring-2 focus:ring-white/40">
          Вернуться в каталог
        </router-link>
      </div>

      <!-- Контент корзины -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
        <!-- Листинг позиций -->
        <div class="lg:col-span-8">
          <div class="divide-y divide-gray-800 rounded-2xl border border-gray-800 bg-gray-900/40">
            <div
              v-for="(it, idx) in items"
              :key="it.id"
              class="p-4 sm:p-5 flex items-start gap-4">
              <!-- Фото -->
              <div class="w-20 h-20 sm:w-24 sm:h-24 flex-shrink-0 overflow-hidden rounded-xl bg-gray-800">
                <img v-if="it.image" :src="it.image" :alt="it.name" class="w-full h-full object-cover" />
              </div>

              <!-- Описание -->
              <div class="flex-1 min-w-0">
                <h3 class="font-semibold leading-tight truncate">{{ it.name }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">ID: {{ it.id }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                  <span class="inline-flex items-center rounded-lg border border-gray-700 px-2.5 h-8 text-sm">
                    {{ formatMoney(it.priceKopecks) }} / шт.
                  </span>

                  <!-- Кол-во -->
                  <div class="inline-flex items-center rounded-lg border border-gray-700 overflow-hidden">
                    <button class="px-3 h-8 hover:bg-white/10" @click="decrement(idx)">−</button>
                    <input
                      class="w-12 h-8 text-center bg-transparent outline-none"
                      type="number"
                      min="1"
                      :value="it.qty"
                      @input="onQtyInput($event, idx)"
                    />
                    <button class="px-3 h-8 hover:bg-white/10" @click="increment(idx)">+</button>
                  </div>

                  <!-- Сумма строки -->
                  <span class="ml-auto font-semibold">{{ formatMoney(it.priceKopecks * it.qty) }}</span>
                </div>

                <!-- Действия -->
                <div class="mt-3 flex items-center gap-3">
                  <button class="text-red-300/90 hover:text-red-200 text-sm" @click="remove(idx)">Удалить</button>
                  <button class="text-gray-400 hover:text-gray-200 text-sm" @click="saveForLater(idx)">Отложить</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Кнопка очистки -->
          <div class="mt-4 flex justify-between">
            <button class="text-gray-400 hover:text-gray-200 text-sm" @click="clearCart">Очистить корзину</button>
            <router-link to="/checkout" class="inline-flex items-center justify-center px-5 h-11 rounded-xl bg-white text-gray-900 font-semibold hover:brightness-95 transition focus:outline-none focus:ring-2 focus:ring-white/40">
              Перейти к оформлению
            </router-link>
          </div>
        </div>

        <!-- Итог -->
        <aside class="lg:col-span-4">
          <div class="rounded-2xl border border-gray-800 bg-gray-900/40 p-5 sticky top-6">
            <h2 class="text-lg font-semibold">Итого</h2>
            <dl class="mt-3 space-y-2 text-sm">
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

            <router-link
              to="/checkout"
              class="mt-4 w-full inline-flex items-center justify-center px-5 h-11 rounded-xl bg-white text-gray-900 font-semibold hover:brightness-95 transition focus:outline-none focus:ring-2 focus:ring-white/40 disabled:opacity-50 disabled:pointer-events-none"
              :class="{ 'opacity-50 pointer-events-none': !items.length }"
            >
              Перейти к оформлению
            </router-link>

            <p class="text-xs text-gray-500 mt-3">Нажимая «Перейти к оформлению», вы сможете указать e‑mail и завершить оплату безопасно через Тинькофф.</p>
          </div>
        </aside>
      </div>
    </div>
  </section>
</template>

<script setup>
import { reactive, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const STORAGE_KEY = 'cart:v1' // ключ в localStorage
const router = useRouter()

// Структура элемента корзины:
// { id, name, priceKopecks, image, qty }
const state = reactive({ items: [] })

function loadCart() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    state.items = raw ? JSON.parse(raw) : []
  } catch {
    state.items = []
  }
}
function persist() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state.items))
}

onMounted(loadCart)
watch(() => state.items, persist, { deep: true })

const items = computed(() => state.items)

// Форматирование денег (рубли). На бэке — копейки.
function formatMoney(kopecks) {
  const rub = (kopecks ?? 0) / 100
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(rub)
}

const subtotal = computed(() => items.value.reduce((s, it) => s + (it.priceKopecks * it.qty), 0))
const discount = computed(() => 0) // место под промокоды, если появятся
const total = computed(() => Math.max(subtotal.value - discount.value, 0))

function increment(idx) {
  const it = items.value[idx]; if (!it) return
  it.qty = Math.min(999, (it.qty || 1) + 1)
}
function decrement(idx) {
  const it = items.value[idx]; if (!it) return
  it.qty = Math.max(1, (it.qty || 1) - 1)
}
function onQtyInput(e, idx) {
  const v = Math.max(1, Math.min(999, parseInt(e.target.value || '1', 10)))
  items.value[idx].qty = v
}
function remove(idx) {
  items.value.splice(idx, 1)
}
function clearCart() {
  state.items = []
}
function saveForLater(idx) {
  // Заглушка под будущий список «Отложенные»
  items.value.splice(idx, 1)
}

// Глобальная утилита добавления в корзину (можно вызывать из карточек товара)
// Пример: window.addToCart({ id, name, priceKopecks, image, qty: 1 })
if (!window.addToCart) {
  window.addToCart = (product) => {
    const existing = state.items.find(i => i.id === product.id)
    if (existing) existing.qty = Math.min(999, existing.qty + (product.qty || 1))
    else state.items.push({ id: product.id, name: product.name, priceKopecks: product.priceKopecks, image: product.image || '', qty: product.qty || 1 })
    persist()
  }
}
</script>

<style scoped>
/***** Небольшие косметические улучшения *****/
.cart-page :is(input[type="number"])::-webkit-outer-spin-button,
.cart-page :is(input[type="number"])::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.cart-page :is(input[type="number"]) { -moz-appearance: textfield; }
</style>
