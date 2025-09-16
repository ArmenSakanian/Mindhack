<template>
  <section
    class="slider"
    role="region"
    aria-label="Галерея"
    tabindex="0"
    @mouseenter="pause()"
    @mouseleave="resume()"
    @keydown.left.prevent="goPrev(true)"
    @keydown.right.prevent="goNext(true)"
    ref="root"
  >
    <!-- Слайды -->
    <div class="slides" @touchstart="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd">
      <img
        v-for="(src, i) in slides"
        :key="i"
        :src="src"
        :alt="`Слайд ${i+1}`"
        :class="['slide', { active: i === current }]"
        draggable="false"
      />
      <!-- Виньетка + градиент -->
      <div class="overlay"></div>
    </div>

    <!-- Прогресс-бар авто-листания -->
    <div class="progress" v-if="slides.length > 1">
      <div class="bar" :style="progressStyle"></div>
    </div>

    <!-- Стрелки -->
    <button
      class="arrow left"
      @click="goPrev(true)"
      aria-label="Предыдущий слайд"
      v-if="slides.length > 1"
    >
      <span class="arrow-icon">‹</span>
    </button>
    <button
      class="arrow right"
      @click="goNext(true)"
      aria-label="Следующий слайд"
      v-if="slides.length > 1"
    >
      <span class="arrow-icon">›</span>
    </button>

    <!-- Индикаторы -->
    <div class="dots" role="tablist" aria-label="Переключатели слайдов" v-if="slides.length > 1">
      <button
        v-for="(_, i) in slides"
        :key="'dot-'+i"
        class="dot"
        :class="{ active: i === current }"
        :aria-selected="i === current ? 'true' : 'false'"
        role="tab"
        @click="goTo(i, true)"
      />
    </div>
  </section>
</template>

<script>
const LIST_URL = '/php/slides/list.php';

export default {
  name: "FullSlider",
  data() {
    return {
      slides: [],           // массив URL из БД
      current: 0,
      timerId: null,
      duration: 5000,       // 5 сек
      paused: false,
      // Для свайпа
      touchX: null,
      touchLock: false,
      // Прогресс (0..1)
      progress: 0,
      rafId: null,
      tickStart: 0,
      // загрузка/ошибки (на будущее, если захочешь показать стейт)
      loading: false,
      error: ''
    };
  },
  computed: {
    progressStyle() {
      return { transform: `scaleX(${this.progress})` };
    },
  },
  async mounted() {
    await this.loadSlides();
    if (this.slides.length > 1) this.startCycle();
    // если длина 0 или 1 — автопрокрутка не нужна
  },
  beforeUnmount() {
    this.stopCycle();
  },
  methods: {
    async loadSlides() {
      this.loading = true; this.error = '';
      try {
        const res = await fetch(LIST_URL, { cache: 'no-store' });
        const j = await res.json();
        if (!j.ok) throw new Error(j.message || 'Не удалось загрузить слайды');

        // фильтруем активные, сортируем по position
        const active = (j.items || [])
          .filter(s => Number(s.is_active) === 1)
          .sort((a, b) => Number(a.position) - Number(b.position));

        this.slides = active.map(s => s.path);
        // сбрасываем индекс и прогресс
        this.current = 0;
        this.progress = 0;

        // если ранее был запущен цикл — перезапускаем/останавливаем корректно
        this.stopCycle();
        if (this.slides.length > 1 && !this.paused) this.startCycle();
      } catch (e) {
        this.error = e.message || 'Ошибка загрузки';
        // в случае ошибки оставляем пустой массив/старые данные
      } finally {
        this.loading = false;
      }
    },

    // ===== Автоцикл =====
    startCycle() {
      if (this.slides.length <= 1) return;
      this.stopCycle();
      this.tickStart = performance.now();
      this.progress = 0;
      this.rafId = requestAnimationFrame(this.tick);
      this.timerId = setTimeout(() => this.goNext(false), this.duration);
    },
    stopCycle() {
      clearTimeout(this.timerId);
      if (this.rafId) cancelAnimationFrame(this.rafId);
      this.timerId = null;
      this.rafId = null;
    },
    pause() {
      if (this.slides.length <= 1) return;
      this.paused = true;
      this.stopCycle();
    },
    resume() {
      if (this.slides.length <= 1) return;
      this.paused = false;
      this.startCycle();
    },
    tick(now) {
      const elapsed = now - this.tickStart;
      this.progress = Math.min(1, elapsed / this.duration);
      if (this.progress < 1 && !this.paused && this.slides.length > 1) {
        this.rafId = requestAnimationFrame(this.tick);
      }
    },

    // ===== Переключение =====
    goNext(manual) {
      if (!this.slides.length) return;
      this.current = (this.current + 1) % this.slides.length;
      if (manual) this.restartAfterManual();
      else this.startCycle();
    },
    goPrev(manual) {
      if (!this.slides.length) return;
      this.current = (this.current - 1 + this.slides.length) % this.slides.length;
      if (manual) this.restartAfterManual();
      else this.startCycle();
    },
    goTo(i, manual) {
      if (!this.slides.length || i === this.current) return;
      this.current = i;
      if (manual) this.restartAfterManual();
      else this.startCycle();
    },
    restartAfterManual() {
      // Сбрасываем таймер и анимацию прогресса
      if (this.slides.length <= 1) return;
      this.paused = false;
      this.startCycle();
    },

    // ===== Свайпы =====
    onTouchStart(e) {
      if (this.touchLock || this.slides.length <= 1) return;
      this.touchX = e.touches[0].clientX;
      this.pause();
    },
    onTouchMove(e) {
      if (this.touchX === null) return;
      const dx = e.touches[0].clientX - this.touchX;
      if (Math.abs(dx) > 10) this.touchLock = true;
    },
    onTouchEnd(e) {
      if (this.touchX === null) return;
      const dx = (e.changedTouches?.[0]?.clientX ?? this.touchX) - this.touchX;
      const threshold = 50; // пикселей
      if (dx > threshold) this.goPrev(true);
      else if (dx < -threshold) this.goNext(true);
      else this.resume();
      this.touchX = null;
      this.touchLock = false;
    },
  },
};
</script>

<style scoped>
/* Базовая область */
.slider {
  position: relative;
  width: 100%;
  height: 100vh;              /* полноэкранный */
  overflow: hidden;
  background: #0b0b0f;        /* запасной фон, если нет изображения */
  outline: none;
}

/* Контейнер слайдов */
.slides {
  position: relative;
  width: 100%;
  height: 100%;
  isolation: isolate;
}

/* Слайды */
.slide {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transform: scale(1.04);             /* лёгкий zoom-out при входе */
  transition:
    opacity 800ms ease,
    transform 5000ms ease;            /* Ken Burns */
  will-change: opacity, transform;
  user-select: none;
  pointer-events: none;
}
.slide.active {
  opacity: 1;
  transform: scale(1);                /* плавный Ken Burns к 1.00 */
}

/* Градиентная виньетка, чтобы текст/контролы читались всегда */
.overlay {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(120% 120% at 50% 50%, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.45) 70%, rgba(0,0,0,0.75) 100%),
    linear-gradient(to top, rgba(0,0,0,0.45), rgba(0,0,0,0));
  z-index: 1;
  pointer-events: none;
}

/* Стрелки */
.arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 3;
  width: 56px;
  height: 56px;
  border: 0;
  border-radius: 999px;
  cursor: pointer;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  background: rgba(255,255,255,0.12);
  box-shadow: 0 10px 30px rgba(0,0,0,0.35), inset 0 0 0 1px rgba(255,255,255,0.2);
  color: #fff;
  display: grid;
  place-items: center;
  transition: transform 160ms ease, background 160ms ease, box-shadow 160ms ease;
}
.arrow:hover {
  transform: translateY(-50%) scale(1.06);
  background: rgba(255,255,255,0.18);
  box-shadow: 0 14px 38px rgba(0,0,0,0.45), inset 0 0 0 1px rgba(255,255,255,0.28);
}
.arrow:active {
  transform: translateY(-50%) scale(0.98);
}
.arrow.left { left: 22px; }
.arrow.right { right: 22px; }
.arrow-icon {
  font-size: 28px;
  line-height: 1;
  transform: translateY(-1px);
}

/* Индикаторы-точки */
.dots {
  position: absolute;
  z-index: 3;
  bottom: 22px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 10px;
}
.dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: rgba(255,255,255,0.35);
  border: 1px solid rgba(255,255,255,0.5);
  box-shadow: 0 2px 10px rgba(0,0,0,0.35);
  cursor: pointer;
  transition: transform 160ms ease, background 160ms ease, width 200ms ease;
}
.dot:hover { transform: scale(1.15); }
.dot.active {
  background: #fff;
  width: 26px;               /* “капсула” для активного */
}

/* Прогресс-линия (5 сек) */
.progress {
  position: absolute;
  z-index: 3;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 3px;
  background: rgba(255,255,255,0.16);
  overflow: hidden;
}
.bar {
  height: 100%;
  transform-origin: left center;
  background: linear-gradient(90deg, #ffb43a, #ff9900, #ff6a00);
  box-shadow: 0 0 18px rgba(255,153,0,0.45);
  transition: transform 120ms linear;
}

/* Адаптив */
@media (max-width: 768px) {
  .arrow { width: 48px; height: 48px; }
  .arrow.left { left: 12px; }
  .arrow.right { right: 12px; }
  .dots { bottom: 16px; gap: 8px; }
}
</style>
