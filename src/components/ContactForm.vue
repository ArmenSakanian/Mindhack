<!-- src/components/ContactForm.vue -->
<template>
  <section id="feedback" class="contact">
    <div class="container">
      <div data-aos="fade-up" class="card">
        <h2 class="title">Обратная связь</h2>

        <form class="form" @submit.prevent="onSubmit" novalidate>
          <!-- NAME -->
          <div class="field floating" :class="{ filled: form.name }">
            <input
              id="name"
              v-model.trim="form.name"
              type="text"
              required
              autocomplete="name"
              @blur="touch.name = true"
              :class="{ invalid: touch.name && !validName }"
            />
            <label for="name">Имя *</label>
          </div>

          <!-- EMAIL -->
          <div class="field floating" :class="{ filled: form.email }">
            <input
              id="email"
              v-model.trim="form.email"
              type="email"
              required
              autocomplete="email"
              @blur="touch.email = true"
              :class="{ invalid: touch.email && !validEmail }"
            />
            <label for="email">E-mail *</label>
            <p v-if="touch.email && !validEmail" class="hint error">
              Введите корректный e-mail.
            </p>
          </div>

          <!-- PHONE -->
          <div
            class="field floating"
            :class="{ filled: cleanDigits(form.phone).length > 1 }"
          >
            <input
              id="phone"
              v-model="form.phone"
              type="tel"
              required
              autocomplete="tel"
              inputmode="tel"
              :placeholder="isPhoneFocused ? '+7 (___) ___-__-__' : ''"
              @focus="onPhoneFocus"
              @blur="onPhoneBlur"
              @input="formatPhone"
              :class="{ invalid: touch.phone && !validPhone }"
            />
            <label for="phone">Телефон *</label>
            <p v-if="touch.phone && !validPhone" class="hint error">
              Формат: +7 (XXX) XXX-XX-XX
            </p>
          </div>

          <!-- SUBJECT -->
          <div class="field floating" :class="{ filled: form.subject }">
            <input
              id="subject"
              v-model.trim="form.subject"
              type="text"
              required
              @blur="touch.subject = true"
              :class="{ invalid: touch.subject && !validSubject }"
            />
            <label for="subject">Тема *</label>
            <p v-if="touch.subject && !validSubject" class="hint error">
              Минимум 3 символа.
            </p>
          </div>

          <!-- MESSAGE -->
          <div class="field floating span-2" :class="{ filled: form.message }">
            <textarea
              id="message"
              v-model.trim="form.message"
              rows="6"
              required
              @blur="touch.message = true"
              :class="{ invalid: touch.message && !validMessage }"
            ></textarea>
            <label for="message">Сообщение *</label>
            <p v-if="touch.message && !validMessage" class="hint error">
              Минимум 10 символов.
            </p>
          </div>

          <!-- CONSENT -->
          <div class="consent span-2">
            <label class="checkbox">
              <input
                type="checkbox"
                v-model="form.consent"
                required
                @blur="touch.consent = true"
              />
              <span>
                Я согласен(на) с
                <a href="/privacy" target="_blank"
                  >политикой конфиденциальности</a
                >
                *
              </span>
            </label>
            <p v-if="touch.consent && !form.consent" class="hint error">
              Необходимо согласие.
            </p>
          </div>

          <!-- FILES -->
          <div class="field field-files span-2">
            <label class="files-label">Файлы (необязательно)</label>

            <div
              class="dropzone"
              :class="{ dragging }"
              @dragover.prevent="dragging = true"
              @dragleave.prevent="dragging = false"
              @drop.prevent="handleDrop"
              @click="openFileDialog"
            >
              <input
                ref="fileInput"
                name="files"
                type="file"
                class="file-input"
                multiple
                @change="handleFiles"
              />
              <div class="dropzone-inner">
                <div class="drop-icon">
                  <i class="fa-solid fa-arrow-up-from-bracket"></i>
                </div>
                <div class="drop-text">
                  Перетащите файлы сюда или
                  <button
                    type="button"
                    class="linklike"
                    @click.stop.prevent="openFileDialog"
                  >
                    выберите
                  </button>
                </div>
              </div>
            </div>

            <ul v-if="files.length" class="files">
              <li v-for="(f, i) in files" :key="i">
                <div class="meta">
                  <span class="fname">{{ f.name }}</span>
                  <span class="fsize">{{ formatSize(f.size) }}</span>
                </div>
                <button type="button" class="remove" @click="removeFile(i)">
                  ×
                </button>
              </li>
            </ul>
          </div>

          <!-- ACTIONS -->
          <div class="actions span-2">
            <button
              class="btn"
              type="submit"
              :disabled="!formValid || submitting"
            >
              <span v-if="!submitting">Отправить</span>
              <span v-else>Отправка…</span>
            </button>
          </div>

          <!-- (скрыто, оставлено на случай доступности) -->
          <p
            v-if="false && resultMessage"
            :class="['result', resultOk ? 'ok' : 'fail', 'span-2']"
          >
            {{ resultMessage }}
          </p>
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, reactive, computed } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

/* --------- state --------- */
const fileInput = ref(null);
const dragging = ref(false);
const files = ref([]);

const form = reactive({
  name: "",
  email: "",
  phone: "",
  subject: "",
  message: "",
  consent: false,
});

const touch = reactive({
  name: false,
  email: false,
  phone: false,
  subject: false,
  message: false,
  consent: false,
});

const submitting = ref(false);
const resultMessage = ref("");
const resultOk = ref(false);

/* --------- validators --------- */
const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
const validName = computed(() => form.name.trim().length > 0);
const validEmail = computed(() => emailRe.test(form.email));

function cleanDigits(v) {
  return (v || "").replace(/\D/g, "");
}

const validPhone = computed(() => {
  const digits = cleanDigits(form.phone);
  return digits.length === 11 && digits.startsWith("7");
});

const validSubject = computed(() => form.subject.trim().length >= 3);
const validMessage = computed(() => form.message.trim().length >= 10);

const formValid = computed(
  () =>
    validName.value &&
    validEmail.value &&
    validPhone.value &&
    validSubject.value &&
    validMessage.value &&
    form.consent
);

const isPhoneFocused = ref(false);

function onPhoneFocus(e) {
  isPhoneFocused.value = true;
  if (!cleanDigits(form.phone).length) {
    form.phone = "+7 (";
    requestAnimationFrame(() => {
      try {
        e.target.setSelectionRange(form.phone.length, form.phone.length);
      } catch {}
    });
  }
}

function onPhoneBlur() {
  isPhoneFocused.value = false;
  touch.phone = true;
  const digits = cleanDigits(form.phone);
  if (digits.length <= 1) {
    form.phone = "";
  }
}

function formatPhone(e) {
  let digits = cleanDigits(e.target.value);
  if (digits.startsWith("8")) digits = "7" + digits.slice(1);
  if (!digits.length) {
    form.phone = "";
    return;
  }
  if (!digits.startsWith("7")) digits = "7" + digits;
  digits = digits.slice(0, 11);

  const a = digits.slice(1, 4);
  const b = digits.slice(4, 7);
  const c = digits.slice(7, 9);
  const d = digits.slice(9, 11);

  let out = "+7";
  out += " (" + a;
  out += a.length === 3 ? ")" : "";
  out += b ? ` ${b}` : "";
  out += c ? `-${c}` : "";
  out += d ? `-${d}` : "";
  form.phone = out;
}

/* --------- files --------- */
function openFileDialog() {
  const el = fileInput.value;
  if (!el) return;
  try {
    if (typeof el.showPicker === "function") {
      el.showPicker();
    } else {
      el.click();
    }
  } catch {
    el.click();
  }
}
function handleFiles(e) {
  files.value = [...e.target.files];
  e.target.value = ""; // reset для повторного выбора тех же файлов
}
function handleDrop(e) {
  dragging.value = false;
  files.value = [...e.dataTransfer.files];
}
function removeFile(i) {
  files.value.splice(i, 1);
}
function formatSize(bytes) {
  if (bytes < 1024) return bytes + " B";
  const kb = bytes / 1024;
  if (kb < 1024) return kb.toFixed(1) + " KB";
  const mb = kb / 1024;
  return mb.toFixed(1) + " MB";
}

/* --------- модалки --------- */
function alertSuccess(msg = "Сообщение отправлено!") {
  Swal.fire({
    icon: "success",
    title: "Отправлено",
    text: msg,
    confirmButtonText: "Ок",
    confirmButtonColor: "#ff9900",
    background: "rgba(19,26,42,0.98)",
    color: "#e9eef4",
    backdrop:
      "rgba(0,0,0,0.6) left top / cover no-repeat fixed " +
      'url("data:image/svg+xml;base64,' +
      btoa(`<svg xmlns='http://www.w3.org/2000/svg' width='1600' height='1000'>
        <defs>
          <linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>
            <stop offset='0%' stop-color='#0f0b1a'/>
            <stop offset='45%' stop-color='#121b30'/>
            <stop offset='70%' stop-color='#153345'/>
            <stop offset='100%' stop-color='#183e5a'/>
          </linearGradient>
        </defs>
        <rect width='100%' height='100%' fill='url(#g)'/>
      </svg>`) +
      '")',
    showClass: { popup: "swal2-show" },
    hideClass: { popup: "swal2-hide" },
  });
}

function alertError(msg = "Ошибка при отправке. Попробуйте позже.") {
  Swal.fire({
    icon: "error",
    title: "Не удалось отправить",
    text: msg,
    confirmButtonText: "Понятно",
    confirmButtonColor: "#ff9900",
    background: "rgba(19,26,42,0.98)",
    color: "#e9eef4",
    backdrop: "rgba(0,0,0,0.6)",
  });
}

/* --------- submit --------- */
async function onSubmit() {
  Object.keys(touch).forEach((k) => (touch[k] = true));
  if (!formValid.value) return;

  submitting.value = true;
  resultMessage.value = "";
  resultOk.value = false;

  try {
    const fd = new FormData();
    fd.append("name", form.name);
    fd.append("email", form.email);
    fd.append("phone", form.phone);
    fd.append("subject", form.subject);
    fd.append("message", form.message);
    fd.append("consent", String(form.consent));
    files.value.forEach((f) => fd.append("files[]", f, f.name));

    const res = await axios.post("/php/send_form.php", fd, {
      headers: { "Content-Type": "multipart/form-data" },
      timeout: 20000,
    });

    resultOk.value = !!res.data?.ok;
    resultMessage.value =
      res.data?.message ||
      (resultOk.value ? "Сообщение отправлено!" : "Ошибка при отправке");

    if (resultOk.value) {
      alertSuccess(resultMessage.value);
      resetForm();
    } else {
      alertError(resultMessage.value);
    }
  } catch (_) {
    resultOk.value = false;
    resultMessage.value = "Ошибка при отправке. Попробуйте позже.";
    alertError(resultMessage.value);
  } finally {
    submitting.value = false;
  }
}

function resetForm() {
  form.name = "";
  form.email = "";
  form.phone = "";
  form.subject = "";
  form.message = "";
  form.consent = false;
  files.value = [];
  Object.keys(touch).forEach((k) => (touch[k] = false));
}
</script>

<style scoped>
:root {
  --bg-grad-a: #1b1230;
  --bg-grad-b: #2a1545;
  --bg-grad-c: #35185a;
  --accent: #ff9900;
}

/* ====== LAYOUT & CARD ====== */
.contact {
  display: grid;
  place-items: center;
  padding: 80px 16px;
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
  color: #fff;
}
.container {
  width: 100%;
  max-width: 1100px;
}

.card {
  max-width: 920px;
  margin: 0 auto;
  width: 100%;
  padding: 40px;
  border-radius: 22px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(14px);
}

/* ====== TITLE ====== */
.title {
  font-size: 40px;
  font-weight: 800;
  margin-bottom: 28px;
  text-align: center;
  color: #ff9900;
}

/* ====== FORM GRID ====== */
.form {
  display: grid;
  grid-template-columns: 1fr;
  gap: 22px;
}
@media (min-width: 760px) {
  .form {
    grid-template-columns: 1fr 1fr;
    gap: 30px;
  }
}
.span-2 {
  grid-column: 1 / -1;
}

/* ====== FIELDS ====== */
.field {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

input,
textarea {
  width: 100%;
  height: 56px;
  padding: 18px 18px 0 18px;
  border-radius: 14px;
  border: 1px solid #9f9f9f;
  background: rgba(255, 255, 255, 0.05);
  color: #fff;
  font-size: 16px;
  transition: border-color 0.25s ease, box-shadow 0.25s ease,
    background 0.25s ease;
}
textarea {
  height: auto;
  min-height: 160px;
  padding-top: 26px;
  resize: vertical;
}

.field.floating > label {
  position: absolute;
  left: 16px;
  top: 18px;
  font-size: 15px;
  color: #cfc7de;
  pointer-events: none;
  transition: transform 0.2s ease, font-size 0.2s ease, top 0.2s ease,
    opacity 0.2s ease;
  opacity: 0.9;
}
.field.floating:focus-within > label,
.field.floating.filled > label {
  top: -12px;
  transform: translateY(-60%);
  font-size: 12px;
  opacity: 0.9;
  color: #ff9900;
}

input:focus,
textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 1px rgba(255, 153, 0, 0.2);
}
.invalid {
  border-color: #f87171 !important;
}

.hint {
  font-size: 12.5px;
  color: #cfc7de;
}
.hint.error {
  color: #ff7a7a;
}

/* ====== CONSENT ====== */
.consent .checkbox {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  font-size: 15px;
}
.consent input[type="checkbox"] {
  accent-color: #ff9900;
  width: 16px;
  height: 16px;
  transform: none;
}
.consent a {
  color: var(--accent);
  text-decoration: underline;
}
.consent a:hover {
  text-decoration: none;
}

/* ====== DROPZONE ====== */
.field-files .files-label {
  display: block;
  margin-bottom: 10px;
  font-weight: 600;
  color: #e9eef4;
}
.dropzone {
  position: relative;
  border: 2px dashed rgba(255, 255, 255, 0.25);
  border-radius: 16px;
  min-height: 140px;
  display: flex;
  justify-content: center;
  align-items: center;
  background: rgba(255, 255, 255, 0.04);
  transition: all 0.25s ease;
  cursor: pointer;
  overflow: hidden;
}
.dropzone:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: var(--accent);
}
.dropzone.dragging {
  background: rgba(255, 153, 0, 0.12);
  border-color: var(--accent);
}

/* важное: инпут больше не перекрывает клики по дропзоне */
.file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  left: -9999px;
  top: 0;
  opacity: 0;
  pointer-events: none;
}

.dropzone-inner {
  text-align: center;
  padding: 18px;
}
.drop-icon {
  font-size: 34px;
  margin-bottom: 8px;
  color: #ffbb44;
}
.drop-text {
  font-size: 15px;
  color: #d9d2eb;
}
.linklike {
  color: var(--accent);
  background: transparent;
  border: none;
  text-decoration: underline;
  cursor: pointer;
}

.files {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.files li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(255, 255, 255, 0.08);
  padding: 10px 14px;
  border-radius: 12px;
}
.files .meta {
  display: flex;
  gap: 10px;
  align-items: baseline;
}
.fname {
  font-weight: 600;
  max-width: 520px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fsize {
  color: #ccc;
  font-size: 13px;
}
.remove {
  border: none;
  background: transparent;
  color: #ff7a7a;
  cursor: pointer;
  font-size: 20px;
  line-height: 1;
}

/* ====== BUTTONS ====== */
.actions {
  display: flex;
  gap: 14px;
}
.btn {
  padding: 14px 24px;
  border-radius: 14px;
  font-weight: 700;
  font-size: 16px;
  border: none;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.25s ease, opacity 0.2s ease;
  background: #ff9900;
  color: #fff;
}
.btn:hover {
  transform: translateY(-2px);
}
.btn:disabled {
  cursor: not-allowed;
}

/* скрытый запасной вывод текста результата */
.result {
  margin-top: 8px;
  display: none;
}
.result.ok {
  color: #6fe09a;
  font-weight: 600;
}
.result.fail {
  color: #ff7a7a;
  font-weight: 600;
}
</style>
