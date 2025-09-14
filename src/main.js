// main.js
import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './style.css'

// Стили/иконки
import '@fortawesome/fontawesome-free/css/all.min.css'

// AOS (анимации при скролле)
import 'aos/dist/aos.css'
import AOS from 'aos'

const app = createApp(App)
app.use(router)

// Дождёмся инициализации роутера, смонтируем приложение и только потом запустим AOS
router.isReady().then(() => {
  app.mount('#app')

  AOS.init({
    duration: 700,          // длительность анимации (мс)
    offset: 80,             // отступ до старта анимации (px)
    easing: 'ease-out-cubic',
    once: true,             // анимация запускается один раз
    mirror: false,          // не повторять при скролле вверх
  })
})

// Обновляем AOS после каждой смены маршрута (чтобы анимации работали на новых экранах)
router.afterEach(() => {
  // ждём рендера нового контента, потом жёсткий refresh
  requestAnimationFrame(() => AOS.refreshHard())
})
