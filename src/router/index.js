import { createRouter, createWebHistory } from 'vue-router'
import HomePage from '@/pages/HomePage.vue'
import VideoBanner from '@/components/VideoBanner.vue'
import AdminLogin from '@/pages/AdminPage.vue'
import AdminDashboard from '@/pages/AdminDashboard.vue'
import AdminCategories from '@/components/AdminCategories.vue'
import AdminProducts from '@/components/AdminProducts.vue'
import CategoryProducts from '@/pages/CategoryProducts.vue'
import FaqAccordion from '@/components/FaqAccordion.vue'
import PrivacyPolicy from '@/pages/PrivacyPolicy.vue'
import PublicOffer from '@/pages/PublicOffer.vue'
import MarketingConsent from '@/pages/MarketingConsent.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: HomePage },
    { path: '/VideoBanner', component: VideoBanner },
    { path: '/admin', component: AdminLogin },
    { path: '/admin/dashboard', component: AdminDashboard, meta: { requiresAuth: true } },
    { path: '/admin/categories', component: AdminCategories, meta: { requiresAuth: true } },
    { path: '/admin/products', component: AdminProducts, meta: { requiresAuth: true } },

    { path: '/category/:id(\\d+)', name: 'category', component: CategoryProducts, props: true },
    { path: '/category', name: 'categoryQuery', component: CategoryProducts },
    { path: '/FaqAccordion', name: 'FaqAccordion', component: FaqAccordion},
    { path: '/PrivacyPolicy', name: 'PrivacyPolicy', component: PrivacyPolicy},
    { path: '/PublicOffer', name: 'PublicOffer', component: PublicOffer},
    { path: '/MarketingConsent', name: 'MarketingConsent', component: MarketingConsent},



    // Корзина
    { path: '/cart', name: 'cart', component: () => import('@/pages/CartPage.vue') },

    // НОВОЕ: оформление и страница успеха
    { path: '/checkout', name: 'checkout', component: () => import('@/pages/Checkout.vue') },
    { path: '/payment/success', name: 'paymentSuccess', component: () => import('@/pages/PaymentSuccess.vue') },
  ],
})

// гард как был
router.beforeEach(async (to, from, next) => {
  if (!to.meta.requiresAuth) return next()
  try {
    const res = await fetch('/php/session_check.php', { credentials: 'include' })
    const data = await res.json()
    data?.ok ? next() : next('/admin')
  } catch {
    next('/admin')
  }
})

export default router
