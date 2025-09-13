<template>
  <section class="admin-products">
    <!-- Заголовок страницы -->
    <header class="page-head">
      <h1>Управление продуктами</h1>
      <p class="muted">Добавляйте, редактируйте и удаляйте продукты внутри выбранных категорий.</p>
    </header>

    <!-- Форма -->
    <div class="card form-card" :class="{ loading: submitting }">
      <div class="form-head">
        <h2 class="fade-in">{{ mode === 'create' ? 'Новый продукт' : 'Редактирование продукта' }}</h2>
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
        <!-- Категория -->
        <div class="field">
          <label for="category">Категория <span class="req">*</span></label>
          <select
            id="category"
            v-model.number="form.category_id"
            :class="{ invalid: touched.category && !valid.category }"
            @blur="touched.category = true"
          >
            <option :value="0" disabled>Выберите категорию…</option>
            <option v-for="opt in categoryOptions" :key="opt.id" :value="opt.id">
              {{ opt.title }}
            </option>
          </select>
          <small v-if="touched.category && !valid.category" class="error">Выберите категорию.</small>
        </div>

        <!-- Eyebrow -->
        <div class="field">
          <label for="eyebrow">Верхняя подпись (eyebrow) <span class="req">*</span></label>
          <input
            id="eyebrow"
            v-model.trim="form.eyebrow"
            type="text"
            placeholder="Например: Управление задачами"
            :class="{ invalid: touched.eyebrow && !valid.eyebrow }"
            @blur="touched.eyebrow = true"
            required
          />
          <small v-if="touched.eyebrow && !valid.eyebrow" class="error">Заполните поле.</small>
        </div>

        <!-- Заголовок -->
        <div class="field">
          <label for="title">Заголовок <span class="req">*</span></label>
          <input
            id="title"
            v-model.trim="form.title"
            type="text"
            placeholder="Например: Kanban-доска PRO"
            :class="{ invalid: touched.title && !valid.title }"
            @blur="touched.title = true"
            required
          />
          <small v-if="touched.title && !valid.title" class="error">Заполните заголовок.</small>
        </div>

        <!-- Короткое описание -->
        <div class="field">
          <label for="tagline">Описание (кратко) <span class="req">*</span></label>
          <textarea
            id="tagline"
            v-model.trim="form.tagline"
            rows="3"
            placeholder="Гибкое управление задачами: статусы, дедлайны, отчёты…"
            :class="{ invalid: touched.tagline && !valid.tagline }"
            @blur="touched.tagline = true"
            required
          ></textarea>
          <small v-if="touched.tagline && !valid.tagline" class="error">Заполните описание.</small>
        </div>

        <!-- Фичи (чипы) -->
        <div class="field">
          <label>Преимущества/фичи (чипы) <span class="req">*</span></label>

          <div class="kw-row">
            <input
              v-model.trim="featureInput"
              type="text"
              :placeholder="featPlaceholder"
              :maxlength="50"
              @keydown.enter.prevent="addFeature"
              :disabled="form.features.length >= FEAT_MAX"
            />
            <button
              type="button"
              class="btn"
              @click="addFeature"
              :disabled="featureInput.length === 0 || form.features.length >= FEAT_MAX"
              title="Добавить фичу"
            >Добавить</button>
            <span class="kw-count">{{ form.features.length }}/{{ FEAT_MAX }}</span>
          </div>

          <div class="chips" v-if="form.features.length">
            <span class="chip" v-for="(f, i) in form.features" :key="f">
              {{ f }}
              <button class="x" type="button" aria-label="Удалить" @click="removeFeature(i)">×</button>
            </span>
          </div>

          <small v-if="touched.features && !valid.features" class="error">
            Нужно от {{ FEAT_MIN }} до {{ FEAT_MAX }} пунктов.
          </small>
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

        <!-- Фото -->
        <div class="field">
          <label for="image">Фото продукта (обложка) <span class="req">*</span></label>
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

          <div v-if="form.imagePreview" class="preview wide">
            <img :src="form.imagePreview" alt="Предпросмотр" />
          </div>
        </div>

        <!-- Кнопки -->
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

    <!-- Список продуктов -->
    <div class="card list-card">
      <div class="list-head">
        <h2>Продукты</h2>
        <span class="muted">{{ products.length }} шт.</span>
      </div>

      <div v-if="loading" class="empty">Загрузка…</div>
      <div v-else-if="!products.length" class="empty">
        Пока нет продуктов. Добавьте первый выше ↑
      </div>

      <div v-else class="v-list">
        <article class="prod-card pop-in" v-for="p in products" :key="p.id">
          <!-- Левая часть -->
          <div class="content">
            <div class="eyebrow">{{ p.eyebrow }}</div>
            <h3 class="title">{{ p.title }}</h3>
            <div class="subtitle">
              <span class="badge-cat">{{ p.category_title }}</span>
            </div>
            <p class="tagline">{{ p.tagline }}</p>

            <div class="feat-wrap">
              <span class="chip small" v-for="f in p.features" :key="f">{{ f }}</span>
            </div>

            <div class="meta">
              <span class="price">от {{ formatPrice(p.price) }}</span>
            </div>
          </div>

          <!-- Правая часть: фото -->
          <div class="thumb">
            <img :src="p.imagePreview" alt="" />
          </div>

          <!-- Действия -->
          <div class="actions">
            <button class="icon" title="Редактировать" @click="startEdit(p)">✏️</button>
            <button class="icon danger" title="Удалить" @click="confirmDelete(p)">🗑</button>
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

/* ===== API пути ===== */
const API_CATEGORIES = '/php/categories/list.php'
const API_PRODUCTS = {
  list:   '/php/products/list.php',
  create: '/php/products/create.php',
  update: '/php/products/update.php',
  delete: '/php/products/delete.php',
}

/* ===== Константы ===== */
const FEAT_MIN = 1
const FEAT_MAX = 6
const IMAGE_MAX_BYTES = 5 * 1024 * 1024
const ACCEPTED_TYPES = ['image/png', 'image/jpeg', 'image/webp']

/* ===== Состояние ===== */
const products = reactive([])
const categoryOptions = ref([])

const loading = ref(false)
const submitting = ref(false)

const mode = ref('create')
const editingId = ref(null)
const initialSnapshot = ref(null)

/* ===== Форма ===== */
const blankForm = () => ({
  category_id: 0,
  category_title: '',
  eyebrow: '',
  title: '',
  tagline: '',
  features: [],
  price: null,
  imageFile: null,
  imagePreview: ''
})

const form = reactive(blankForm())
const touched = reactive({
  category: false,
  eyebrow: false,
  title: false,
  tagline: false,
  features: false,
  price: false,
  image: false
})

/* ===== Чипы (фичи) ===== */
const featureInput = ref('')
const featPlaceholder = computed(() =>
  form.features.length >= FEAT_MAX ? 'Достигнут лимит' : 'Введите фичу и нажмите «Добавить»'
)
function addFeature () {
  const val = featureInput.value.trim()
  if (!val) return
  if (form.features.length >= FEAT_MAX) return notify(`Максимум ${FEAT_MAX} пунктов`, 'warn')
  const lower = val.toLowerCase()
  if (form.features.some(k => k.toLowerCase() === lower)) return notify('Такой пункт уже добавлен', 'warn')
  form.features.push(val); featureInput.value = ''; touched.features = true
}
function removeFeature (i) { form.features.splice(i,1); touched.features = true }

/* ===== Изображение ===== */
function onImageChange (e) {
  const file = e.target.files?.[0]; if (!file) return
  if (!ACCEPTED_TYPES.includes(file.type)) { touched.image = true; return notify('Недопустимый формат. Разрешены PNG/JPG/WEBP.', 'error') }
  if (file.size > IMAGE_MAX_BYTES)        { touched.image = true; return notify('Файл слишком большой (до 5 МБ).', 'error') }
  form.imageFile = file
  form.imagePreview = URL.createObjectURL(file)
  touched.image = true
}

/* ===== Валидация ===== */
const valid = reactive({
  get category () { return Number.isInteger(form.category_id) && form.category_id > 0 },
  get eyebrow  () { return form.eyebrow.length > 0 },
  get title    () { return form.title.length > 0 },
  get tagline  () { return form.tagline.length > 0 },
  get features () { return form.features.length >= FEAT_MIN && form.features.length <= FEAT_MAX },
  get price    () { return typeof form.price === 'number' && form.price > 0 },
  get image    () { return !!form.imagePreview }
})
const isFormValid = computed(() => valid.category && valid.eyebrow && valid.title && valid.tagline && valid.features && valid.price && valid.image)

function snapshotForm (obj = form) {
  return JSON.stringify({
    category_id: obj.category_id,
    category_title: obj.category_title,
    eyebrow: obj.eyebrow,
    title: obj.title,
    tagline: obj.tagline,
    features: obj.features.slice(),
    price: obj.price,
    imagePreview: obj.imagePreview
  })
}
const isDirty = computed(() => mode.value === 'create' ? isFormValid.value : (initialSnapshot.value ? snapshotForm() !== initialSnapshot.value : true))

/* ===== Вспомогательное ===== */
function notify (message, type = 'info', ms = 2200) {
  toast.message = message; toast.type = type; toast.show = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.show = false }, ms)
}
function formatPrice (val) {
  const n = Number(val); if (!Number.isFinite(n)) return val
  return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(n) + ' ₽'
}
function syncCategoryTitle () {
  const found = categoryOptions.value.find(o => o.id === form.category_id)
  form.category_title = found ? found.title : ''
}
function toAbsoluteUrl (relOrAbs) {
  if (!relOrAbs) return ''
  if (relOrAbs.startsWith('http')) return relOrAbs
  const origin = window.location.origin
  const path = relOrAbs.startsWith('/') ? relOrAbs : `/${relOrAbs}`
  return origin + path
}
function mapProduct(r) {
  return {
    id: Number(r.id),
    category_id: Number(r.category_id),
    category_title: r.category_title || '',
    eyebrow: r.eyebrow || '',
    title: r.title || '',
    tagline: r.tagline || '',
    features: Array.isArray(r.features) ? r.features : [],
    price: Number(r.price),
    image: r.image || '',
    imagePreview: r.image_url || toAbsoluteUrl(r.image || '')
  }
}

/* ===== CRUD с сервером ===== */
async function loadCategoryOptions () {
  try {
    const res = await fetch(`${API_CATEGORIES}?page=1&limit=100`)
    const data = await res.json()
    if (data?.ok && Array.isArray(data.items)) {
      categoryOptions.value = data.items.map(i => ({ id: i.id, title: i.title }))
    }
  } catch (_) {
    notify('Не удалось загрузить категории для выбора.', 'warn')
  }
}

async function fetchProducts () {
  loading.value = true
  try {
    const res = await fetch(`${API_PRODUCTS.list}?page=1&limit=100`)
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Ошибка загрузки')
    const items = Array.isArray(data.items) ? data.items : []
    products.splice(0, products.length, ...items.map(mapProduct))
  } catch (e) {
    notify(`Не удалось загрузить продукты: ${e.message}`, 'error', 3000)
    products.splice(0, products.length) // очистим
  } finally {
    loading.value = false
  }
}

async function createProduct () {
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('category_id', String(form.category_id))
    fd.append('eyebrow', form.eyebrow)
    fd.append('title', form.title)
    fd.append('tagline', form.tagline)
    fd.append('features', JSON.stringify(form.features))
    fd.append('price', String(form.price))
    if (form.imageFile) fd.append('image', form.imageFile)

    const res = await fetch(API_PRODUCTS.create, { method: 'POST', body: fd })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось добавить продукт')

    const p = mapProduct(data.product || {})
    products.unshift(p)

    notify('Продукт добавлен.', 'success')
    resetForm()
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    submitting.value = false
  }
}

async function updateProduct () {
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('id', String(editingId.value))
    fd.append('category_id', String(form.category_id))
    fd.append('eyebrow', form.eyebrow)
    fd.append('title', form.title)
    fd.append('tagline', form.tagline)
    fd.append('features', JSON.stringify(form.features))
    fd.append('price', String(form.price))
    if (form.imageFile) fd.append('image', form.imageFile) // опционально

    const res = await fetch(API_PRODUCTS.update, { method: 'POST', body: fd })
    const data = await res.json()

    if (!data.ok) {
      notify(data.message || 'Ошибка сохранения', data.message?.includes('ничего не меняли') ? 'warn' : 'error')
      if (data.message?.includes('ничего не меняли')) return
      return
    }

    const updated = mapProduct(data.product || {})
    const idx = products.findIndex(p => p.id === updated.id)
    if (idx !== -1) products[idx] = updated

    initialSnapshot.value = snapshotForm()
    notify('Изменения сохранены.', 'success')
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    submitting.value = false
  }
}

async function deleteProduct (id) {
  try {
    const res = await fetch(API_PRODUCTS.delete, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось удалить продукт')

    const idx = products.findIndex(p => p.id === id)
    if (idx !== -1) products.splice(idx, 1)
    if (mode.value === 'edit' && editingId.value === id) resetForm()
    notify('Продукт удалён.', 'success')
  } catch (e) {
    notify(`Ошибка удаления: ${e.message}`, 'error', 3000)
  }
}

/* ===== Обработчики формы ===== */
async function onSubmit () {
  touched.category = touched.eyebrow = touched.title = touched.tagline = touched.features = touched.price = touched.image = true
  syncCategoryTitle()
  if (!isFormValid.value) return notify('Проверьте форму — есть ошибки.', 'error')

  if (mode.value === 'create') {
    await createProduct()
  } else {
    if (!isDirty.value) return notify('Вы ничего не меняли. Сохранять нечего.', 'warn')
    await updateProduct()
  }
}

function startEdit (p) {
  mode.value = 'edit'
  editingId.value = p.id
  form.category_id = p.category_id
  form.category_title = p.category_title
  form.eyebrow = p.eyebrow
  form.title = p.title
  form.tagline = p.tagline
  form.features = p.features.slice()
  form.price = p.price
  form.imageFile = null
  form.imagePreview = p.imagePreview
  resetTouched()
  initialSnapshot.value = snapshotForm()
  notify('Режим редактирования.', 'info')
}

function cancelEdit () {
  if (initialSnapshot.value) {
    const snap = JSON.parse(initialSnapshot.value)
    form.category_id = snap.category_id
    form.category_title = snap.category_title
    form.eyebrow = snap.eyebrow
    form.title = snap.title
    form.tagline = snap.tagline
    form.features = snap.features.slice()
    form.price = snap.price
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
  touched.category = touched.eyebrow = touched.title = touched.tagline = touched.features = touched.price = touched.image = false
}

function confirmDelete (p) {
  const ok = window.confirm(`Удалить продукт «${p.title}»?`)
  if (!ok) return
  deleteProduct(p.id)
}

/* ===== Тосты ===== */
const toast = reactive({ show: false, type: 'info', message: '' })
let toastTimer = null

/* ===== Инициализация ===== */
onMounted(async () => {
  await Promise.all([loadCategoryOptions(), fetchProducts()])
})
</script>


<style scoped>
/* ===== Фон страницы — как у категорий ===== */
.admin-products {
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

/* Заголовок */
.page-head h1 {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: .2px;
  text-shadow: 0 8px 30px rgba(0,0,0,.35);
}
.muted { color: var(--muted); }
.small { font-size: 12px; }

/* Карточка формы */
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
.form-card { max-width: 1040px; }
.form-card.loading { opacity: .7; pointer-events: none; }

/* Поля формы */
.field { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }
.field.inline { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.field .w-50 { width: 100%; }

label { font-size: 14px; color: var(--muted); }
.req { color: var(--warn); }

input[type="text"],
input[type="number"],
textarea,
select {
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

input:focus, textarea:focus, select:focus {
  border-color: rgba(122,92,255,.65);
  box-shadow: 0 0 0 3px rgba(122,92,255,.15);
}

input.invalid, textarea.invalid, select.invalid {
  border-color: var(--error);
  box-shadow: 0 0 0 3px rgba(255,107,107,.14);
}

.hint { color: var(--muted); font-size: 12px; }
.error { color: var(--error); font-size: 12px; }

/* Чипы */
.kw-row { display: flex; align-items: center; gap: 10px; }
.kw-row input[type="text"] { flex: 1; }
.kw-count { color: var(--muted); font-size: 12px; }

.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.chip {
  background: rgba(12,16,24,.8);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 12px;
  display: inline-flex; align-items: center; gap: 6px;
  transition: transform .08s ease, background .15s ease;
}
.chip:hover { transform: translateY(-1px); background: rgba(12,16,24,.95); }
.chip.small { padding: 4px 8px; font-size: 11px; }
.chip .x { background: transparent; border: none; color: var(--muted); cursor: pointer; font-size: 14px; line-height: 1; }
.chip .x:hover { color: var(--error); }

/* Превью фото */
.preview.wide {
  margin-top: 10px;
  width: 100%;
  max-width: 720px;
  aspect-ratio: 16 / 9;
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  background: rgba(8,10,16,.6);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.02);
}
.preview img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Кнопки */
.submit-row { margin-top: 16px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.btn {
  background: rgba(20, 24, 39, .85);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 10px 14px;
  border-radius: 12px;
  cursor: pointer;
  transition: transform .1s ease, border-color .18s ease, background .18s ease, box-shadow .18s ease;
  font-weight: 700; letter-spacing: .2px;
}
.btn:hover { transform: translateY(-1px); border-color: #3b3f5c; box-shadow: 0 8px 26px rgba(0,0,0,.3); }
.btn.primary { background: var(--primary); border-color: transparent; color: white; }
.btn.primary:hover { background: var(--primary-600); }
.btn.ghost { background: transparent; }
.btn:disabled { opacity: .55; cursor: not-allowed; }

/* Список продуктов (горизонтальные карточки) */
.list-card .list-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 8px; }
.empty {
  border: 1px dashed var(--border);
  color: var(--muted);
  padding: 18px;
  border-radius: 12px;
  text-align: center;
}
.v-list { display: grid; gap: 14px; }

.prod-card {
  position: relative;
  display: grid;
  grid-template-columns: 1.2fr 1fr; /* слева текст, справа фото */
  gap: 0;
  background: rgba(8,10,16,.72);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  min-height: 200px;
  transition: transform .12s ease, box-shadow .18s ease, border-color .18s ease;
}
.prod-card:hover { transform: translateY(-2px); box-shadow: 0 14px 40px rgba(0,0,0,.35); border-color: #3a3f57; }

.content { padding: 16px 16px 14px; display: flex; flex-direction: column; gap: 8px; }
.eyebrow { color: #cfc7de; font-size: 13px; letter-spacing: .02em; text-transform: uppercase; }
.title { margin: 0; font-size: 18px; font-weight: 800; color: #ffe7c1; }
.subtitle { color: var(--muted); font-size: 13px; }
.badge-cat { background: rgba(122,92,255,.18); color: #e7e2ff; border: 1px solid rgba(122,92,255,.35); padding: 4px 8px; border-radius: 999px; }
.tagline { margin: 4px 0 0; color: #d9d2eb; font-size: 14px; }
.feat-wrap { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.meta { margin-top: auto; display: flex; align-items: center; gap: 12px; }
.price { font-weight: 800; }

.thumb { position: relative; min-height: 180px; }
.thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }

/* Иконки действий */
.actions {
  position: absolute; right: 8px; top: 8px;
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

/* Анимации */
@keyframes popIn { from { opacity: 0; transform: translateY(6px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.pop-in { animation: popIn .22s ease both; }

/* Адаптив */
@media (max-width: 900px) {
  .field.inline { grid-template-columns: 1fr; }
  .prod-card { grid-template-columns: 1fr; }
  .thumb { order: -1; min-height: 160px; }
}
</style>
