<template>
  <section class="admin-slides">
    <!-- Заголовок -->
    <header class="page-head">
      <h1>Слайды главного экрана</h1>
      <p class="muted">Загружайте, сортируйте и включайте/выключайте изображения для слайдера.</p>
    </header>

    <!-- Форма: загрузка новых -->
    <div class="card form-card" :class="{ loading: submitting }">
      <div class="form-head">
        <h2 class="fade-in">Добавить слайды</h2>
      </div>

      <div
        class="dropzone"
        @dragover.prevent
        @dragenter.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop"
        :class="{ over: dragOver }"
      >
        <input
          id="slidesInput"
          type="file"
          accept="image/png,image/jpeg,image/webp,image/gif"
          multiple
          class="hidden-input"
          @change="onFilesPicked"
        />
        <p class="muted small">
          Перетащите файлы сюда или
          <label for="slidesInput" class="pick">выберите на устройстве</label><br>
          PNG / JPG / WEBP / GIF, до 10 МБ за файл. Можно сразу несколько.
        </p>
      </div>

      <div class="submit-row">
        <button class="btn--primary btn" type="button" :disabled="!newFiles.length || submitting" @click="uploadNew">
          {{ submitting ? 'Загружаем…' : `Загрузить (${newFiles.length})` }}
        </button>
        <button v-if="newFiles.length" class="btn ghost" type="button" @click="clearNew">Очистить выбор</button>
      </div>

      <!-- Превью ещё не отправленных -->
      <div v-if="newFiles.length" class="gallery-grid">
        <div class="g-item" v-for="(it, i) in newFiles" :key="it.preview">
          <img :src="it.preview" alt="">
          <div class="g-actions">
            <button class="icon danger" title="Убрать из загрузки" @click="removeFromNew(i)">🗑</button>
          </div>
          <div class="g-badges"><span class="badge">новое</span></div>
        </div>
      </div>
    </div>

    <!-- Список слайдов -->
    <div class="card list-card">
      <div class="list-head">
        <h2>Слайды</h2>
        <span class="muted">{{ items.length }} шт.</span>
      </div>

      <div v-if="loading" class="empty">Загрузка…</div>
      <div v-else-if="!items.length" class="empty">Пока нет слайдов. Добавьте выше ↑</div>

      <ul v-else class="slides-grid" @dragover.prevent>
        <li
          v-for="(s, idx) in items"
          :key="s.id"
          class="slide-card pop-in"
          draggable="true"
          @dragstart="onDragStart(idx)"
          @dragenter.prevent="onDragEnter(idx)"
          @dragend="onDragEnd"
        >
          <div class="thumb">
            <img :src="toAbs(s.path)" alt="">
            <span class="position-badge">#{{ idx + 1 }}</span>
          </div>

          <div class="info">
            <div class="row">
              <strong>ID {{ s.id }}</strong>
              <label class="switch">
                <input type="checkbox" v-model="s.is_active" @change="markDirty">
                <span>Активен</span>
              </label>
            </div>

            <div class="row">
              <label class="btn ghost sm">
                Заменить
                <input type="file" accept="image/*" hidden @change="replaceOne($event, s)">
              </label>
              <button class="btn danger sm" @click="removeOne(s)">Удалить</button>
            </div>
          </div>
        </li>
      </ul>

      <div class="submit-row" v-if="items.length">
        <button class="btn--primary btn" :disabled="!dirty || saving" @click="saveAll">
          {{ saving ? 'Сохраняем…' : 'Сохранить порядок и активность' }}
        </button>
        <button class="btn danger sm" :disabled="saving" @click="reload">Обновить список</button>
        <span v-if="!dirty" class="muted small">Нет изменений</span>
      </div>
    </div>

    <!-- Тосты -->
    <transition name="toast">
      <div v-if="toast.show" class="toast" :class="toast.type">{{ toast.message }}</div>
    </transition>
  </section>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'

/* ==== API ==== */
const API = {
  list: '/php/slides/list.php',
  create: '/php/slides/create.php',
  update: '/php/slides/update.php',
  delete: '/php/slides/delete.php',
  replace: '/php/slides/replace.php',
}

/* ==== State ==== */
const items = reactive([])        // [{id, path, position, is_active}]
const loading = ref(false)
const saving = ref(false)
const submitting = ref(false)
const dirty = ref(false)

/* Новые файлы (пока не отправленные) */
const newFiles = reactive([])     // [{file, preview}]
const dragOver = ref(false)

/* Toasts */
const toast = reactive({ show: false, type: 'info', message: '' })
let toastTimer = null
function notify (message, type='info', ms=2200) {
  toast.message = message; toast.type = type; toast.show = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.show = false }, ms)
}

/* ==== Utils ==== */
function toAbs (p) {
  if (!p) return ''
  if (p.startsWith('http')) return p
  const origin = window.location.origin
  const path = p.startsWith('/') ? p : `/${p}`
  return origin + path
}
function markDirty () { dirty.value = true }

/* ==== Load ==== */
async function reload () {
  loading.value = true
  try {
    const res = await fetch(API.list, { cache: 'no-store' })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Ошибка загрузки')
    const rows = Array.isArray(data.items) ? data.items : []
    items.splice(0, items.length, ...rows.map(r => ({
      id: Number(r.id),
      path: r.path,
      position: Number(r.position) || 0,
      is_active: Number(r.is_active) === 1
    })))
    dirty.value = false
  } catch (e) {
    notify(e.message, 'error', 3000)
    items.splice(0, items.length)
  } finally {
    loading.value = false
  }
}

/* ==== Upload ==== */
function onFilesPicked (e) {
  const files = Array.from(e.target.files || [])
  addNew(files)
  e.target.value = ''
}
function onDrop (e) {
  dragOver.value = false
  addNew(Array.from(e.dataTransfer?.files || []))
}
function addNew (files) {
  const accept = ['image/png','image/jpeg','image/webp','image/gif']
  const max = 10 * 1024 * 1024
  for (const f of files) {
    if (!accept.includes(f.type)) { notify(`Не поддерживается: ${f.name}`, 'warn'); continue }
    if (f.size > max) { notify(`Слишком большой файл: ${f.name}`, 'warn'); continue }
    newFiles.push({ file: f, preview: URL.createObjectURL(f) })
  }
}
function clearNew () {
  for (const n of newFiles) URL.revokeObjectURL(n.preview)
  newFiles.splice(0, newFiles.length)
}
function removeFromNew (i) {
  URL.revokeObjectURL(newFiles[i].preview)
  newFiles.splice(i,1)
}

async function uploadNew () {
  if (!newFiles.length) return
  submitting.value = true
  try {
    const fd = new FormData()
    for (const n of newFiles) fd.append('files[]', n.file)
    const res = await fetch(API.create, { method: 'POST', body: fd })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось загрузить')
    // Добавим в список и почистим выбор
    const appended = (data.items || []).map(x => ({
      id: Number(x.id),
      path: x.path,
      position: Number(x.position) || items.length,
      is_active: true
    }))
    items.push(...appended)
    clearNew()
    dirty.value = true
    notify('Загружено ✅', 'success')
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    submitting.value = false
  }
}

/* ==== Replace / Delete ==== */
async function replaceOne (ev, s) {
  const file = ev.target.files?.[0]
  ev.target.value = ''
  if (!file) return
  const fd = new FormData()
  fd.append('id', String(s.id))
  fd.append('file', file)
  try {
    saving.value = true
    const res = await fetch(API.replace, { method: 'POST', body: fd })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось заменить')
    s.path = data.path
    dirty.value = true
    notify('Изображение обновлено', 'success')
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    saving.value = false
  }
}

async function removeOne (s) {
  if (!confirm('Удалить этот слайд?')) return
  try {
    saving.value = true
    const res = await fetch(API.delete, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: s.id })
    })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Не удалось удалить')
    const i = items.findIndex(x => x.id === s.id)
    if (i !== -1) items.splice(i,1)
    dirty.value = true
    notify('Слайд удалён', 'success')
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    saving.value = false
  }
}

/* ==== Save order/active ==== */
async function saveAll () {
  const payload = {
    items: items.map((x, i) => ({
      id: x.id,
      position: i,
      is_active: x.is_active ? 1 : 0
    }))
  }
  try {
    saving.value = true
    const res = await fetch(API.update, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (!data.ok) throw new Error(data.message || 'Сохранение не удалось')
    dirty.value = false
    notify('Изменения сохранены ✅', 'success')
  } catch (e) {
    notify(e.message, 'error', 3000)
  } finally {
    saving.value = false
  }
}

/* ==== Drag & Drop сортировка ==== */
let dragFrom = null
function onDragStart (idx) { dragFrom = idx }
function onDragEnter (idx) {
  if (dragFrom === null || dragFrom === idx) return
  const copy = [...items]
  const [moved] = copy.splice(dragFrom, 1)
  copy.splice(idx, 0, moved)
  items.splice(0, items.length, ...copy)
  dragFrom = idx
  dirty.value = true
}
function onDragEnd () { dragFrom = null }

/* ==== init ==== */
onMounted(reload)
</script>

<style scoped>
/* ===== Палитра/фон общий со страницей продуктов ===== */
.admin-slides {
  --panel: rgba(17, 16, 27, .72);
  --panel-2: rgba(22, 21, 35, .78);
  --text: #E9ECF5;
  --muted: #9AA3B2;
  --border: #2B2F44;
  --primary: #7A5CFF;
  --error: #ff6b6b;

  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(255,153,0,.08), transparent 60%),
    radial-gradient(800px 400px at 90% 10%, rgba(135,77,255,.1), transparent 55%),
    linear-gradient(160deg, #0f0b1a, #1b1230 45%, #2a1545 70%, #35185a);
  color: var(--text);
  padding: 24px;
  min-height: 100vh;
}

.page-head h1 { margin: 0 0 6px; font-size: 26px; font-weight: 800; letter-spacing: .2px; text-shadow: 0 8px 30px rgba(0,0,0,.35); }
.muted { color: var(--muted); }
.small { font-size: 12px; }

/* Карточки */
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
.form-card.loading { opacity: .7; pointer-events: none; }

.list-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 8px; }
.empty { border: 1px dashed var(--border); color: var(--muted); padding: 18px; border-radius: 12px; text-align: center; }

/* Dropzone */
.dropzone {
  margin-top: 6px;
  border: 1px dashed var(--border);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  background: rgba(8,10,16,.6);
}
.dropzone.over { border-color: rgba(122,92,255,.65); box-shadow: 0 0 0 3px rgba(122,92,255,.15); }
.dropzone .pick { color: #e7e2ff; text-decoration: underline; cursor: pointer; }
.hidden-input { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

/* Кнопки */


.submit-row { margin-top: 12px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Сетка слайдов */
.slides-grid {
  list-style: none;
  padding: 0; margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}

.slide-card {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: rgba(8,10,16,.72);
  overflow: hidden;
  min-height: 180px;
}

.thumb { position: relative; height: 180px; }
.thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.position-badge {
  position: absolute; left: 8px; top: 8px;
  background: rgba(0,0,0,.55); color: #fff; border: 1px solid var(--border);
  border-radius: 999px; padding: 2px 8px; font-size: 11px;
}

.info { padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
.switch input[type="checkbox"] { width: 18px; height: 18px; }

/* Превью новых */
.gallery-grid {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}
.g-item { position: relative; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: rgba(8,10,16,.6); }
.g-item img { display: block; width: 100%; height: 140px; object-fit: cover; }
.g-badges { position: absolute; left: 8px; top: 8px; display: flex; gap: 6px; }
.badge { background: rgba(0,0,0,.55); color: #e9ecf5; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px; font-size: 11px; }
.g-actions { position: absolute; right: 8px; top: 8px; display: flex; gap: 6px; }
.icon {
  background: rgba(0,0,0,.45);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 4px 6px;
  border-radius: 10px;
  cursor: pointer;
  transition: background .15s ease, transform .08s ease;
  font-size: 13px;
}
.icon:hover { background: rgba(255,255,255,.08); transform: translateY(-1px); }

/* Тосты */
.toast {
  position: fixed; right: 18px; top: 18px;
  padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border);
  background: rgba(10,12,18,.86); box-shadow: 0 10px 30px rgba(0,0,0,.3); z-index: 9999;
}
.toast.success { border-color: rgba(39,192,147,.4); }
.toast.error { border-color: rgba(255,107,107,.4); }
.toast.warn { border-color: rgba(255,176,32,.4); }
.toast.info { border-color: rgba(122,92,255,.4); }
.toast-enter-active, .toast-leave-active { transition: all .18s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px); }

/* Анимация появления */
@keyframes popIn { from { opacity: 0; transform: translateY(6px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.pop-in { animation: popIn .22s ease both; }

/* Адаптив */
@media (max-width: 560px) {
  .thumb { height: 150px; }
}
</style>
