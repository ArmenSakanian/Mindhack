<template>
  <section class="admin-categories">
    <!-- Заголовок страницы -->
    <header class="page-head">
      <h1>Управление категориями</h1>
      <p class="muted">Добавляйте, редактируйте и удаляйте категории для каталога.</p>
    </header>

    <!-- Форма -->
    <div class="card form-card" :class="{ loading: submitting }">
      <div class="form-head">
        <h2 class="fade-in">{{ mode === 'create' ? 'Новая категория' : 'Редактирование категории' }}</h2>
        <div class="form-actions">
          <button
            v-if="mode === 'create'"
            class="btn ghost"
            type="button"
            @click="resetForm()"
          >Закрыть</button>

          <button
            v-else
            class="btn ghost"
            type="button"
            @click="cancelEdit"
          >Отмена</button>
        </div>
      </div>

      <form @submit.prevent="onSubmit" novalidate>
        <!-- Заголовок -->
        <div class="field">
          <label for="title">Заголовок <span class="req">*</span></label>
          <input
            id="title"
            v-model.trim="form.title"
            type="text"
            placeholder="Например: Финансы"
            :class="{ invalid: touched.title && !valid.title }"
            @blur="touched.title = true"
            required
          />
          <small v-if="touched.title && !valid.title" class="error">Заполните заголовок.</small>
        </div>

        <!-- Подзаголовок -->
        <div class="field">
          <label for="subtitle">Подзаголовок <span class="req">*</span></label>
          <input
            id="subtitle"
            v-model.trim="form.subtitle"
            type="text"
            placeholder="Короткое уточнение"
            :class="{ invalid: touched.subtitle && !valid.subtitle }"
            @blur="touched.subtitle = true"
            required
          />
          <small v-if="touched.subtitle && !valid.subtitle" class="error">Заполните подзаголовок.</small>
        </div>

        <!-- Описание -->
        <div class="field">
          <label for="description">Описание <span class="req">*</span></label>
          <textarea
            id="description"
            v-model.trim="form.description"
            rows="4"
            placeholder="Опишите категорию..."
            :class="{ invalid: touched.description && !valid.description }"
            @blur="touched.description = true"
            required
          ></textarea>
          <small v-if="touched.description && !valid.description" class="error">Заполните описание.</small>
        </div>

        <!-- Цена -->
        <div class="field inline">
          <div class="field w-50">
            <label for="price">Цена (₽) <span class="req">*</span></label>
            <input
              id="price"
              v-model.number="form.price"
              type="number"
              min="0.01"
              step="0.01"
              placeholder="0.00"
              :class="{ invalid: touched.price && !valid.price }"
              @blur="touched.price = true"
              required
            />
            <small v-if="touched.price && !valid.price" class="error">Введите цену больше 0.</small>
          </div>
        </div>

        <!-- Ключевые слова -->
        <div class="field">
          <label>Ключевые слова <span class="req">*</span></label>

          <div class="kw-row">
            <input
              v-model.trim="keywordInput"
              type="text"
              :placeholder="kwPlaceholder"
              :maxlength="100"
              @keydown.enter.prevent="addKeyword"
              :disabled="form.keywords.length >= KW_MAX"
            />
            <button
              type="button"
              class="btn"
              @click="addKeyword"
              :disabled="keywordInput.length === 0 || form.keywords.length >= KW_MAX"
              title="Добавить ключевое слово"
            >Добавить</button>
            <span class="kw-count">{{ form.keywords.length }}/{{ KW_MAX }}</span>
          </div>

          <div class="chips" v-if="form.keywords.length">
            <span class="chip" v-for="(kw, i) in form.keywords" :key="kw">
              {{ kw }}
              <button class="x" type="button" aria-label="Удалить" @click="removeKeyword(i)">×</button>
            </span>
          </div>

          <small v-if="touched.keywords && !valid.keywords" class="error">
            Нужно от {{ KW_MIN }} до {{ KW_MAX }} ключевых слов.
          </small>
        </div>

        <!-- Фото -->
        <div class="field">
          <label for="image">Фото категории <span class="req">*</span></label>
          <input
            id="image"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            @change="onImageChange"
            :class="{ invalid: touched.image && !valid.image }"
            @blur="touched.image = true"
          />
          <small class="hint">PNG / JPG / WEBP, до 5 МБ.</small>
          <small v-if="touched.image && !valid.image" class="error">Загрузите корректное изображение.</small>

          <div v-if="form.imagePreview" class="preview">
            <img :src="form.imagePreview" alt="Предпросмотр" />
          </div>
        </div>

        <!-- Кнопки отправки -->
        <div class="submit-row">
          <button
            v-if="mode === 'create'"
            class="btn primary"
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
            title="Сохранить изменения"
          >
            {{ submitting ? 'Сохраняем…' : 'Сохранить' }}
          </button>

          <button
            v-if="mode === 'create'"
            class="btn ghost"
            type="button"
            @click="resetForm()"
          >Закрыть</button>

          <button
            v-else
            class="btn ghost"
            type="button"
            @click="cancelEdit"
          >Закрыть</button>

          <span v-if="mode === 'edit' && !isDirty" class="muted small">Нет изменений — сохранять нечего</span>
        </div>
      </form>
    </div>

    <!-- Список категорий -->
    <div class="card list-card">
      <div class="list-head">
        <h2>Категории</h2>
        <span class="muted">{{ categories.length }} шт.</span>
      </div>

      <div v-if="loading" class="empty">Загрузка…</div>
      <div v-else-if="!categories.length" class="empty">
        Пока нет категорий. Добавьте первую выше ↑
      </div>

      <div v-else class="h-list">
        <article class="cat-card pop-in" v-for="cat in categories" :key="cat.id">
          <div class="thumb">
            <img :src="cat.image_url || cat.image || ''" alt="" />
          </div>
          <div class="meta">
            <h3>{{ cat.title }}</h3>
            <p class="price">{{ formatPrice(cat.price) }}</p>
            <p class="subtitle">{{ cat.subtitle }}</p>
            <div class="kw-wrap">
              <span class="chip small" v-for="kw in cat.keywords" :key="kw">{{ kw }}</span>
            </div>
          </div>
          <div class="actions">
            <button class="icon" title="Редактировать" @click="startEdit(cat)">✏️</button>
            <button class="icon danger" title="Удалить" @click="confirmDelete(cat)">🗑</button>
          </div>
        </article>
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

/* ===== API пути (правь при необходимости) ===== */
const API = {
  list:  '/php/categories/list.php',
  create:'/php/categories/create.php',
  update:'/php/categories/update.php',
  delete:'/php/categories/delete.php',
}

/* ===== Константы ===== */
const KW_MIN = 1
const KW_MAX = 4
const IMAGE_MAX_BYTES = 5 * 1024 * 1024
const ACCEPTED_TYPES = ['image/png', 'image/jpeg', 'image/webp']

/* ===== Состояние ===== */
const categories = reactive([])     // приходит из БД
const loading = ref(false)
const submitting = ref(false)

const mode = ref('create') // 'create' | 'edit'
const editingId = ref(null)
const initialSnapshot = ref(null) // для проверки изменений

const blankForm = () => ({
  title: '',
  subtitle: '',
  description: '',
  price: null,
  keywords: [],
  imageFile: null,
  imagePreview: ''
})

const form = reactive(blankForm())
const touched = reactive({
  title: false,
  subtitle: false,
  description: false,
  price: false,
  keywords: false,
  image: false
})

/* ===== Ключевые слова ===== */
const keywordInput = ref('')
const kwPlaceholder = computed(() =>
  form.keywords.length >= KW_MAX ? 'Достигнут лимит' : 'Введите слово и нажмите «Добавить»'
)

function addKeyword () {
  const val = keywordInput.value.trim()
  if (!val) return
  if (form.keywords.length >= KW_MAX) {
    return notify('Максимум 4 ключевых слова', 'warn')
  }
  const lower = val.toLowerCase()
  const exists = form.keywords.some(k => k.toLowerCase() === lower)
  if (exists) {
    return notify('Такое ключевое слово уже добавлено', 'warn')
  }
  form.keywords.push(val)
  keywordInput.value = ''
  touched.keywords = true
}

function removeKeyword (index) {
  form.keywords.splice(index, 1)
  touched.keywords = true
}

/* ===== Изображение ===== */
function onImageChange (e) {
  const file = e.target.files?.[0]
  if (!file) return

  if (!ACCEPTED_TYPES.includes(file.type)) {
    touched.image = true
    return notify('Недопустимый формат. Разрешены PNG/JPG/WEBP.', 'error')
  }
  if (file.size > IMAGE_MAX_BYTES) {
    touched.image = true
    return notify('Файл слишком большой (до 5 МБ).', 'error')
  }

  form.imageFile = file
  form.imagePreview = URL.createObjectURL(file)
  touched.image = true
}

/* ===== Валидация ===== */
const valid = reactive({
  get title () { return form.title.length > 0 },
  get subtitle () { return form.subtitle.length > 0 },
  get description () { return form.description.length > 0 },
  get price () { return typeof form.price === 'number' && form.price > 0 },
  get keywords () { return form.keywords.length >= KW_MIN && form.keywords.length <= KW_MAX },
  get image () { return mode.value === 'edit' ? !!form.imagePreview : !!form.imagePreview } // в create обязателен превью; в edit уже есть prev от сервера
})

const isFormValid = computed(() =>
  valid.title && valid.subtitle && valid.description && valid.price && valid.keywords && valid.image
)

function snapshotForm (obj = form) {
  return JSON.stringify({
    title: obj.title,
    subtitle: obj.subtitle,
    description: obj.description,
    price: obj.price,
    keywords: obj.keywords.slice(),
    imagePreview: obj.imagePreview
  })
}

const isDirty = computed(() => {
  if (mode.value === 'create') return isFormValid.value
  if (!initialSnapshot.value) return true
  return snapshotForm() !== initialSnapshot.value
})

/* ===== CRUD с сервером ===== */
async function fetchList () {
  loading.value = true
  try {
    const res = await fetch(`${API.list}?page=1&limit=100`)
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Ошибка загрузки')
    categories.splice(0, categories.length, ...data.items)
  } catch (e) {
    notify(`Не удалось загрузить категории: ${e.message}`, 'error', 3000)
  } finally {
    loading.value = false
  }
}

function toAbsoluteUrl(relOrAbs) {
  if (!relOrAbs) return ''
  if (relOrAbs.startsWith('http')) return relOrAbs
  // делаем абсолютный от текущего домена
  const origin = window.location.origin
  const path = relOrAbs.startsWith('/') ? relOrAbs : `/${relOrAbs}`
  return origin + path
}

async function createCategory () {
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('title', form.title)
    fd.append('subtitle', form.subtitle)
    fd.append('description', form.description)
    fd.append('price', String(form.price))
    fd.append('keywords', JSON.stringify(form.keywords))
    if (form.imageFile) fd.append('image', form.imageFile)

    const res = await fetch(API.create, { method: 'POST', body: fd })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось добавить категорию')

    // Сервер от create возвращает category с относительным image
    const cat = data.category
    cat.image_url = toAbsoluteUrl(cat.image)
    categories.unshift(cat)

    notify('Категория добавлена.', 'success')
    resetForm()
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    submitting.value = false
  }
}

async function updateCategory () {
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('id', String(editingId.value))
    fd.append('title', form.title)
    fd.append('subtitle', form.subtitle)
    fd.append('description', form.description)
    fd.append('price', String(form.price))
    fd.append('keywords', JSON.stringify(form.keywords))
    if (form.imageFile) fd.append('image', form.imageFile) // опционально

    const res = await fetch(API.update, { method: 'POST', body: fd })
    const data = await res.json()

    if (!data.ok) {
      // сообщение "Вы ничего не меняли" — тоже ок, просто покажем тост
      notify(data.message || 'Нет изменений', data.message?.includes('ничего не меняли') ? 'warn' : 'error')
      if (data.message?.includes('ничего не меняли')) {
        // остаёмся в режиме edit
        return
      }
      return
    }

    const updated = data.category
    updated.image_url = toAbsoluteUrl(updated.image)

    const idx = categories.findIndex(c => c.id === updated.id)
    if (idx !== -1) categories[idx] = updated

    initialSnapshot.value = snapshotForm()
    notify('Изменения сохранены.', 'success')

  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    submitting.value = false
  }
}

async function deleteCategory (id) {
  try {
    const res = await fetch(API.delete, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось удалить')

    const idx = categories.findIndex(c => c.id === id)
    if (idx !== -1) categories.splice(idx, 1)
    if (mode.value === 'edit' && editingId.value === id) resetForm()
    notify('Категория удалена.', 'success')
  } catch (e) {
    notify(`Ошибка удаления: ${e.message}`, 'error', 3000)
  }
}

/* ===== Обработчики формы ===== */
async function onSubmit () {
  touched.title = touched.subtitle = touched.description = touched.price = touched.keywords = touched.image = true
  if (!isFormValid.value) return notify('Проверьте форму — есть ошибки.', 'error')

  if (mode.value === 'create') {
    await createCategory()
  } else {
    if (!isDirty.value) return notify('Вы ничего не меняли. Сохранять нечего.', 'warn')
    await updateCategory()
  }
}

function startEdit (cat) {
  mode.value = 'edit'
  editingId.value = cat.id
  form.title = cat.title
  form.subtitle = cat.subtitle
  form.description = cat.description
  form.price = typeof cat.price === 'number' ? cat.price : Number(cat.price)
  form.keywords = Array.isArray(cat.keywords) ? cat.keywords.slice() : []
  form.imageFile = null
  form.imagePreview = cat.image_url || cat.image || ''
  resetTouched()
  initialSnapshot.value = snapshotForm()
  notify('Режим редактирования.', 'info')
}

function cancelEdit () {
  if (initialSnapshot.value) {
    const snap = JSON.parse(initialSnapshot.value)
    form.title = snap.title
    form.subtitle = snap.subtitle
    form.description = snap.description
    form.price = snap.price
    form.keywords = snap.keywords.slice()
    form.imagePreview = snap.imagePreview
    form.imageFile = null
  } else {
    resetForm()
  }
  mode.value = 'create'
  editingId.value = null
  notify('Редактирование отменено.', 'info')
}

function resetForm () {
  Object.assign(form, blankForm())
  resetTouched()
  mode.value = 'create'
  editingId.value = null
  initialSnapshot.value = null
}

function resetTouched () {
  touched.title = touched.subtitle = touched.description = touched.price = touched.keywords = touched.image = false
}

/* Удаление (подтверждение) */
function confirmDelete (cat) {
  const ok = window.confirm(`Удалить категорию «${cat.title}»?`)
  if (!ok) return
  deleteCategory(cat.id)
}

/* ===== Утилиты ===== */
function formatPrice (val) {
  if (typeof val !== 'number') return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 2 }).format(Number(val))
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 2 }).format(val)
}

/* ===== Тосты ===== */
const toast = reactive({ show: false, type: 'info', message: '' })
let toastTimer = null
function notify (message, type = 'info', ms = 2200) {
  toast.message = message
  toast.type = type
  toast.show = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.show = false }, ms)
}

/* ===== Инициализация ===== */
onMounted(fetchList)
</script>

<style scoped>
/* ===== Фон страницы по ТЗ ===== */
.admin-categories {
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
  padding: 24px;
  min-height: 100vh;
}

/* ===== Заголовок ===== */
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
.field.inline { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.field .w-50 { width: 100%; }

label { font-size: 14px; color: var(--muted); }
.req { color: var(--warn); }

input[type="text"],
input[type="number"],
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

.hint { color: var(--muted); font-size: 12px; }
.error { color: var(--error); font-size: 12px; }

/* ===== Ключевые слова ===== */
.kw-row { display: flex; align-items: center; gap: 10px; }
.kw-row input[type="text"] { flex: 1; }
.kw-count { color: var(--muted); font-size: 12px; }

.chips {
  display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;
}
.chip {
  background: rgba(12,16,24,.8);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: transform .08s ease, background .15s ease;
}
.chip:hover { transform: translateY(-1px); background: rgba(12,16,24,.95); }
.chip.small { padding: 4px 8px; font-size: 11px; }
.chip .x {
  background: transparent; border: none; color: var(--muted);
  cursor: pointer; font-size: 14px; line-height: 1;
}
.chip .x:hover { color: var(--error); }

/* ===== Превью изображения ===== */
.preview {
  margin-top: 10px;
  width: 100%;
  max-width: 460px;
  aspect-ratio: 16 / 9;
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  background: rgba(8,10,16,.6);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.02);
}
.preview img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* ===== Кнопки ===== */
.submit-row {
  margin-top: 16px;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.btn {
  background: rgba(20, 24, 39, .85);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 10px 14px;
  border-radius: 12px;
  cursor: pointer;
  transition: transform .1s ease, border-color .18s ease, background .18s ease, box-shadow .18s ease;
  font-weight: 700;
  letter-spacing: .2px;
}
.btn:hover { transform: translateY(-1px); border-color: #3b3f5c; box-shadow: 0 8px 26px rgba(0,0,0,.3); }
.btn.primary { background: var(--primary); border-color: transparent; color: white; }
.btn.primary:hover { background: var(--primary-600); }
.btn.ghost { background: transparent; }
.btn:disabled { opacity: .55; cursor: not-allowed; }

/* ===== Список ===== */
.list-card .list-head {
  display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 8px;
}
.empty {
  border: 1px dashed var(--border);
  color: var(--muted);
  padding: 18px;
  border-radius: 12px;
  text-align: center;
}

.h-list {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(300px, 360px);
  gap: 14px;
  overflow-x: auto;
  padding-bottom: 4px;
}
.cat-card {
  background: rgba(8,10,16,.72);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  display: grid;
  grid-template-rows: 168px auto;
  position: relative;
  transition: transform .12s ease, box-shadow .18s ease, border-color .18s ease;
}
.cat-card:hover { transform: translateY(-2px); box-shadow: 0 14px 40px rgba(0,0,0,.35); border-color: #3a3f57; }
.thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.meta { padding: 12px; display: flex; flex-direction: column; gap: 6px; }
.meta h3 { margin: 0; font-size: 16px; font-weight: 800; }
.subtitle { color: var(--muted); font-size: 13px; }
.price { font-weight: 800; }

.kw-wrap { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }

.actions {
  position: absolute;
  right: 8px;
  top: 8px;
  display: flex; gap: 6px;
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

/* ===== Тосты ===== */
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

/* ===== Анимации ===== */
@keyframes popIn { from { opacity: 0; transform: translateY(6px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.pop-in { animation: popIn .24s ease both; }
.fade-in { animation: fadeIn .2s ease both; }
@keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }

/* Адаптив */
@media (max-width: 720px) {
  .field.inline { grid-template-columns: 1fr; }
}
</style>
