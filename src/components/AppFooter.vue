<template>
  <footer class="site-footer">
    <div class="container footer-grid">
      <!-- Логотип -->
      <div class="footer-logo">
        <img src="@/assets/logo/logo.png" alt="MindHack" />
      </div>

      <!-- Основное меню -->
      <nav class="footer-menu">
        <ul>
          <li><a href="#catalog">Каталог</a></li>
          <li><a href="#about">О нас</a></li>
          <li><a href="#contact">Обратная связь</a></li>
          <li><a href="#faq">Часто задаваемые вопросы</a></li>
        </ul>
      </nav>

      <!-- Политики -->
      <div class="footer-legal">
        <ul>
          <li><a href="/PrivacyPolicy">Политика конфиденциальности</a></li>
          <li><a href="/PublicOffer">Условия оферты</a></li>
        </ul>
      </div>

      <!-- Соцсети -->
      <div class="footer-social">
        <a
          href="https://www.instagram.com/mindhackkk/"
          target="_blank"
          rel="noopener"
          aria-label="Instagram"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="28"
            height="28"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path
              d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2zm0 1.5A4.25 4.25 0 0 0 3.5 7.75v8.5A4.25 4.25 0 0 0 7.75 20.5h8.5a4.25 4.25 0 0 0 4.25-4.25v-8.5A4.25 4.25 0 0 0 16.25 3.5h-8.5zm4.25 3.25a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm5.75-.88a1.13 1.13 0 1 1-2.25 0 1.13 1.13 0 0 1 2.25 0z"
            />
          </svg>
        </a>
      </div>
    </div>

    <!-- Нижняя часть -->
    <div class="footer-bottom">
      <p>© {{ new Date().getFullYear() }} MindHack. Все права защищены.</p>
    </div>
  </footer>
</template>

<script>
export default {
  name: "SiteFooter",
};
</script>

<style scoped>
.site-footer {
  background: #000000;
  color: #fff;
  padding: 50px 20px 20px;
  font-size: 15px;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.footer-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr auto;
  gap: 40px;
  align-items: start;
}

.footer-logo img {
  width: 200px;
  height: auto;
}

.footer-menu ul,
.footer-legal ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.footer-menu a,
.footer-legal a {
  color: #fff;
  text-decoration: none;
  transition: color 0.2s;
}

.footer-menu a:hover,
.footer-legal a:hover {
  color: #f90;
}

.footer-social a {
  color: #fff;
  transition: color 0.2s;
  display: inline-flex;
}

.footer-social a:hover {
  color: #f90;
}

.footer-bottom {
  margin-top: 35px;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  padding-top: 15px;
  text-align: center;
  font-size: 13px;
  color: #aaa;
}

/* Мобильная адаптация */
@media (max-width: 768px) {
  .footer-grid {
    grid-template-columns: 1fr;
    text-align: center;
  }

  .footer-menu ul,
  .footer-legal ul {
    align-items: center;
  }

  .footer-social {
    margin-top: 15px;
  }
}
</style>
