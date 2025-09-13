<template>
  <section class="admin">
    <header class="admin__header">
      <h1 class="admin__title">Админ-панель</h1>
      <button class="admin__logout" @click="logout">Выйти</button>
    </header>

    <div class="tiles">
      <!-- единственная плитка -->
      <RouterLink to="/admin/categories" class="tile">
        <h2 class="tile__title">Управление категориями</h2>
      </RouterLink>
          <RouterLink to="/admin/products" class="tile">
        <h2 class="tile__title">Управление продуктамы</h2>
      </RouterLink>
    </div>
  </section>
</template>

<script setup>
import { RouterLink } from 'vue-router'

function logout() {
  fetch('/php/logout.php', { credentials: 'include', cache: 'no-store' })
    .finally(() => { window.location.href = '/admin' })
}
</script>

<style scoped>
.admin {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  padding: 32px;
  color: #f3f4f6;

  /* красивый фон на весь экран */
  background:
    radial-gradient(1000px 600px at 0% 0%, rgba(255,153,0,0.15), transparent 70%),
    radial-gradient(800px 400px at 100% 20%, rgba(135,77,255,0.15), transparent 70%),
    linear-gradient(160deg, #0f0b1a, #1b1230 45%, #2a1545 70%, #35185a);
}

.admin__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}

.admin__title {
  font-size: 26px;
  font-weight: 700;
  margin: 0;
}

.admin__logout {
  border: 0;
  border-radius: 10px;
  background: #dc2626;
  color: #fff;
  padding: 10px 16px;
  font-weight: 600;
  cursor: pointer;
  transition: filter .2s ease, transform .06s ease;
}
.admin__logout:hover { filter: brightness(.95); }
.admin__logout:active { transform: translateY(1px); }

.tiles {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  max-width: 600px;
}

.tile {
  display: block;
  text-decoration: none;
  border-radius: 16px;
  padding: 32px;
  border: 1px solid rgba(255,255,255,0.1);
  background: rgba(255,255,255,0.04);
  backdrop-filter: blur(6px);
  color: #fff;
  transition: transform .1s ease, box-shadow .2s ease;
  box-shadow: 0 8px 20px rgba(0,0,0,.25);
}
.tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(0,0,0,.35);
}
.tile__title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}
</style>
