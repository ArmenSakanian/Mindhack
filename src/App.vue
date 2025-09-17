<template>
  <div class="app">
    <AppHeader />
    <main>
      <router-view />
    </main>
    <AppFooter />
  </div>
</template>

<script setup>
import AppHeader from "@/components/AppHeader.vue";
import AppFooter from "@/components/AppFooter.vue";
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

main {
  min-height: 100vh;
}


html {
  scroll-behavior: smooth;
}

body {
font-family: "Rubik", sans-serif;
  font-optical-sizing: auto;
  font-style: normal;
  background: #222222;
  display: flex;
  flex-direction: column;
  flex: 1;
}
/* опционально, если где-то нет margin: */
.section {
  scroll-margin-top: 90px;
}
</style>
