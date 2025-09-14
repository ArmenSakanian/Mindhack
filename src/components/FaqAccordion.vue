<template>
  <section class="faq" id="faq" aria-labelledby="faq-title">
    <div class="faq__container">
      <header class="faq__head">
        <h2 id="faq-title" class="faq__title">Часто задаваемые вопросы</h2>
        <p class="faq__subtitle">Всё, что нужно знать про покупку и использование таблиц</p>
      </header>

      <ul class="faq__list">
        <!-- Вопрос 1 -->
        <li class="faq__item" :class="{ open: openIndex === 0 }">
          <h3 class="faq__q">
            <button class="faq__btn" @click="toggle(0)">
              <span>Как получить доступ к таблицам?</span>
              <span class="faq__icon" aria-hidden="true">+</span>
            </button>
          </h3>
          <div class="faq__panel" v-show="openIndex === 0">
            <p class="faq__answer">
              После оплаты на вашу почту, указанную при оплате, автоматически придёт ссылка на таблицу.
            </p>
          </div>
        </li>

        <!-- Вопрос 2 -->
        <li class="faq__item" :class="{ open: openIndex === 1 }">
          <h3 class="faq__q">
            <button class="faq__btn" @click="toggle(1)">
              <span>Могу ли я использовать таблицу на нескольких устройствах или телефоне?</span>
              <span class="faq__icon" aria-hidden="true">+</span>
            </button>
          </h3>
          <div class="faq__panel" v-show="openIndex === 1">
            <p class="faq__answer">
              Да. Таблицы открываются в <b>Google Таблицах</b> и адаптированы под все устройства, включая мобильные.
            </p>
          </div>
        </li>

        <!-- Вопрос 3 -->
        <li class="faq__item" :class="{ open: openIndex === 2 }">
          <h3 class="faq__q">
            <button class="faq__btn" @click="toggle(2)">
              <span>Что будет, если я потеряю или удалю таблицу?</span>
              <span class="faq__icon" aria-hidden="true">+</span>
            </button>
          </h3>
          <div class="faq__panel" v-show="openIndex === 2">
            <p class="faq__answer">
              Ничего страшного. В Google Таблицах есть история изменений. Даже если удалили из своих документов, у вас остаётся доступ по ссылке из письма.
            </p>
          </div>
        </li>

        <!-- Вопрос 4 -->
        <li class="faq__item" :class="{ open: openIndex === 3 }">
          <h3 class="faq__q">
            <button class="faq__btn" @click="toggle(3)">
              <span>Почему это платно?</span>
              <span class="faq__icon" aria-hidden="true">+</span>
            </button>
          </h3>
          <div class="faq__panel" v-show="openIndex === 3">
            <p class="faq__answer">
              Все наши таблицы <b>уникальны</b> и создаются вручную: уже настроены формулы, диаграммы и графики — вам остаётся только ввести свои данные.
            </p>
          </div>
        </li>

        <!-- Вопрос 5 -->
        <li class="faq__item" :class="{ open: openIndex === 4 }">
          <h3 class="faq__q">
            <button class="faq__btn" @click="toggle(4)">
              <span>Что потребуется для использования таблицы?</span>
              <span class="faq__icon" aria-hidden="true">+</span>
            </button>
          </h3>
          <div class="faq__panel" v-show="openIndex === 4">
            <p class="faq__answer">
              Любое устройство: компьютер, ноутбук, телефон или планшет. Достаточно доступа к Google Таблицам.
            </p>
          </div>
        </li>

        <!-- Вопрос 6 -->
        <li class="faq__item" :class="{ open: openIndex === 5 }">
          <h3 class="faq__q">
            <button class="faq__btn" @click="toggle(5)">
              <span>После оплаты ничего не пришло. Что делать?</span>
              <span class="faq__icon" aria-hidden="true">+</span>
            </button>
          </h3>
          <div class="faq__panel" v-show="openIndex === 5">
            <p class="faq__answer">
              Опишите ситуацию через <a href="#contact">форму обратной связи</a> на сайте — мы свяжемся с вами и поможем.
            </p>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'
const openIndex = ref(-1)
const toggle = (i) => {
  openIndex.value = openIndex.value === i ? -1 : i
}
</script>

<style scoped>
.faq {
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
  padding: 40px 16px;
}

.faq__container {
  max-width: 900px;
  margin: 0 auto;
}

.faq__head {
  margin-bottom: 24px;
  text-align: center;
}

.faq__title {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 8px;
  color: #ff9900;
  letter-spacing: 0.5px;
}

.faq__subtitle {
  color: #eee;
  margin: 0;
  font-size: 15px;
}

.faq__list {
  display: grid;
  gap: 12px;
  padding: 0;
  margin: 0;
  list-style: none;
}

.faq__item {
  border: 1px solid #333;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.faq__item.open {
  border-color: #ff9900;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
}

.faq__btn {
  width: 100%;
  text-align: left;
  padding: 18px 20px;
  background: none;
  border: none;
  font-size: 16px;
  font-weight: 600;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  color: #222;
}

.faq__btn:focus {
  outline: none;
  background: #f7f7f7;
}

.faq__icon {
  font-size: 20px;
  transition: transform 0.3s ease, color 0.3s ease;
}

.faq__item.open .faq__icon {
  transform: rotate(45deg);
  color: #ff9900;
}

.faq__panel {
  padding: 0 20px 18px;
}

.faq__answer {
  margin-top: 10px;
  font-size: 15.5px;
  color: #444;
  line-height: 1.6;
}

.faq__answer b {
  color: #000;
}

.faq__answer a {
  color: #007bff;
  text-decoration: underline;
  text-underline-offset: 2px;
  font-weight: 500;
}
</style>

