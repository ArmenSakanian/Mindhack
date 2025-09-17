<template>
  <section id="feedback" class="contact">
    <div class="container">
      <div data-aos="fade-up" class="card">
        <h2 class="title">Обратная связь</h2>

        <form class="form" @submit.prevent="onSubmit" novalidate>
          <!-- NAME -->
          <div class="field floating" :class="{ filled: form.name }">
            <input id="name" v-model.trim="form.name" type="text" required autocomplete="name" @blur="touch.name = true"
              :class="{ invalid: touch.name && !validName }" />
            <label for="name">Имя *</label>
          </div>

          <!-- EMAIL -->
          <div class="field floating" :class="{ filled: form.email }">
            <input id="email" v-model.trim="form.email" type="email" required autocomplete="email"
              @blur="touch.email = true" :class="{ invalid: touch.email && !validEmail }" />
            <label for="email">E-mail *</label>
            <p v-if="touch.email && !validEmail" class="hint error">
              Введите корректный e-mail.
            </p>
          </div>

          <!-- PHONE (необязательно) -->
          <div class="field floating" :class="{ filled: cleanDigits(form.phone).length > 1 }">
            <input id="phone" v-model="form.phone" type="tel" autocomplete="tel" inputmode="tel"
              :placeholder="isPhoneFocused ? '+7 (___) ___-__-__' : ''" @focus="onPhoneFocus" @blur="onPhoneBlur"
              @input="formatPhone" :class="{ invalid: touch.phone && form.phone && !validPhone }" />
            <label for="phone">Телефон</label>
            <p v-if="touch.phone && form.phone && !validPhone" class="hint error">
              Формат: +7 (XXX) XXX-XX-XX
            </p>
          </div>

          <!-- SUBJECT -->
          <div class="field floating" :class="{ filled: form.subject }">
            <input id="subject" v-model.trim="form.subject" type="text" required @blur="touch.subject = true"
              :class="{ invalid: touch.subject && !validSubject }" />
            <label for="subject">Тема *</label>
            <p v-if="touch.subject && !validSubject" class="hint error">
              Минимум 3 символа.
            </p>
          </div>

          <!-- MESSAGE -->
          <div class="field floating span-2" :class="{ filled: form.message }">
            <textarea id="message" v-model.trim="form.message" rows="6" required @blur="touch.message = true"
              :class="{ invalid: touch.message && !validMessage }"></textarea>
            <label for="message">Сообщение *</label>
            <p v-if="touch.message && !validMessage" class="hint error">
              Минимум 10 символов.
            </p>
          </div>

          <!-- CONSENT: Политика (обязательно) -->
          <div class="consent span-2">
            <label class="checkbox">
              <input type="checkbox" v-model="form.policy" required @blur="touch.policy = true" />
              <span>
                Я согласен(на) с
                <a href="/PrivacyPolicy" class="check__link" target="_blank">политикой конфиденциальности</a> <b class="req">*</b>
              </span>
            </label>
            <p v-if="touch.policy && !form.policy" class="hint error">
              Необходимо согласие.
            </p>
          </div>

          <!-- CONSENT: Рекламные рассылки (необязательно) -->
          <div class="consent span-2">
            <label class="checkbox">
              <input type="checkbox" v-model="form.marketing" />
              <span>
                Согласен(на) на получение
                <a href="/MarketingConsent" class="check__link" target="_blank">рекламных материалов</a>
              </span>
            </label>
          </div>

          <!-- ACTIONS -->
          <div class="actions span-2">
            <button class="btn--primary btn" type="submit" :disabled="!formValid || submitting">
              <span v-if="!submitting">Отправить</span>
              <span v-else>Отправка…</span>
            </button>
          </div>
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
const form = reactive({
  name: "",
  email: "",
  phone: "",
  subject: "",
  message: "",
  policy: false,     // обязательно
  marketing: false,  // опционально
});

const touch = reactive({
  name: false,
  email: false,
  phone: false,
  subject: false,
  message: false,
  policy: false,
});

const submitting = ref(false);

/* --------- validators --------- */
const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
const validName = computed(() => form.name.trim().length > 0);
const validEmail = computed(() => emailRe.test(form.email));

function cleanDigits(v) {
  return (v || "").replace(/\D/g, "");
}

const validPhone = computed(() => {
  const digits = cleanDigits(form.phone);
  if (!digits.length) return true; // пустое поле — ок
  return digits.length === 11 && digits.startsWith("7");
});

const validSubject = computed(() => form.subject.trim().length >= 3);
const validMessage = computed(() => form.message.trim().length >= 10);

const formValid = computed(
  () =>
    validName.value &&
    validEmail.value &&
    validSubject.value &&
    validMessage.value &&
    form.policy // только политика обязательна
);

const isPhoneFocused = ref(false);
function onPhoneFocus() { isPhoneFocused.value = true; }
function onPhoneBlur() { isPhoneFocused.value = false; touch.phone = true; }
function formatPhone(e) {
  let digits = cleanDigits(e.target.value);
  if (digits.startsWith("8")) digits = "7" + digits.slice(1);
  if (!digits.length) { form.phone = ""; return; }
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

/* --------- submit --------- */
async function onSubmit() {
  Object.keys(touch).forEach((k) => (touch[k] = true));
  if (!formValid.value) return;

  submitting.value = true;

  try {
    const fd = new FormData();
    fd.append("name", form.name);
    fd.append("email", form.email);
    fd.append("phone", form.phone);
    fd.append("subject", form.subject);
    fd.append("message", form.message);
    fd.append("policy", String(form.policy));
    fd.append("marketing", String(form.marketing)); // отправляем, даже если false

    const res = await axios.post("/php/send_form.php", fd, {
      headers: { "Content-Type": "multipart/form-data" },
      timeout: 20000,
    });

    if (res.data?.ok) {
      Swal.fire({
        icon: "success",
        title: "Отправлено",
        text: res.data?.message || "Сообщение отправлено!",
        confirmButtonText: "Ок",
        confirmButtonColor: "var(--accent-color)",
      });
      resetForm();
    } else {
      Swal.fire({
        icon: "error",
        title: "Ошибка",
        text: res.data?.message || "Не удалось отправить сообщение",
        confirmButtonText: "Понятно",
        confirmButtonColor: "var(--accent-color)",
      });
    }
  } catch {
    Swal.fire({
      icon: "error",
      title: "Ошибка",
      text: "Ошибка при отправке. Попробуйте позже.",
      confirmButtonText: "Понятно",
      confirmButtonColor: "var(--accent-color)",
    });
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
  form.policy = false;
  form.marketing = false;
  Object.keys(touch).forEach((k) => (touch[k] = false));
}
</script>

<style scoped>

.contact {
  display: grid;
  place-items: center;
  padding: 80px 16px;
  background: linear-gradient(1deg, #0f0b1a, #121b30 45%, #153345 70%, #183e5a);
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

.title {
  font-size: 40px;
  font-weight: 800;
  margin-bottom: 28px;
  text-align: center;
  color: var(--accent-color);
}

.form {
  display: grid;
  grid-template-columns: 1fr;
  gap: 22px;
}

@media(min-width:760px) {
  .form {
    grid-template-columns: 1fr 1fr;
    gap: 30px;
  }
}

.span-2 {
  grid-column: 1 / -1;
}

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
}

textarea {
  height: auto;
  min-height: 160px;
  padding-top: 26px;
  resize: vertical;
}

.field.floating>label {
  position: absolute;
  left: 16px;
  top: 18px;
  font-size: 15px;
  color: #cfc7de;
  transition: all 0.2s ease;
  opacity: 0.9;
}

.field.floating:focus-within>label,
.field.floating.filled>label {
  top: -12px;
  transform: translateY(-60%);
  font-size: 12px;
  color: var(--accent-color);
}

input:focus,
textarea:focus {
  outline: none;
  border-color: var(--accent-color);
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

.req {
  color: var(--accent-color);
  font-weight: 600;
}

.consent .checkbox {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  font-size: 15px;
}




.consent .hint.error {
  margin-left: 28px;
}

.field-files .files-label {
  margin-bottom: 10px;
  font-weight: 600;
}

.dropzone {
  border: 2px dashed rgba(255, 255, 255, 0.25);
  border-radius: 16px;
  min-height: 140px;
  display: flex;
  justify-content: center;
  align-items: center;
  background: rgba(255, 255, 255, 0.04);
  cursor: pointer;
}

.dropzone:hover {
  border-color: var(--accent);
}

.dropzone.dragging {
  background: rgba(255, 153, 0, 0.12);
  border-color: var(--accent);
}

.file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  left: -9999px;
}

.dropzone-inner {
  text-align: center;
  padding: 18px;
}

.drop-icon {
  font-size: 34px;
  margin-bottom: 8px;
  color: var(--accent-color);
}

.drop-text {
  font-size: 15px;
  color: #d9d2eb;
}

.linklike {
  color: var(--accent-color);
  text-decoration: underline;
  cursor: pointer;
  background: none;
  border: none;
}

.files {
  margin: 12px 0 0;
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
  background: none;
  border: none;
  color: #ff7a7a;
  cursor: pointer;
  font-size: 20px;
}

.actions {
  display: flex;
  gap: 14px;
}


.btn:disabled {
  cursor: not-allowed;
}

.result {
  display: none;
}

.result.ok {
  color: #6fe09a;
}

.result.fail {
  color: #ff7a7a;
}</style>