<template>
  <section id="home" class="video-banner" aria-label="Hero video">
    <video
      ref="vid"
      class="video"
      preload="auto"
      autoplay
      muted
      loop
      playsinline
      webkit-playsinline
      x5-playsinline
      disablePictureInPicture
      controlslist="nodownload noplaybackrate noremoteplayback"
    >
      <!-- Мобильное видео до 768px -->
      <source src="@/assets/video/home_mobile.mp4" type="video/mp4" media="(max-width: 768px)" />
      <!-- Десктопное видео 1920x600 -->
      <source src="@/assets/video/home.mp4" type="video/mp4" media="(min-width: 769px)" />
      <!-- Фолбэк -->
      Ваш браузер не поддерживает воспроизведение видео.
    </video>
  </section>
</template>

<script>
export default {
  name: "VideoBanner",
  mounted() {
    const video = this.$refs.vid;

    // На всякий случай включаем mute/inline до play()
    video.muted = true;
    video.setAttribute("muted", "");
    video.setAttribute("playsinline", "");
    video.setAttribute("webkit-playsinline", "");
    video.setAttribute("x5-playsinline", "");
    video.setAttribute("autoplay", "");
    video.loop = true;

    const tryPlay = async () => {
      try {
        const p = video.play();
        // Некоторые браузеры возвращают promise
        if (p && typeof p.then === "function") await p;
      } catch (e) {
        // Автоплей мог быть заблокирован — повторим попытку чуть позже
        setTimeout(() => {
          video.muted = true;
          video.play().catch(() => {});
        }, 500);
      }
    };

    // 1) Первая попытка сразу
    tryPlay();

    // 2) Повторная попытка, когда вкладка становится видимой
    const onVis = () => {
      if (!document.hidden) tryPlay();
    };
    document.addEventListener("visibilitychange", onVis);

    // 3) Триггер от первого взаимодействия пользователя (жест/клик/скролл)
    const onceInteract = () => {
      tryPlay();
      window.removeEventListener("touchstart", onceInteract);
      window.removeEventListener("click", onceInteract);
      window.removeEventListener("scroll", onceInteract);
    };
    window.addEventListener("touchstart", onceInteract, { once: true, passive: true });
    window.addEventListener("click", onceInteract, { once: true });
    window.addEventListener("scroll", onceInteract, { once: true, passive: true });

    // 4) Автовоспроизведение при появлении в зоне видимости
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            tryPlay();
          } else {
            // Не паузим: для баннера лучше непрерывный цикл
            // video.pause();
          }
        });
      },
      { threshold: 0.1 }
    );
    io.observe(video);

    // Очистка
    this._cleanup = () => {
      document.removeEventListener("visibilitychange", onVis);
      try {
        io.disconnect();
      } catch {}
    };
  },
  beforeUnmount() {
    this._cleanup && this._cleanup();
  },
};
</script>

<style scoped>
.video-banner {
  height:calc(100vh - 90px);;
  margin-top: 90px;
  width: 100%;
  overflow: hidden;

  display: flex;                /* добавляем flex */
  justify-content: center;      /* по центру по горизонтали */
  align-items: center;          /* по центру по вертикали */

  background: radial-gradient(1200px 600px at 10% -10%, rgba(255, 153, 0, .08), transparent 60%),
    radial-gradient(800px 400px at 90% 10%, rgba(135, 77, 255, .1), transparent 55%),
    linear-gradient(180deg, #0f0b1a, #121b30 45%, #153345 70%, #183e5a);
}

/* Поддерживаем нужную «полосу» 1920x600 (соотношение 3.2) */
.video {
  width: 100%;
  height: auto;
  display: block;
  object-fit: cover;
  /* aspect-ratio: 1920 / 600; */
}

/* На мобильных можно позволить больше высоты экрана */
@media (max-width: 768px) {
  .video {
    aspect-ratio: auto;          /* мобильное видео под своё соотношение */
    height: auto;
    max-height: 70vh;            /* чтобы не занимать весь экран */
  }
}
</style>
