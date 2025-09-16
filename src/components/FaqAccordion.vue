<template>
  <section class="faq" id="faq" aria-labelledby="faq-title">
    <div class="faq__container">
      <header class="faq__head">
        <h2 id="faq-title" class="faq__title">Часто задаваемые вопросы</h2>
        <p class="faq__subtitle">Всё, что нужно знать про покупку и использование таблиц</p>
      </header>

      <!-- Состояния -->
      <div v-if="loading" class="faq__state">Загрузка…</div>
      <div v-else-if="error" class="faq__state faq__state--error">{{ error }}</div>
      <div v-else-if="!faqs.length" class="faq__state">Пока нет вопросов.</div>

      <ul v-else class="faq__list">
        <li
          v-for="(item, i) in faqs"
          :key="item.id"
          class="faq__item"
          :class="{ open: openIndex === i }"
        >
          <h3 class="faq__q">
            <button
              class="faq__btn"
              type="button"
              :aria-expanded="openIndex === i"
              :aria-controls="'faq-panel-' + item.id"
              @click="toggle(i)"
              @keydown.enter.prevent="toggle(i)"
              @keydown.space.prevent="toggle(i)"
            >
              <span>{{ item.question }}</span>
              <span class="faq__icon" aria-hidden="true">+</span>
            </button>
          </h3>

          <transition
            @before-enter="beforeEnter"
            @enter="enter"
            @after-enter="afterEnter"
            @before-leave="beforeLeave"
            @leave="leave"
            @after-leave="afterLeave"
          >
            <div
              v-show="openIndex === i"
              class="faq__panel"
              :id="'faq-panel-' + item.id"
              role="region"
              :aria-hidden="openIndex !== i"
            >
              <div class="faq__panel-inner">
                <p class="faq__answer" v-html="item.answer"></p>
              </div>
            </div>
          </transition>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'

// API
const API_LIST = '/php/faq/list.php'

// state
const faqs = ref([])
const loading = ref(false)
const error = ref('')
const openIndex = ref(-1)

// Кэш активных анимаций (чтобы останавливать/не накладывались)
const active = new WeakMap()

function stopAnimation(el) {
  const a = active.get(el)
  if (!a) return
  a.cancel()
  active.delete(el)
}

function nextFrame() {
  return new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))
}

// ===== АНИМАЦИЯ «ШТОРКА» БЕЗ ЛАГОВ =====
// Суть: 1) фиксируем текущую высоту, 2) измеряем целевую, 3) один CSS transition.
const D_OPEN = 420   // ms
const D_CLOSE = 360  // ms
const EASE_OUT = 'cubic-bezier(.16,1,.3,1)'
const EASE_IN  = 'cubic-bezier(.4,0,.2,1)'

function beforeEnter(el) {
  stopAnimation(el)
  el.style.height = '0px'
  el.style.opacity = '0'
  el.style.overflow = 'hidden'
  el.style.willChange = 'height, opacity'
}

async function enter(el) {
  // Измеряем цель до включения transition
  const target = el.scrollHeight

  // Отложим на 2 кадра чтобы браузер применил стартовые стили (без «скачков»)
  await nextFrame()

  // Настраиваем трансишн один раз
  el.style.transition = `height ${D_OPEN}ms ${EASE_OUT}, opacity 240ms ease-out`

  // Запуск анимации
  el.style.height = target + 'px'
  el.style.opacity = '1'

  // Ставим «фальшивый» Animation, чтобы уметь отменять (единая точка управления)
  const timer = setTimeout(() => {
    active.delete(el)
  }, D_OPEN)
  active.set(el, { cancel: () => clearTimeout(timer) })
}

function afterEnter(el) {
  stopAnimation(el)
  el.style.transition = ''
  el.style.height = 'auto'   // чтобы внутри можно было динамически расти
  el.style.opacity = '1'
  el.style.overflow = ''
  el.style.willChange = ''
}

function beforeLeave(el) {
  stopAnimation(el)
  // Фиксируем текущую высоту как старт
  el.style.height = el.scrollHeight + 'px'
  el.style.opacity = '1'
  el.style.overflow = 'hidden'
  el.style.willChange = 'height, opacity'
}

async function leave(el) {
  await nextFrame()
  el.style.transition = `height ${D_CLOSE}ms ${EASE_IN}, opacity 220ms ease-in`
  el.style.height = '0px'
  el.style.opacity = '0'

  const timer = setTimeout(() => {
    active.delete(el)
  }, D_CLOSE)
  active.set(el, { cancel: () => clearTimeout(timer) })
}

function afterLeave(el) {
  stopAnimation(el)
  el.style.transition = ''
  el.style.height = ''
  el.style.opacity = ''
  el.style.overflow = ''
  el.style.willChange = ''
}

function toggle(i) {
  openIndex.value = openIndex.value === i ? -1 : i
}

async function fetchFaqs() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${API_LIST}?page=1&limit=100`)
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Ошибка загрузки')
    faqs.value = Array.isArray(data.items) ? data.items : []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить FAQ'
  } finally {
    loading.value = false
  }
}

onMounted(fetchFaqs)
</script>

<style scoped>
/* ===== Фон и шапка (как у тебя) ===== */
.faq {
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
    linear-gradient(180deg, #0f0b1a, #121b30 45%, #153345 70%, #183e5a);
  padding: 40px 16px;
}
.faq__container { max-width: 900px; margin: 0 auto; }
.faq__head { margin-bottom: 24px; text-align: center; }
.faq__title {
  font-size: 28px; font-weight: 700; margin: 0 0 8px;
  color: var(--accent-color, #ff9900); letter-spacing: .5px;
}
.faq__subtitle { color: #eee; margin: 0; font-size: 15px; }

/* ===== Состояния ===== */
.faq__state {
  border: 1px dashed #333; color: #ddd; padding: 18px;
  border-radius: 10px; text-align: center;
}
.faq__state--error { color: #ff6b6b; border-color: #663; }

/* ===== Список ===== */
.faq__list {
  display: grid; gap: 12px; padding: 0; margin: 0; list-style: none;
}
.faq__item {
  border: 1px solid #333; border-radius: 10px; overflow: hidden; background: #fff;
  transition: border-color .2s ease, box-shadow .2s ease;
}
.faq__item.open { border-color: var(--accent-color, #ff9900); box-shadow: 0 4px 14px rgba(0,0,0,.1); }

/* ===== Кнопка вопроса ===== */
.faq__btn {
  width: 100%; text-align: left; padding: 18px 20px; background: none; border: none;
  font-size: 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center;
  cursor: pointer; color: #222;
}
.faq__btn:focus { outline: none; background: #f7f7f7; }
.faq__icon { font-size: 20px; transition: transform .28s ease, color .28s ease; }
.faq__item.open .faq__icon { transform: rotate(45deg); color: var(--accent-color, #ff9900); }

/* ===== Панель ответа (ключ к производительности) ===== */
.faq__panel {
  /* НИЧЕГО тяжёлого — только overflow/contain/will-change для плавности */
  overflow: hidden;
  contain: layout paint;           /* изолируем перерисовку */
  will-change: height, opacity;    /* подсказка браузеру */
  transform-origin: top;           /* ощущение «шторки сверху» */
  padding: 0 20px;                 /* боковые отступы держим здесь */
  /* ВАЖНО: нижний паддинг на внутреннем блоке, чтобы не «скакала» высота при расчёте */
}

.faq__panel-inner {
  padding-bottom: 18px;
}

.faq__answer {
  margin-top: 10px; font-size: 15.5px; color: #444; line-height: 1.6;
}
.faq__answer b { color: #000; }
.faq__answer a {
  color: #007bff; text-decoration: underline; text-underline-offset: 2px; font-weight: 500;
}
</style>00--