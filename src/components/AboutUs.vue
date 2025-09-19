<!-- src/components/AboutUs.vue -->
<template>
  <section id="about" class="about">
    <div class="container">
      <!-- HERO -->
      <div class="hero">
        <h2 data-aos="fade-right">
          MindHack — готовые таблицы, которые работают за вас
        </h2>
        <p data-aos="fade-left">
          MindHack - магазин готовых таблиц Excel и Google Sheets. Мы создаем
          простые и понятные решения для планирования задач и финансов,
          благодаря которым вы систематизируете свою жизнь без лишней возни.
        </p>
      </div>

      <!-- ПОЧЕМУ ВЫБИРАЮТ -->
      <div id="about-trust" class="trust">
        <ul class="trust-items" aria-label="Почему выбирают MindHack">
          <li data-aos="fade-right">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Мгновенный доступ</div>
              <div class="trust-sub">
                Формулы отчеты и графики заранее подготовленные - открываете и
                пользуетесь
              </div>
            </div>
          </li>
          <li data-aos="fade-down">
            <i class="fa-solid fa-bolt" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Моментальная доставка</div>
              <div class="trust-sub">
                Ссылка на шаблон приходит сразу после оплаты
              </div>
            </div>
          </li>
          <li data-aos="fade-left">
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Поддержка 24/7</div>
              <div class="trust-sub">
                Поможем с доступом, установкой и иными сложностями
              </div>
            </div>
          </li>
          <li data-aos="fade-left">
            <i class="fa-solid fa-mobile" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Удобно на любом устройстве</div>
              <div class="trust-sub">
                Наши таблицы работают адапативно на всех гаджетах
              </div>
            </div>
          </li>
          <li data-aos="fade-left">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Ваши данные — в безопасности</div>
              <div class="trust-sub">
                Никаких серверов: файлы хранятся локально у вас
              </div>
            </div>
          </li>
          <li data-aos="fade-left">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <div>
              <div class="trust-title">Доступ 24/7</div>
              <div class="trust-sub">
                Файлы и инструкции всегда под рукой в вашем устройстве
              </div>
            </div>
          </li>
        </ul>
      </div>


      <!-- CTA (по желанию) -->
      <!--
      <div class="cta">
        <button class="btn-link" type="button" @click="$router.push('/#catalog')">
          Посмотреть каталог
        </button>
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
  font-size: 54px;
  line-height: 1.2;
  margin: 0 0 20px;
  color: var(--accent-color);
}
.hero p {
  color: #cfc7de;
  font-size: 20px;
  max-width: 900px;
  margin: 0 auto;
}

/* LINK BUTTON */
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

/* TRUST */
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

/* FEATURES */


/* АДАПТИВ */
@media (max-width: 1100px) {
  .trust-items {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 640px) {
  .trust-items {
    grid-template-columns: 1fr;
  }
  .hero h2 {
    font-size: 40px;
  }
  .hero p {
    font-size: 18px;
  }
}
</style>
