<template>
  <section class="admin-wrap">
    <div class="card">
      <div class="head">
        <h1>Вход в админ-панель</h1>
        <p>Введите логин и пароль администратора</p>
      </div>

      <form class="form" @submit.prevent="onSubmit">
        <label class="field">
          <span>Логин</span>
          <input
            v-model.trim="form.login"
            type="text"
            placeholder="admin"
            autocomplete="username"
            required
          />
        </label>

        <label class="field">
          <span>Пароль</span>
          <div class="pwd">
            <input
              v-model="form.password"
              :type="showPwd ? 'text' : 'password'"
              placeholder="••••••••"
              autocomplete="current-password"
              required
              minlength="6"
            />
            <button type="button" class="toggle" @click="showPwd = !showPwd">
              {{ showPwd ? 'Скрыть' : 'Показать' }}
            </button>
          </div>
        </label>

        <button class="submit" type="submit" :disabled="loading">
          <span v-if="!loading">Войти</span>
          <span v-else>Проверяем…</span>
        </button>

        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="success" class="success">Готово! Перенаправляем…</p>
      </form>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const form = ref({ login: '', password: '' })
const error = ref('')
const success = ref(false)
const loading = ref(false)
const showPwd = ref(false)

// если уже авторизованы — сразу уводим в дашборд
onMounted(async () => {
  try {
    const res = await fetch(`/php/session_check.php?ts=${Date.now()}`, {
      credentials: 'include',
      cache: 'no-store'
    })
    const data = await res.json().catch(() => ({}))
    if (data?.ok) {
      window.location.href = '/admin/dashboard'
    }
  } catch {}
})

async function onSubmit() {
  error.value = ''
  success.value = false
  loading.value = true
  try {
    const body = new FormData()
    body.append('login', form.value.login)
    body.append('password', form.value.password)

    const res = await fetch('/php/login.php', {
      method: 'POST',
      body,
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
    })

    const data = await res.json().catch(() => null)
    if (!res.ok || !data?.ok) throw new Error(data?.message || 'Неверный логин или пароль')

    success.value = true

    // простукиваем сессию
    await fetch(`/php/session_check.php?ts=${Date.now()}`, {
      credentials: 'include',
      cache: 'no-store'
    }).catch(() => {})

    // железный редирект
    window.location.href = '/admin/dashboard'
  } catch (e) {
    error.value = e?.message || 'Не удалось войти'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.admin-wrap {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 24px;
  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(255, 153, 0, 0.12), transparent 60%),
    radial-gradient(800px 400px at 90% 10%, rgba(135, 77, 255, 0.15), transparent 55%),
    linear-gradient(160deg, #0f0b1a, #1b1230 45%, #2a1545 70%, #35185a);
}

.card {
  width: min(560px, 100%);
  background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02)), #17181d;
  border: 1px solid #24262c;
  border-radius: 20px;
  padding: 32px;
  box-shadow: 0 10px 30px rgba(0,0,0,.35);
  backdrop-filter: blur(6px);
}

.head h1 {
  font-size: 22px;
  margin: 0 0 6px;
  letter-spacing: .3px;
  color: #ffffff;
}

.head p {
  margin: 0 0 20px;
  font-size: 14px;
  color: #d1d5db;
}

.form { display: grid; gap: 16px; }

.field span {
  display: block;
  margin: 0 0 8px;
  font-size: 13px;
  color: #e5e7eb;
}

input {
  width: 100%;
  background: #0f1014;
  color: #f3f4f6;
  border: 1px solid #2d2f36;
  border-radius: 12px;
  padding: 14px 14px;
  outline: none;
  transition: border-color .2s, box-shadow .2s, transform .02s;
  font-size: 14px;
}
input::placeholder { color: #6b7280; }
input:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99,102,241,.25);
}

.pwd { position: relative; display: grid; }
.toggle {
  position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
  background: transparent; color: #9ca3af; border: 0; padding: 6px 10px;
  border-radius: 10px; cursor: pointer; font-size: 13px;
}
.toggle:hover { color: #e5e7eb; }

.submit {
  margin-top: 6px; width: 100%; border: 0; border-radius: 14px;
  padding: 14px 16px; font-weight: 600;
  background: #ffffff; color: #111827;
  cursor: pointer; transition: transform .06s ease, filter .2s ease;
  font-size: 15px;
}
.submit:hover { filter: brightness(.96); }
.submit:active { transform: translateY(1px); }
.submit:disabled { opacity: .6; cursor: not-allowed; }

.error, .success { margin-top: 6px; font-size: 13px; line-height: 1.4; }
.error { color: #f87171; }
.success { color: #34d399; }
</style>
