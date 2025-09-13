<!-- src/components/OfferTerms.vue -->
<template>
  <section class="offer" aria-labelledby="offer-title" v-once>
    <h1 id="offer-title" class="sr-only">Публичная оферта</h1>
    <pre class="offer-text">
ПУБЛИЧНАЯ ОФЕРТА

    </pre>
  </section>
</template>

<script>
export default {
  name: 'OfferTerms',
};
</script>

<style scoped>
.offer {
  width: 100%;
  margin: 0;
  padding: 90px 30px;
  background: #ffffff;
  color: #222;
}

.offer-text {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-size: 16px;
  line-height: 1.8;
  color: #333;
  max-width: 1200px;
  margin: 0 auto;
}

/* первая строка (заголовок всего документа) */
.offer-text::first-line {
  font-weight: 800;
  font-size: 22px;
  color: #000;
}

/* заголовки внутри текста */
.offer-text h2,
.offer-text h3 {
  margin: 32px 0 16px;
  font-size: 24px;
  font-weight: 800;
  color: #111;
  border-left: 4px solid #f90;
  padding-left: 12px;
}

.offer-text strong,
.offer-text b {
  color: #f90;
  font-weight: 600;
}
</style>

