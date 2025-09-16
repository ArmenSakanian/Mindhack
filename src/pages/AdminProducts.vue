<template>
  <section class="admin-products">
    <!-- Заголовок страницы -->
    <header class="page-head">
      <h1>Управление продуктами</h1>
      <p class="muted">
        Добавляйте, редактируйте и удаляйте продукты внутри выбранных категорий.
      </p>
    </header>

    <!-- Форма -->
    <div class="card form-card" :class="{ loading: submitting }">
      <div class="form-head">
        <h2 class="fade-in">
          {{ mode === "create" ? "Новый продукт" : "Редактирование продукта" }}
        </h2>
        <div class="form-actions">
          <button
            v-if="mode === 'create'"
            class="btn ghost"
            type="button"
            @click="resetForm()"
          >
            Закрыть
          </button>

          <button v-else class="btn ghost" type="button" @click="cancelEdit">
            Отмена
          </button>
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
            <option
              v-for="opt in categoryOptions"
              :key="opt.id"
              :value="opt.id"
            >
              {{ opt.title }}
            </option>
          </select>
          <small v-if="touched.category && !valid.category" class="error"
            >Выберите категорию.</small
          >
        </div>

        <!-- Eyebrow -->
        <div class="field">
          <label for="eyebrow"
            >Верхняя подпись (eyebrow) <span class="req">*</span></label
          >
          <input
            id="eyebrow"
            v-model.trim="form.eyebrow"
            type="text"
            placeholder="Например: Управление задачами"
            :class="{ invalid: touched.eyebrow && !valid.eyebrow }"
            @blur="touched.eyebrow = true"
            required
          />
          <small v-if="touched.eyebrow && !valid.eyebrow" class="error"
            >Заполните поле.</small
          >
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
          <small v-if="touched.title && !valid.title" class="error"
            >Заполните заголовок.</small
          >
        </div>

        <!-- Короткое описание -->
        <div class="field">
          <label for="tagline"
            >Описание (кратко) <span class="req">*</span></label
          >
          <textarea
            id="tagline"
            v-model.trim="form.tagline"
            rows="3"
            placeholder="Гибкое управление задачами: статусы, дедлайны, отчёты…"
            :class="{ invalid: touched.tagline && !valid.tagline }"
            @blur="touched.tagline = true"
            required
          ></textarea>
          <small v-if="touched.tagline && !valid.tagline" class="error"
            >Заполните описание.</small
          >
        </div>

        <!-- Ссылка на таблицу -->
        <div class="field">
          <label for="link_url">Ссылка на таблицу <span class="req">*</span></label>
          <input
            id="link_url"
            v-model.trim="form.link_url"
            type="text"
            placeholder="https://docs.google.com/..."
            :class="{ invalid: touched.link_url && !valid.link_url }"
            @blur="touched.link_url = true"
            required
          />
          <small v-if="touched.link_url && !valid.link_url" class="error">
            Укажите ссылку.
          </small>
        </div>

        <!-- Фичи (чипы) -->
        <div class="field">
          <label>Преимущества/фичи (чипы) <span class="req">*</span></label>

          <div class="kw-row">
            <input
              v-model.trim="featureInput"
              type="text"
              :placeholder="featPlaceholder"
              :maxlength="300"
              @keydown.enter.prevent="addFeature"
              :disabled="form.features.length >= FEAT_MAX"
            />
            <button
              type="button"
              class="btn"
              @click="addFeature"
              :disabled="
                featureInput.length === 0 || form.features.length >= FEAT_MAX
              "
              title="Добавить фичу"
            >
              Добавить
            </button>
            <span class="kw-count"
              >{{ form.features.length }}/{{ FEAT_MAX_LABEL }}</span
            >
          </div>

          <div class="chips" v-if="form.features.length">
            <span class="chip" v-for="(f, i) in form.features" :key="f">
              {{ f }}
              <button
                class="x"
                type="button"
                aria-label="Удалить"
                @click="removeFeature(i)"
              >
                ×
              </button>
            </span>
          </div>

          <small v-if="touched.features && !valid.features" class="error">
            Нужно минимум {{ FEAT_MIN }} пункт(а).
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
            <small v-if="touched.price && !valid.price" class="error"
              >Введите цену больше 0.</small
            >
          </div>
        </div>

        <!-- Галерея (мультизагрузка) -->
        <div class="field">
          <label for="images">Галерея фото <span class="req">*</span></label>

          <!-- Зона dnd/кнопка выбора -->
          <div
            class="dropzone"
            @dragover.prevent
            @dragenter.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
            :class="{ over: dragOver }"
          >
            <input
              id="images"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              multiple
              class="hidden-input"
              @change="onImagesPicked"
            />
            <p class="muted small">
              Перетащите файлы сюда или
              <label for="images" class="pick">выберите на устройстве</label>
              <br />PNG / JPG / WEBP, до 5 МБ. Максимум {{ IMAGES_MAX }} файлов.
            </p>
          </div>

          <!-- Превью галереи -->
          <div v-if="galleryList.length" class="gallery-grid">
            <div class="g-item" v-for="(img, i) in galleryList" :key="img.key">
              <img :src="img.preview" :alt="img.alt || 'Фото ' + (i + 1)" />
              <div class="g-badges">
                <span v-if="img.is_primary" class="badge primary">обложка</span>
                <span v-if="img.existing" class="badge">сохранено</span>
                <span v-else class="badge">новое</span>
              </div>
              <div class="g-actions">
                <button
                  type="button"
                  class="icon"
                  title="Сделать обложкой"
                  @click="makePrimary(i)"
                >
                  ★
                </button>
                <button
                  type="button"
                  class="icon"
                  title="Вверх"
                  @click="moveUp(i)"
                  :disabled="i === 0"
                >
                  ↑
                </button>
                <button
                  type="button"
                  class="icon"
                  title="Вниз"
                  @click="moveDown(i)"
                  :disabled="i === galleryList.length - 1"
                >
                  ↓
                </button>
                <button
                  type="button"
                  class="icon danger"
                  title="Удалить"
                  @click="removeImage(i)"
                >
                  🗑
                </button>
              </div>
            </div>
          </div>

          <small v-if="touched.images && !valid.images" class="error"
            >Загрузите хотя бы одно фото.</small
          >
        </div>

        <!-- Кнопки -->
        <div class="submit-row">
          <button
            v-if="mode === 'create'"
            class="btn primary"
            type="submit"
            :disabled="!isFormValid || submitting"
          >
            {{ submitting ? "Добавляем…" : "Добавить" }}
          </button>

          <button
            v-else
            class="btn primary"
            type="submit"
            :disabled="!isFormValid || !isDirty || submitting"
            title="Сохранить изменения"
          >
            {{ submitting ? "Сохраняем…" : "Сохранить" }}
          </button>

          <button
            v-if="mode === 'create'"
            class="btn ghost"
            type="button"
            @click="resetForm()"
          >
            Закрыть
          </button>

          <button v-else class="btn ghost" type="button" @click="cancelEdit">
            Закрыть
          </button>

          <span v-if="mode === 'edit' && !isDirty" class="muted small"
            >Нет изменений — сохранять нечего</span
          >
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

            <!-- Можно подсветить, что у продукта есть ссылка -->
            <div v-if="p.link_url" class="small muted" style="margin-top:6px;">
              Ссылка: {{ p.link_url }}
            </div>
          </div>

          <!-- Правая часть: фото (обложка) -->
          <div class="thumb">
            <img :src="p.coverPreview" alt="" />
          </div>

          <!-- Действия -->
          <div class="actions">
            <button class="icon" title="Редактировать" @click="startEdit(p)">
              ✏️
            </button>
            <button
              class="icon danger"
              title="Удалить"
              @click="confirmDelete(p)"
            >
              🗑
            </button>
          </div>
        </article>
      </div>
    </div>

    <!-- Тосты -->
    <transition name="toast">
      <div v-if="toast.show" class="toast" :class="toast.type">
        {{ toast.message }}
      </div>
    </transition>
  </section>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from "vue";

/* ===== API пути ===== */
const API_CATEGORIES = "/php/categories/list.php";
const API_PRODUCTS = {
  list: "/php/products/list.php",
  create: "/php/products/create.php",
  update: "/php/products/update.php",
  delete: "/php/products/delete.php",
};

/* ===== Константы ===== */
const FEAT_MIN = 1;
const FEAT_MAX = Infinity; // безлимит по фичам
const FEAT_MAX_LABEL = "∞";
const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const ACCEPTED_TYPES = ["image/png", "image/jpeg", "image/webp"];
const IMAGES_MAX = 10;

/* ===== Состояние ===== */
const products = reactive([]);
const categoryOptions = ref([]);

const loading = ref(false);
const submitting = ref(false);

const mode = ref("create");
const editingId = ref(null);
const initialSnapshot = ref(null);

/* ===== Форма ===== */
const blankForm = () => ({
  category_id: 0,
  category_title: "",
  eyebrow: "",
  title: "",
  tagline: "",
  link_url: "",           // <--- добавлено
  features: [],
  price: null,

  // Галерея
  existingImages: /** @type {Array<{id:number,url:string,url_full?:string,alt?:string,sort:number,is_primary:number}>} */ ([]),
  newImages: /** @type {Array<{file:File, preview:string}>} */ ([]),
});

const form = reactive(blankForm());
const touched = reactive({
  category: false,
  eyebrow: false,
  title: false,
  tagline: false,
  link_url: false,        // <--- добавлено
  features: false,
  price: false,
  images: false,
});

/* ===== Локальное представление галереи ===== */
const galleryList = computed(() => {
  const existing = [...form.existingImages]
    .sort((a, b) => b.is_primary - a.is_primary || a.sort - b.sort || a.id - b.id)
    .map((img) => ({
      existing: true,
      id: img.id,
      url: img.url,
      url_full: img.url_full,
      alt: img.alt || "",
      is_primary: Number(img.is_primary) === 1,
      preview: img.url_full || toAbsoluteUrl(img.url || ""),
      key: `ex_${img.id}`,
    }));

  const fresh = form.newImages.map((ni, idx) => ({
    existing: false,
    file: ni.file,
    preview: ni.preview,
    alt: "",
    is_primary: false,
    key: `new_${idx}_${ni.preview}`,
  }));

  return [...existing, ...fresh];
});

/* ===== Чипы (фичи) ===== */
const featureInput = ref("");
const featPlaceholder = computed(() => "Введите фичу и нажмите «Добавить»");

function addFeature() {
  const val = featureInput.value.trim();
  if (!val) return;
  const lower = val.toLowerCase();
  if (form.features.some((k) => k.toLowerCase() === lower)) {
    return notify("Такой пункт уже добавлен", "warn");
  }
  form.features.push(val);
  featureInput.value = "";
  touched.features = true;
}
function removeFeature(i) {
  form.features.splice(i, 1);
  touched.features = true;
}

/* ===== Галерея: ввод ===== */
const dragOver = ref(false);

function onImagesPicked(e) {
  const files = Array.from(e.target.files || []);
  addNewFiles(files);
  e.target.value = "";
}
function onDrop(e) {
  dragOver.value = false;
  const files = Array.from(e.dataTransfer?.files || []);
  addNewFiles(files);
}
function addNewFiles(files) {
  if (!files.length) return;
  const allCount = form.existingImages.length + form.newImages.length;
  if (allCount + files.length > IMAGES_MAX) {
    return notify(`Максимум ${IMAGES_MAX} фото в галерее.`, "warn");
  }
  const accepted = [];
  for (const f of files) {
    if (!ACCEPTED_TYPES.includes(f.type)) {
      notify(`Формат «${f.name}» не поддерживается.`, "error");
      continue;
    }
    if (f.size > IMAGE_MAX_BYTES) {
      notify(`Файл «${f.name}» слишком большой (до 5 МБ).`, "error");
      continue;
    }
    accepted.push(f);
  }
  for (const f of accepted) {
    const url = URL.createObjectURL(f);
    form.newImages.push({ file: f, preview: url });
  }
  touched.images = true;
}

/* ===== Галерея: действия ===== */
function makePrimary(idx) {
  const list = galleryList.value;
  list.forEach((it) => (it.is_primary = false));
  list[idx].is_primary = true;
  writeBackGallery(list);
}
function moveUp(idx) {
  if (idx === 0) return;
  const list = galleryList.value;
  const a = list[idx - 1];
  const b = list[idx];
  list[idx - 1] = b;
  list[idx] = a;
  writeBackGallery(list);
}
function moveDown(idx) {
  const list = galleryList.value;
  if (idx >= list.length - 1) return;
  const a = list[idx];
  const b = list[idx + 1];
  list[idx] = b;
  list[idx + 1] = a;
  writeBackGallery(list);
}
function removeImage(idx) {
  const list = galleryList.value;
  const it = list[idx];
  if (it.existing) {
    form.existingImages = form.existingImages.filter((x) => x.id !== it.id);
  } else {
    const pos = form.newImages.findIndex((x) => x.preview === it.preview);
    if (pos !== -1) {
      URL.revokeObjectURL(form.newImages[pos].preview);
      form.newImages.splice(pos, 1);
    }
  }
  touched.images = true;
}
function writeBackGallery(list) {
  const ex = [];
  const nw = [];
  for (const item of list) {
    if (item.existing) {
      ex.push({
        id: item.id,
        url: item.url,
        url_full: item.url_full,
        alt: item.alt,
        sort: 0,
        is_primary: item.is_primary ? 1 : 0,
      });
    } else {
      const found = form.newImages.find((x) => x.preview === item.preview);
      if (found) nw.push(found);
    }
  }
  form.existingImages = ex.map((e, i) => ({ ...e, sort: i + 1 }));
  form.newImages = nw;
}

/* ===== Валидация ===== */
const valid = reactive({
  get category() { return Number.isInteger(form.category_id) && form.category_id > 0; },
  get eyebrow() { return form.eyebrow.length > 0; },
  get title() { return form.title.length > 0; },
  get tagline() { return form.tagline.length > 0; },
  get link_url() { return form.link_url.length > 0; },   // <--- добавлено (без строгой URL-проверки)
  get features() { return form.features.length >= FEAT_MIN; },
  get price() { return typeof form.price === "number" && form.price > 0; },
  get images() {
    const count = form.existingImages.length + form.newImages.length;
    return count >= 1;
  },
});
const isFormValid = computed(
  () =>
    valid.category &&
    valid.eyebrow &&
    valid.title &&
    valid.tagline &&
    valid.link_url &&   // <--- учитываем поле
    valid.features &&
    valid.price &&
    valid.images
);

function snapshotForm(obj = form) {
  return JSON.stringify({
    category_id: obj.category_id,
    category_title: obj.category_title,
    eyebrow: obj.eyebrow,
    title: obj.title,
    tagline: obj.tagline,
    link_url: obj.link_url,              // <--- добавлено
    features: obj.features.slice(),
    price: obj.price,
    existingIds: obj.existingImages.map((x) => x.id),
    newCount: obj.newImages.length,
  });
}
const isDirty = computed(() =>
  mode.value === "create"
    ? isFormValid.value
    : initialSnapshot.value
    ? snapshotForm() !== initialSnapshot.value
    : true
);

/* ===== Вспомогательное ===== */
function notify(message, type = "info", ms = 2200) {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.show = false;
  }, ms);
}
function formatPrice(val) {
  const n = Number(val);
  if (!Number.isFinite(n)) return val;
  return new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 0 }).format(n) + " ₽";
}
function syncCategoryTitle() {
  const found = categoryOptions.value.find((o) => o.id === form.category_id);
  form.category_title = found ? found.title : "";
}
function toAbsoluteUrl(relOrAbs) {
  if (!relOrAbs) return "";
  if (relOrAbs.startsWith("http")) return relOrAbs;
  const origin = window.location.origin;
  const path = relOrAbs.startsWith("/") ? relOrAbs : `/${relOrAbs}`;
  return origin + path;
}
function pickCoverUrl(r) {
  if (Array.isArray(r.images) && r.images.length) {
    const sorted = [...r.images].sort(
      (a, b) => b.is_primary - a.is_primary || a.sort - b.sort || a.id - b.id
    );
    const first = sorted[0];
    return first.url_full || toAbsoluteUrl(first.url || "");
  }
  return r.image_url || toAbsoluteUrl(r.image || "");
}
function mapProduct(r) {
  return {
    id: Number(r.id),
    category_id: Number(r.category_id),
    category_title: r.category_title || "",
    eyebrow: r.eyebrow || "",
    title: r.title || "",
    tagline: r.tagline || "",
    link_url: r.link_url || "",        // <--- добавлено
    features: Array.isArray(r.features) ? r.features : [],
    price: Number(r.price),
    image: r.image || "",
    image_url: r.image_url || "",
    images: Array.isArray(r.images) ? r.images : [],
    coverPreview: pickCoverUrl(r),
  };
}

/* ===== CRUD с сервером ===== */
async function loadCategoryOptions() {
  try {
    const res = await fetch(`${API_CATEGORIES}?page=1&limit=100`);
    const data = await res.json();
    if (data?.ok && Array.isArray(data.items)) {
      categoryOptions.value = data.items.map((i) => ({
        id: i.id,
        title: i.title,
      }));
    }
  } catch (_) {
    notify("Не удалось загрузить категории для выбора.", "warn");
  }
}

async function fetchProducts() {
  loading.value = true;
  try {
    const res = await fetch(`${API_PRODUCTS.list}?page=1&limit=100`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Ошибка загрузки");
    const items = Array.isArray(data.items) ? data.items : [];
    products.splice(0, products.length, ...items.map(mapProduct));
  } catch (e) {
    notify(`Не удалось загрузить продукты: ${e.message}`, "error", 3000);
    products.splice(0, products.length);
  } finally {
    loading.value = false;
  }
}

async function createProduct() {
  submitting.value = true;
  try {
    const fd = new FormData();
    fd.append("category_id", String(form.category_id));
    fd.append("eyebrow", form.eyebrow);
    fd.append("title", form.title);
    fd.append("tagline", form.tagline);
    fd.append("link_url", form.link_url);                 // <--- добавлено
    fd.append("features", JSON.stringify(form.features));
    fd.append("price", String(form.price));

    for (const it of form.newImages) {
      fd.append("images[]", it.file);
    }

    const res = await fetch(API_PRODUCTS.create, { method: "POST", body: fd });
    const data = await res.json();
    if (!data.ok)
      throw new Error(data.message || "Не удалось добавить продукт");

    const p = mapProduct(data.product || {});
    products.unshift(p);

    notify("Продукт добавлен.", "success");
    resetForm();
  } catch (e) {
    notify(e.message, "error", 3000);
  } finally {
    submitting.value = false;
  }
}

async function updateProduct() {
  submitting.value = true;
  try {
    const fd = new FormData();
    fd.append("id", String(editingId.value));
    fd.append("category_id", String(form.category_id));
    fd.append("eyebrow", form.eyebrow);
    fd.append("title", form.title);
    fd.append("tagline", form.tagline);
    fd.append("link_url", form.link_url);                 // <--- добавлено
    fd.append("features", JSON.stringify(form.features));
    fd.append("price", String(form.price));

    for (const it of form.newImages) {
      fd.append("images[]", it.file);
    }

    const existingIdsCurrent = form.existingImages.map((x) => x.id);
    const existingIdsOriginal = initialSnapshot.value
      ? JSON.parse(initialSnapshot.value).existingIds
      : [];
    const toDelete = existingIdsOriginal.filter(
      (id) => !existingIdsCurrent.includes(id)
    );
    if (toDelete.length) {
      fd.append("image_ids_to_delete", JSON.stringify(toDelete));
    }

    const orderIds = galleryList.value
      .filter((it) => it.existing)
      .map((it) => it.id);
    if (orderIds.length) {
      fd.append("images_order", JSON.stringify(orderIds));
    }

    const primaryExisting = galleryList.value.find(
      (it) => it.existing && it.is_primary
    );
    if (primaryExisting) {
      fd.append("primary_id", String(primaryExisting.id));
    }

    const res = await fetch(API_PRODUCTS.update, { method: "POST", body: fd });
    const data = await res.json();

    if (!data.ok) {
      notify(
        data.message || "Ошибка сохранения",
        data.message?.includes("ничего не меняли") ? "warn" : "error"
      );
      if (data.message?.includes("ничего не меняли")) return;
      return;
    }

    const updated = mapProduct(data.product || {});
    const idx = products.findIndex((p) => p.id === updated.id);
    if (idx !== -1) products[idx] = updated;

    initialSnapshot.value = snapshotForm();
    notify("Изменения сохранены.", "success");
  } catch (e) {
    notify(e.message, "error", 3000);
  } finally {
    submitting.value = false;
  }
}

async function deleteProduct(id) {
  try {
    const res = await fetch(API_PRODUCTS.delete, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Не удалось удалить продукт");

    const idx = products.findIndex((p) => p.id === id);
    if (idx !== -1) products.splice(idx, 1);
    if (mode.value === "edit" && editingId.value === id) resetForm();
    notify("Продукт удалён.", "success");
  } catch (e) {
    notify(`Ошибка удаления: ${e.message}`, "error", 3000);
  }
}

/* ===== Обработчики формы ===== */
async function onSubmit() {
  touched.category =
    touched.eyebrow =
    touched.title =
    touched.tagline =
    touched.link_url =     // <--- добавлено
    touched.features =
    touched.price =
    touched.images =
      true;

  syncCategoryTitle();
  if (!isFormValid.value)
    return notify("Проверьте форму — есть ошибки.", "error");

  if (mode.value === "create") {
    await createProduct();
  } else {
    if (!isDirty.value)
      return notify("Вы ничего не меняли. Сохранять нечего.", "warn");
    await updateProduct();
  }
}

function startEdit(p) {
  mode.value = "edit";
  editingId.value = p.id;
  form.category_id = p.category_id;
  form.category_title = p.category_title;
  form.eyebrow = p.eyebrow;
  form.title = p.title;
  form.tagline = p.tagline;
  form.link_url = p.link_url || "";                 // <--- добавлено
  form.features = p.features.slice();
  form.price = p.price;

  const ex = Array.isArray(p.images) ? p.images : [];
  const sorted = [...ex].sort(
    (a, b) => b.is_primary - a.is_primary || a.sort - b.sort || a.id - b.id
  );
  form.existingImages = sorted.map((img, idx) => ({
    id: Number(img.id),
    url: img.url || "",
    url_full: img.url_full || "",
    alt: img.alt || "",
    sort: idx + 1,
    is_primary: Number(img.is_primary) === 1 ? 1 : 0,
  }));

  for (const ni of form.newImages) {
    URL.revokeObjectURL(ni.preview);
  }
  form.newImages = [];

  resetTouched();
  initialSnapshot.value = JSON.stringify({
    category_id: form.category_id,
    category_title: form.category_title,
    eyebrow: form.eyebrow,
    title: form.title,
    tagline: form.tagline,
    link_url: form.link_url,               // <--- добавлено
    features: form.features.slice(),
    price: form.price,
    existingIds: form.existingImages.map((x) => x.id),
    newCount: 0,
  });
  notify("Режим редактирования.", "info");
}

function cancelEdit() {
  resetForm();
  mode.value = "create";
  editingId.value = null;
  notify("Редактирование отменено.", "info");
}

function resetForm() {
  for (const ni of form.newImages) {
    URL.revokeObjectURL(ni.preview);
  }
  Object.assign(form, blankForm());
  resetTouched();
  mode.value = "create";
  editingId.value = null;
  initialSnapshot.value = null;
}

function resetTouched() {
  touched.category =
    touched.eyebrow =
    touched.title =
    touched.tagline =
    touched.link_url =     // <--- добавлено
    touched.features =
    touched.price =
    touched.images =
      false;
}

function confirmDelete(p) {
  const ok = window.confirm(`Удалить продукт «${p.title}»?`);
  if (!ok) return;
  deleteProduct(p.id);
}

/* ===== Тосты ===== */
const toast = reactive({ show: false, type: "info", message: "" });
let toastTimer = null;

/* ===== Инициализация ===== */
onMounted(async () => {
  await Promise.all([loadCategoryOptions(), fetchProducts()]);
});
</script>


<style scoped>
/* ===== Фон страницы — как у категорий ===== */
.admin-products {
  --panel: rgba(17, 16, 27, 0.72);
  --panel-2: rgba(22, 21, 35, 0.78);
  --text: #e9ecf5;
  --muted: #9aa3b2;
  --border: #2b2f44;
  --primary: #7a5cff;
  --primary-600: #6248d6;
  --error: #ff6b6b;
  --warn: #ffb020;
  --success: #27c093;

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
  letter-spacing: 0.2px;
  text-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
}
.muted {
  color: var(--muted);
}
.small {
  font-size: 12px;
}

/* Карточка формы */
.card {
  background: linear-gradient(180deg, var(--panel), var(--panel-2));
  backdrop-filter: saturate(120%) blur(6px);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px;
  margin-top: 18px;
  box-shadow: 0 10px 36px rgba(0, 0, 0, 0.35);
  transition: transform 0.12s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.card:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.38);
}
.form-card {
  max-width: 1040px;
}
.form-card.loading {
  opacity: 0.7;
  pointer-events: none;
}

/* Поля формы */
.field {
  margin-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.field.inline {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.field .w-50 {
  width: 100%;
}

label {
  font-size: 14px;
  color: var(--muted);
}
.req {
  color: var(--warn);
}

input[type="text"],
input[type="number"],
textarea,
select {
  background: rgba(7, 9, 15, 0.6);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
  outline: none;
  font-size: 14px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease,
    background 0.15s ease, transform 0.06s ease;
}
textarea {
  resize: vertical;
}

input:focus,
textarea:focus,
select:focus {
  border-color: rgba(122, 92, 255, 0.65);
  box-shadow: 0 0 0 3px rgba(122, 92, 255, 0.15);
}

input.invalid,
textarea.invalid,
select.invalid {
  border-color: var(--error);
  box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.14);
}

.hint {
  color: var(--muted);
  font-size: 12px;
}
.error {
  color: var(--error);
  font-size: 12px;
}

/* Чипы */
.kw-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.kw-row input[type="text"] {
  flex: 1;
}
.kw-count {
  color: var(--muted);
  font-size: 12px;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}
.chip {
  background: rgba(12, 16, 24, 0.8);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: transform 0.08s ease, background 0.15s ease;
}
.chip:hover {
  transform: translateY(-1px);
  background: rgba(12, 16, 24, 0.95);
}
.chip.small {
  padding: 4px 8px;
  font-size: 11px;
}
.chip .x {
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
}
.chip .x:hover {
  color: var(--error);
}

/* Dropzone */
.dropzone {
  margin-top: 6px;
  border: 1px dashed var(--border);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  background: rgba(8, 10, 16, 0.6);
}
.dropzone.over {
  border-color: rgba(122, 92, 255, 0.65);
  box-shadow: 0 0 0 3px rgba(122, 92, 255, 0.15);
}
.dropzone .pick {
  color: #e7e2ff;
  text-decoration: underline;
  cursor: pointer;
}
.hidden-input {
  position: absolute;
  left: -9999px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}

/* Галерея превью */
.gallery-grid {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}
.g-item {
  position: relative;
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  background: rgba(8, 10, 16, 0.6);
}
.g-item img {
  display: block;
  width: 100%;
  height: 140px;
  object-fit: cover;
}
.g-badges {
  position: absolute;
  left: 8px;
  top: 8px;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.badge {
  background: rgba(0, 0, 0, 0.55);
  color: #e9ecf5;
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 11px;
}
.badge.primary {
  background: rgba(122, 92, 255, 0.75);
  border-color: rgba(122, 92, 255, 0.35);
}

.g-actions {
  position: absolute;
  right: 8px;
  top: 8px;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.icon {
  background: rgba(0, 0, 0, 0.45);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 4px 6px;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.08s ease;
  font-size: 13px;
}
.icon:hover {
  background: rgba(255, 255, 255, 0.08);
  transform: translateY(-1px);
}
.icon.danger:hover {
  background: rgba(255, 0, 0, 0.12);
}

/* Список продуктов (горизонтальные карточки) */
.list-card .list-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 8px;
}
.empty {
  border: 1px dashed var(--border);
  color: var(--muted);
  padding: 18px;
  border-radius: 12px;
  text-align: center;
}
.v-list {
  display: grid;
  gap: 14px;
}

.prod-card {
  position: relative;
  display: grid;
  grid-template-columns: 1.2fr 1fr; /* слева текст, справа фото */
  gap: 0;
  background: rgba(8, 10, 16, 0.72);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  min-height: 200px;
  transition: transform 0.12s ease, box-shadow 0.18s ease,
    border-color 0.18s ease;
}
.prod-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 40px rgba(0, 0, 0, 0.35);
  border-color: #3a3f57;
}

.content {
  padding: 16px 16px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.eyebrow {
  color: #cfc7de;
  font-size: 13px;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: #ffe7c1;
}
.subtitle {
  color: var(--muted);
  font-size: 13px;
}
.badge-cat {
  background: rgba(122, 92, 255, 0.18);
  color: #e7e2ff;
  border: 1px solid rgba(122, 92, 255, 0.35);
  padding: 4px 8px;
  border-radius: 999px;
}
.tagline {
  margin: 4px 0 0;
  color: #d9d2eb;
  font-size: 14px;
}
.feat-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}
.meta {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 12px;
}
.price {
  font-weight: 800;
}

.thumb {
  position: relative;
  min-height: 180px;
}
.thumb img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Иконки действий */
.actions {
  position: absolute;
  right: 8px;
  top: 8px;
  display: flex;
  gap: 6px;
}
.icon {
  background: rgba(0, 0, 0, 0.45);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 6px 8px;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.08s ease;
}
.icon:hover {
  background: rgba(255, 255, 255, 0.08);
  transform: translateY(-1px);
}
.icon.danger:hover {
  background: rgba(255, 0, 0, 0.12);
}

/* Тосты */
.toast {
  position: fixed;
  right: 18px;
  top: 18px;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: rgba(10, 12, 18, 0.86);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  z-index: 9999;
}
.toast.success {
  border-color: rgba(39, 192, 147, 0.4);
}
.toast.error {
  border-color: rgba(255, 107, 107, 0.4);
}
.toast.warn {
  border-color: rgba(255, 176, 32, 0.4);
}
.toast.info {
  border-color: rgba(122, 92, 255, 0.4);
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.18s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Анимации */
@keyframes popIn {
  from {
    opacity: 0;
    transform: translateY(6px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
.pop-in {
  animation: popIn 0.22s ease both;
}

/* Адаптив */
@media (max-width: 900px) {
  .field.inline {
    grid-template-columns: 1fr;
  }
  .prod-card {
    grid-template-columns: 1fr;
  }
  .thumb {
    order: -1;
    min-height: 160px;
  }
}
</style>
