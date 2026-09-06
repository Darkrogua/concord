<template>
  <div class="concord-page concord-landing">
    <ConcordPageHeader chrome>
      <template #left>
        <h1 class="concord-page-header__brand">
          <ConcordLogoMark size="sm" />
          <span class="concord-page-header__brand-name">Concord</span>
        </h1>
      </template>
      <template #right>
        <a v-if="auth.isAuthenticated" class="concord-btn concord-btn--ghost" href="/agreements">К согласованиям</a>
        <a v-else class="concord-btn concord-btn--primary" href="/login">Войти</a>
      </template>
    </ConcordPageHeader>

    <section class="concord-landing-hero">
      <h2 class="concord-landing-hero__title">Документ не готов, пока не сказали все.</h2>
      <p class="concord-landing-hero__lead">
        Разделы, голоса и подписи в одном месте. Согласование проходит, только если каждый участник ответил «да».
      </p>
      <div class="concord-landing-hero__actions">
        <a v-if="auth.isAuthenticated" class="concord-btn concord-btn--primary" href="/agreements">Открыть согласования</a>
        <a v-else class="concord-btn concord-btn--primary" href="/login">Войти</a>
      </div>
    </section>
  </div>
</template>

<script setup>
import ConcordPageHeader from '../components/concord/ConcordPageHeader.vue'
import ConcordLogoMark from '../components/concord/ConcordLogoMark.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>

<style scoped>
.concord-landing {
  min-height: 100vh;
  background: var(--concord-shell-muted);
}

.concord-landing-hero {
  padding: 2rem var(--concord-page-gutter) 4rem;
  max-width: 640px;
}

.concord-landing-hero__title {
  margin: 0 0 1rem;
  font-size: clamp(1.75rem, 5vw, 2.25rem);
  font-weight: var(--concord-weight-semibold);
  letter-spacing: var(--concord-tracking-title);
  line-height: var(--concord-leading-tight);
}

.concord-landing-hero__lead {
  margin: 0 0 1.5rem;
  color: var(--concord-text-muted);
  line-height: var(--concord-leading-relaxed);
}

.concord-landing-hero__actions {
  display: flex;
  gap: 0.75rem;
}

.concord-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 0 1.1rem;
  border-radius: var(--concord-radius-sm);
  font-weight: var(--concord-weight-semibold);
  text-decoration: none;
}

.concord-btn--primary {
  background: var(--concord-primary);
  color: #fff;
}

.concord-btn--ghost {
  background: transparent;
  color: var(--concord-text);
  border: 1px solid var(--concord-border);
}
</style>
