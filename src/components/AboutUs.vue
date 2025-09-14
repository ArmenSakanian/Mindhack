<!-- src/components/AboutUs.vue -->
<template>
  <section id="about" class="about">
    <div class="container">
      <!-- HERO -->
      <div class="hero">
        <h2 data-aos="fade-right">О нас — MindHack</h2>
        <p data-aos="fade-left">
          MindHack — это магазин готовых таблиц Excel и Google Sheets. Мы делаем
          простые и понятные решения, чтобы планировать задачи, считать финансы
          и навести порядок без лишней возни. Скачал — и работаешь.
        </p>
      </div>

      <!-- ПОЧЕМУ ВЫБИРАЮТ -->
      <div id="about-trust" class="trust">
        <ul class="trust-items" aria-label="Почему выбирают MindHack">
          <li data-aos="fade-right">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <div>
              <div class="d-title">Готово к использованию</div>
              <div class="trust-sub">
                Таблицы уже настроены под типовые задачи
              </div>
            </div>
          </li>
          <li data-aos="fade-down">
            <i class="fa-solid fa-bolt" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Моментальная доставка</div>
              <div class="trust-sub">
                Ссылка на скачивание приходит на почту сразу после оплаты
              </div>
            </div>
          </li>
          <li data-aos="fade-left">
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Поддержка по покупке</div>
              <div class="trust-sub">
                Поможем, если письмо не пришло или файл не открывается
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- ЧТО ВНУТРИ -->
      <div id="about-features" class="features">
        <h3>Что вы получаете</h3>
        <div class="features-grid">
          <div data-aos="fade-right" class="feat">
            <i class="fa-solid fa-table" aria-hidden="true"></i>
            <div>
              <div class="feat-title">Готовые таблицы</div>
              <div class="feat-sub">
                Excel / Google Sheets под конкретные сценарии
              </div>
            </div>
          </div>
          <div data-aos="fade-up" class="feat">
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            <div>
              <div class="feat-title">Автоматизация</div>
              <div class="feat-sub">
                Формулы, выпадающие списки, условное форматирование
              </div>
            </div>
          </div>
          <div data-aos="fade-left" class="feat">
            <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
            <div>
              <div class="feat-title">Графики и отчёты</div>
              <div class="feat-sub">Наглядная динамика и готовые сводки</div>
            </div>
          </div>
          <div data-aos="fade-right" class="feat">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <div>
              <div class="feat-title">Приватность</div>
              <div class="feat-sub">Все данные остаются у вас</div>
            </div>
          </div>
          <div data-aos="fade-up" class="feat">
            <i class="fa-solid fa-mobile-screen" aria-hidden="true"></i>
            <div>
              <div class="feat-title">Кроссплатформенность</div>
              <div class="feat-sub">
                Удобно на телефоне, планшете и компьютере
              </div>
            </div>
          </div>
          <div data-aos="fade-left" class="feat">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <div>
              <div class="feat-title">Доступ 24/7</div>
              <div class="feat-sub">Файлы и инструкции всегда под рукой</div>
            </div>
          </div>
        </div>
      </div>

      <!-- CTA (опционально, можно убрать или заменить на роутер-ссылку) -->
      <!--
      <div class="cta">
        <button class="btn-link" type="button" @click="$router.push('/#catalog')">Открыть каталог</button>
      </div>
      -->
    </div>
  </section>
</template>

<script>
export default {
  name: "AboutUs",
};
</script>

<style scoped>
/* БАЗА */
.about {
  position: relative;
  padding: 140px 24px 100px;
  color: #fff;
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
    linear-gradient(180deg, #0f0b1a, #121b30 45%, #153345 70%, #183e5a);
  overflow: hidden;
}
.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* HERO */
.hero {
  text-align: center;
  margin-bottom: 56px;
}
.hero h2 {
  font-size: 56px;
  line-height: 1.2;
  margin: 0 0 20px;
  color: var(--accent-color);
}
.hero p {
  color: #cfc7de;
  font-size: 22px;
  max-width: 900px;
  margin: 0 auto;
}

/* ССЫЛОЧНАЯ КНОПКА (если нужна в CTA) */
.btn-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-radius: 12px;
  border: 0;
  font-weight: 600;
  font-size: 16px;
  cursor: pointer;
  color: var(--accent-color);
  background: transparent;
  transition: transform 0.2s ease;
}
.btn-link:hover {
  transform: translateY(-2px);
}

/* ПОЧЕМУ ВЫБИРАЮТ */
.trust {
  margin: 40px 0 16px;
}
.trust-items {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
}
.trust-items li {
  display: flex;
  gap: 16px;
  align-items: center;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 18px 20px;
}
.trust-items i {
  font-size: 36px;
  color: var(--accent-color);
  filter: drop-shadow(0 2px 10px rgba(255, 153, 0, 0.28));
}
.trust-title {
  font-weight: 800;
  color: #fff;
  font-size: 20px;
}
.trust-sub {
  color: #cfc7de;
  font-size: 16px;
}

/* ЧТО ВНУТРИ */
.features {
  margin-top: 36px;
}
.features h3 {
  text-align: center;
  margin: 0 0 20px;
  color: var(--accent-color);
  font-size: 28px;
}
.features-grid {
  display: grid;
  gap: 18px;
  grid-template-columns: repeat(3, 1fr);
}
.feat {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 18px 20px;
}
.feat i {
  font-size: 28px;
  color: var(--accent-color);
  margin-top: 2px;
}
.feat-title {
  font-weight: 800;
  color: #fff;
  font-size: 20px;
}
.feat-sub {
  color: #cfc7de;
  font-size: 16px;
}

/* АДАПТИВ */
@media (max-width: 1100px) {
  .features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .trust-items {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 640px) {
  .features-grid,
  .trust-items {
    grid-template-columns: 1fr;
  }
  .hero h2 {
    font-size: 42px;
  }
  .hero p {
    font-size: 20px;
  }
}
</style>
