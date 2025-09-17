<template>
  <section class="admin-faq">
    <!-- Заголовок страницы -->
    <header class="page-head">
      <h1>Управление FAQ</h1>
      <p class="muted">Добавляйте, редактируйте и удаляйте вопросы-ответы.</p>
    </header>

    <!-- Форма -->
    <div class="card form-card" :class="{ loading: submitting }">
      <div class="form-head">
        <h2 class="fade-in">{{ mode === 'create' ? 'Новый вопрос' : 'Редактирование вопроса' }}</h2>
        <div class="form-actions">
          <button
            v-if="mode === 'create'"
            class="btn danger sm"
            type="button"
            @click="resetForm"
          >Закрыть</button>

          <button
            v-else
            class="btn danger sm"
            type="button"
            @click="cancelEdit"
          >Отмена</button>
        </div>
      </div>

      <form @submit.prevent="onSubmit" novalidate>
        <!-- Вопрос -->
        <div class="field">
          <label for="q">Вопрос <span class="req">*</span></label>
          <input
            id="q"
            v-model.trim="form.question"
            type="text"
            placeholder="Например: Как получить доступ к таблицам?"
            :class="{ invalid: touched.question && !valid.question }"
            @blur="touched.question = true"
            required
          />
          <small v-if="touched.question && !valid.question" class="error">Заполните вопрос.</small>
        </div>

        <!-- Ответ -->
        <div class="field">
          <label for="a">Ответ <span class="req">*</span></label>
          <textarea
            id="a"
            v-model.trim="form.answer"
            rows="4"
            placeholder="Коротко и по делу…"
            :class="{ invalid: touched.answer && !valid.answer }"
            @blur="touched.answer = true"
            required
          ></textarea>
          <small v-if="touched.answer && !valid.answer" class="error">Заполните ответ.</small>
        </div>

        <!-- Кнопки -->
        <div class="submit-row">
          <button
            v-if="mode === 'create'"
            class="btn--primary btn"
            type="submit"
            :disabled="!isFormValid || submitting"
          >
            {{ submitting ? 'Добавляем…' : 'Добавить' }}
          </button>

          <button
            v-else
            class="btn primary"
            type="submit"
            :disabled="!isFormValid || !isDirty || submitting"
          >
            {{ submitting ? 'Сохраняем…' : 'Сохранить' }}
          </button>

          <button
            v-if="mode === 'create'"
            class="btn danger sm"
            type="button"
            @click="resetForm"
          >Закрыть</button>

          <button
            v-else
            class="btn danger sm"
            type="button"
            @click="cancelEdit"
          >Закрыть</button>

          <span v-if="mode === 'edit' && !isDirty" class="muted small">Нет изменений — сохранять нечего</span>
        </div>
      </form>
    </div>

    <!-- Список вопросов (с перетаскиванием и кнопками порядка) -->
    <div class="card list-card">
      <div class="list-head">
        <h2>Вопросы-ответы</h2>
        <div class="list-tools">
          <input
            class="search"
            v-model.trim="search"
            type="text"
            placeholder="Поиск по вопросу/ответу…"
            @input="debouncedFetch"
          />
          <span class="muted">{{ total }} шт.</span>
        </div>
      </div>

      <div v-if="loading" class="empty">Загрузка…</div>
      <div v-else-if="!faqs.length" class="empty">
        Пока пусто. Добавьте первый вопрос выше ↑
      </div>

      <ul
        v-else
        class="faq-list"
        @dragover.prevent
      >
        <li
          v-for="(item, idx) in faqs"
          :key="item.id"
          class="faq-item pop-in"
          draggable="true"
          @dragstart="onDragStart(idx)"
          @dragenter.prevent="onDragEnter(idx)"
          @dragend="onDragEnd"
          :class="{ 'drag-over': dragOverIndex === idx }"
        >
          <div class="handle" title="Перетащите для изменения порядка">☰</div>

          <div class="qa">
            <h3 class="q">{{ item.question }}</h3>
            <p class="a">{{ item.answer }}</p>
          </div>

          <div class="order-actions">
            <button class="icon" :disabled="idx===0" @click="moveUp(idx)" title="Вверх">▲</button>
            <button class="icon" :disabled="idx===faqs.length-1" @click="moveDown(idx)" title="Вниз">▼</button>
          </div>

          <div class="actions">
            <button class="icon" title="Редактировать" @click="startEdit(item)">✏️</button>
            <button class="icon danger" title="Удалить" @click="confirmDelete(item)">🗑</button>
          </div>
        </li>
      </ul>

      <div v-if="pages > 1" class="pager">
        <button class="btn" :disabled="page<=1" @click="goto(page-1)">Назад</button>
        <span class="muted small">Стр. {{ page }} из {{ pages }}</span>
        <button class="btn" :disabled="page>=pages" @click="goto(page+1)">Вперёд</button>
      </div>

      <div class="save-order" v-if="orderDirty">
        <button class="btn primary" :disabled="savingOrder" @click="saveOrder">
          {{ savingOrder ? 'Сохраняем порядок…' : 'Сохранить порядок' }}
        </button>
        <button class="btn danger sm" :disabled="savingOrder" @click="discardOrder">Отменить перестановку</button>
      </div>
    </div>

    <!-- Тосты -->
    <transition name="toast">
      <div v-if="toast.show" class="toast" :class="toast.type">{{ toast.message }}</div>
    </transition>
  </section>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'

/* ===== API пути ===== */
const API = {
  list:   '/php/faq/list.php',
  create: '/php/faq/create.php',
  update: '/php/faq/update.php',
  delete: '/php/faq/delete.php',
  reorder: '/php/faq/reorder.php'
}

/* ===== Состояния ===== */
const faqs = reactive([])
const originalOrder = ref([])   // для отмены
const total = ref(0)
const page = ref(1)
const limit = ref(100)          // берём побольше, чтобы было удобно таскать
const pages = computed(() => Math.max(1, Math.ceil(total.value / limit.value)))
const search = ref('')

const loading = ref(false)
const submitting = ref(false)

const mode = ref('create') // create | edit
const editingId = ref(null)
const initialSnapshot = ref(null)

/* ===== Drag & Drop ===== */
const dragIndex = ref(-1)
const dragOverIndex = ref(-1)
const orderDirty = ref(false)
const savingOrder = ref(false)

function onDragStart(idx) {
  dragIndex.value = idx
}
function onDragEnter(idx) {
  dragOverIndex.value = idx
  if (dragIndex.value === -1 || dragIndex.value === idx) return
  // Мгновенно меняем местами (оптимистично)
  const moved = faqs.splice(dragIndex.value, 1)[0]
  faqs.splice(idx, 0, moved)
  dragIndex.value = idx
  orderDirty.value = true
}
function onDragEnd() {
  dragIndex.value = -1
  dragOverIndex.value = -1
}

function moveUp(idx) {
  if (idx <= 0) return
  ;[faqs[idx-1], faqs[idx]] = [faqs[idx], faqs[idx-1]]
  orderDirty.value = true
}
function moveDown(idx) {
  if (idx >= faqs.length - 1) return
  ;[faqs[idx], faqs[idx+1]] = [faqs[idx+1], faqs[idx]]
  orderDirty.value = true
}

async function saveOrder() {
  savingOrder.value = true
  try {
    const order = faqs.map(f => f.id)
    const res = await fetch(API.reorder, {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ order })
    })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось сохранить порядок')
    originalOrder.value = order.slice()
    orderDirty.value = false
    notify('Порядок сохранён.', 'success')
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    savingOrder.value = false
  }
}

function discardOrder() {
  if (!originalOrder.value?.length) return fetchList()
  // Восстанавливаем порядок по originalOrder
  const byId = new Map(faqs.map(f => [f.id, f]))
  const restored = []
  for (const id of originalOrder.value) {
    if (byId.has(id)) restored.push(byId.get(id))
  }
  // добросить, если вдруг есть новые
  for (const f of faqs) if (!originalOrder.value.includes(f.id)) restored.push(f)
  faqs.splice(0, faqs.length, ...restored)
  orderDirty.value = false
}

/* ===== Форма ===== */
const blankForm = () => ({ question: '', answer: '' })
const form = reactive(blankForm())
const touched = reactive({ question: false, answer: false })

const valid = reactive({
  get question () { return form.question.trim().length > 0 },
  get answer () { return form.answer.trim().length > 0 }
})
const isFormValid = computed(() => valid.question && valid.answer)

function snapshotForm () {
  return JSON.stringify({ question: form.question.trim(), answer: form.answer.trim() })
}
const isDirty = computed(() => {
  if (mode.value === 'create') return isFormValid.value
  if (!initialSnapshot.value) return true
  return snapshotForm() !== initialSnapshot.value
})

/* ===== Тост ===== */
const toast = reactive({ show:false, type:'info', message:'' })
let toastTimer = null
function notify (message, type='info', ms=2200) {
  toast.message = message
  toast.type = type
  toast.show = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.show = false }, ms)
}

/* ===== CRUD ===== */
async function fetchList () {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
    })
    if (search.value) params.set('q', search.value)

    const res = await fetch(`${API.list}?${params.toString()}`)
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Ошибка загрузки')

    faqs.splice(0, faqs.length, ...(data.items || []))
    total.value = data.total || faqs.length

    // Сохраняем исходный порядок (для отмены)
    originalOrder.value = faqs.map(f => f.id)
    orderDirty.value = false
  } catch (e) {
    notify(`Не удалось загрузить: ${e.message}`, 'error', 3000)
  } finally {
    loading.value = false
  }
}

async function createFaq () {
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('question', form.question.trim())
    fd.append('answer', form.answer.trim())

    const res = await fetch(API.create, { method:'POST', body:fd })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось добавить')

    // Новый элемент в конец списка (последний по порядку)
    faqs.push(data.faq)
    total.value += 1
    orderDirty.value = true
    notify('Вопрос добавлен. Не забудьте сохранить порядок.', 'success')
    resetForm()
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    submitting.value = false
  }
}

async function updateFaq () {
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('id', String(editingId.value))
    fd.append('question', form.question.trim())
    fd.append('answer', form.answer.trim())

    const res = await fetch(API.update, { method:'POST', body:fd })
    const data = await res.json()

    if (!data.ok) {
      notify(data.message || 'Нет изменений', data.message?.includes('ничего не меняли') ? 'warn' : 'error')
      if (data.message?.includes('ничего не меняли')) return
      return
    }

    const idx = faqs.findIndex(f => f.id === data.faq.id)
    if (idx !== -1) {
      // сохраняем текущую позицию
      const keepOrder = faqs[idx]
      faqs[idx] = { ...data.faq, sort_order: keepOrder.sort_order }
    }

    initialSnapshot.value = snapshotForm()
    notify('Изменения сохранены.', 'success')
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    submitting.value = false
  }
}

async function deleteFaq (item) {
  try {
    const res = await fetch(API.delete, {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ id: item.id })
    })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось удалить')

    const idx = faqs.findIndex(f => f.id === item.id)
    if (idx !== -1) faqs.splice(idx, 1)
    total.value = Math.max(0, total.value - 1)
    orderDirty.value = true
    notify('Удалено. Сохраните порядок при необходимости.', 'success')
  } catch (e) {
    notify(`Ошибка удаления: ${e.message}`, 'error', 3000)
  }
}

/* ===== Хэндлеры формы ===== */
async function onSubmit () {
  touched.question = touched.answer = true
  if (!isFormValid.value) return notify('Проверьте форму — есть ошибки.', 'error')
  if (mode.value === 'create') {
    await createFaq()
  } else {
    if (!isDirty.value) return notify('Вы ничего не меняли. Сохранять нечего.', 'warn')
    await updateFaq()
  }
}

function startEdit (item) {
  mode.value = 'edit'
  editingId.value = item.id
  form.question = item.question
  form.answer = item.answer
  touched.question = touched.answer = false
  initialSnapshot.value = snapshotForm()
  notify('Режим редактирования.', 'info')
}

function cancelEdit () {
  if (initialSnapshot.value) {
    const snap = JSON.parse(initialSnapshot.value)
    form.question = snap.question
    form.answer = snap.answer
  } else {
    resetForm()
  }
  mode.value = 'create'
  editingId.value = null
  notify('Редактирование отменено.', 'info')
}

function resetForm () {
  Object.assign(form, blankForm())
  touched.question = touched.answer = false
  mode.value = 'create'
  editingId.value = null
  initialSnapshot.value = null
}

/* ===== Поиск (дебаунс) ===== */
let debounceTimer = null
function debouncedFetch () {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    page.value = 1
    fetchList()
  }, 300)
}

function goto (p) {
  page.value = Math.min(Math.max(1, p), pages.value)
  fetchList()
}

function confirmDelete (item) {
  const ok = window.confirm(`Удалить вопрос «${item.question}»?`)
  if (!ok) return
  deleteFaq(item)
}

/* ===== Init ===== */
onMounted(fetchList)
</script>

<style scoped>
/* ===== Тёмный фон и базовые цвета ===== */
.admin-faq {
  --panel: rgba(17, 16, 27, .72);
  --panel-2: rgba(22, 21, 35, .78);
  --text: #E9ECF5;
  --muted: #9AA3B2;
  --border: #2B2F44;
  --primary: #7A5CFF;
  --primary-600: #6248D6;
  --error: #ff6b6b;
  --warn: #ffb020;
  --success: #27c093;

  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(255,153,0,.08), transparent 60%),
    radial-gradient(800px 400px at 90% 10%, rgba(135,77,255,.1), transparent 55%),
    linear-gradient(160deg, #0f0b1a, #1b1230 45%, #2a1545 70%, #35185a);
  color: var(--text);
  min-height: 100vh;
    padding: 120px 25px;
}

.page-head h1 {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: .2px;
  text-shadow: 0 8px 30px rgba(0,0,0,.35);
}
.muted { color: var(--muted); }
.small { font-size: 12px; }

/* ===== Карточки ===== */
.card {
  background: linear-gradient(180deg, var(--panel), var(--panel-2));
  backdrop-filter: saturate(120%) blur(6px);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px;
  margin-top: 18px;
  box-shadow: 0 10px 36px rgba(0,0,0,.35);
  transition: transform .12s ease, box-shadow .2s ease, border-color .2s ease;
}
.card:hover { transform: translateY(-1px); box-shadow: 0 12px 40px rgba(0,0,0,.38); }
.form-card { max-width: 980px; }
.form-card.loading { opacity: .7; pointer-events: none; }

/* ===== Формы ===== */
.form-head {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 10px;
}
.form-head h2 { margin: 0; font-size: 20px; font-weight: 700; }

.field { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }

label { font-size: 14px; color: var(--muted); }
.req { color: var(--warn); }

input[type="text"],
textarea {
  background: rgba(7, 9, 15, .6);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
  outline: none;
  font-size: 14px;
  transition: border-color .15s ease, box-shadow .15s ease, background .15s ease, transform .06s ease;
}
textarea { resize: vertical; }

input:focus,
textarea:focus {
  border-color: rgba(122,92,255,.65);
  box-shadow: 0 0 0 3px rgba(122,92,255,.15);
}

input.invalid,
textarea.invalid {
  border-color: var(--error);
  box-shadow: 0 0 0 3px rgba(255,107,107,.14);
}

/* ===== Кнопки ===== */
.submit-row {
  margin-top: 16px;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}


/* ===== Список ===== */
.list-head {
  display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 8px;
}
.list-tools {
  display: flex; gap: 10px; align-items: center;
}
.search {
  background: rgba(7, 9, 15, .6);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 8px 12px;
  outline: none;
}

.empty {
  border: 1px dashed var(--border);
  color: var(--muted);
  padding: 18px;
  border-radius: 12px;
  text-align: center;
}

.faq-list {
  display: flex; flex-direction: column; gap: 12px;
}
.faq-item {
  background: rgba(8,10,16,.72);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 12px;
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  gap: 10px;
  transition: transform .12s ease, box-shadow .18s ease, border-color .18s ease;
}
.faq-item:hover { transform: translateY(-2px); box-shadow: 0 14px 40px rgba(0,0,0,.35); border-color: #3a3f57; }
.faq-item.drag-over { outline: 2px dashed rgba(122,92,255,.6); outline-offset: 2px; }

.handle {
  user-select: none;
  cursor: grab;
  align-self: start;
  font-size: 18px;
  padding: 6px 8px;
  color: #9AA3B2;
  background: rgba(0,0,0,.25);
  border: 1px solid var(--border);
  border-radius: 10px;
}
.handle:active { cursor: grabbing; }

.qa .q { margin: 0 0 6px; font-size: 16px; font-weight: 800; }
.qa .a { margin: 0; color: var(--muted); font-size: 14px; }

.order-actions {
  display: flex; flex-direction: column; gap: 6px; align-items: stretch;
}
.actions {
  display: flex; gap: 6px; align-items: start;
}
.icon {
  background: rgba(0,0,0,.45);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 6px 8px;
  border-radius: 10px;
  cursor: pointer;
  transition: background .15s ease, transform .08s ease;
}
.icon:hover { background: rgba(255,255,255,.08); transform: translateY(-1px); }
.icon.danger:hover { background: rgba(255,0,0,.12); }

/* Пагинация */
.pager {
  display: flex; gap: 12px; align-items: center; justify-content: center;
  margin-top: 12px;
}

/* Панель сохранения порядка */
.save-order {
  margin-top: 12px;
  display: flex; gap: 10px; align-items: center; justify-content: flex-end;
}

/* Тосты */
.toast {
  position: fixed;
  right: 18px;
  top: 18px;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: rgba(10,12,18,.86);
  box-shadow: 0 10px 30px rgba(0,0,0,.3);
  z-index: 9999;
}
.toast.success { border-color: rgba(39,192,147,.4); }
.toast.error { border-color: rgba(255,107,107,.4); }
.toast.warn { border-color: rgba(255,176,32,.4); }
.toast.info { border-color: rgba(122,92,255,.4); }

.toast-enter-active, .toast-leave-active { transition: all .18s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px); }

@keyframes popIn { from { opacity: 0; transform: translateY(6px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.pop-in { animation: popIn .24s ease both; }
.fade-in { animation: fadeIn .2s ease both; }
@keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
</style>
