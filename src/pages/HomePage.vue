<template>
  <div>
    <!-- <slide /> -->
    <CategoryGrid />
    <AboutUs />
    <ContactForm />
    <FaqAccordion />
  </div>
</template>

<script>
import slide from "../components/slide.vue";
import CategoryGrid from "../components/CategoryGrid.vue";
import AboutUs from "../components/AboutUs.vue";
import ContactForm from "../components/ContactForm.vue";
import FaqAccordion from "../components/FaqAccordion.vue";

export default {
  name: "HomePage",
  components: {
    slide,
    CategoryGrid,
    AboutUs,
    ContactForm,
    FaqAccordion,

  },
};
</script>


<style scoped>
.home {
  min-height: calc(100vh - 120px);
  padding-top: 40px;
  padding-left: 16px;
  padding-right: 16px;
}

.title {
  font-size: 32px;
  font-weight: 800;
  text-align: center;
  line-height: 1.2;
  margin-bottom: 12px;
}
</style>
