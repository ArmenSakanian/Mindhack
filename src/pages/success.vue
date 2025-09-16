<template>
  <section class="payment-result">
    <div class="bg"></div>

    <div class="wrap card">
      <h1 class="title">Оплата прошла успешно 🎉</h1>
      <p class="subtitle">
        Спасибо за покупку! Ссылка на ваш цифровой продукт отправлена на указанный e-mail.
      </p>

      <div class="actions">
        <router-link to="/" class="btn primary">Вернуться в каталог</router-link>
      </div>
    </div>
  </section>
</template>

<script setup>
// здесь ничего не нужно — просто статическая страница
</script>

<style scoped>
.payment-result {
  position: relative;
  min-height: 100vh;
  padding-top: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #ececf3;
}

/* Фон */
.bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(1000px 500px at 20% -10%, rgba(255,153,0,.08), transparent 60%),
    radial-gradient(700px 350px at 80% 10%, rgba(135,77,255,.1), transparent 55%),
    linear-gradient(1deg, #0f0b1a, #121b30 45%, #153345 70%, #183e5a);
  z-index: 0;
}

.wrap {
  position: relative;
  z-index: 1;
  max-width: 600px;
  padding: 40px;
  border-radius: 16px;
  background: rgba(20, 20, 28, 0.65);
  border: 1px solid rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.35);
}

.title {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 16px;
}

.subtitle {
  font-size: 16px;
  color: #a9a9b3;
  margin-bottom: 28px;
}

.actions .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 48px;
  padding: 0 22px;
  border-radius: 12px;
  background: linear-gradient(135deg, #874dff, var(--accent-color));
  color: #fff;
  font-weight: 600;
  text-decoration: none;
  box-shadow: 0 8px 24px rgba(135, 77, 255, 0.4);
  transition: 0.25s ease;
}
.actions .btn:hover {
  box-shadow: 0 12px 28px rgba(135, 77, 255, 0.55);
}
</style>
