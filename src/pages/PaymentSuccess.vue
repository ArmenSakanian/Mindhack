<template>
  <section class="success">
    <h1>Оплата прошла успешно ✅</h1>
    <p>Спасибо за покупку! Мы отправили подтверждение на ваш e-mail.</p>
    <RouterLink to="/" class="btn">Вернуться на главную</RouterLink>
  </section>
</template>

<script setup>
</script>

<style scoped>
.success {
  padding: 40px;
  text-align: center;
}
h1 { font-size: 28px; margin-bottom: 16px; }
.btn {
  display: inline-block;
  padding: 10px 20px;
  border-radius: 8px;
  background: #4f46e5;
  color: #fff;
  text-decoration: none;
}
</style>
