<template>
  <div class="landing">
    <header class="landing-top">
      <strong>Concord</strong>
    </header>

    <main class="landing-hero">
      <p class="landing-kicker">Согласование документов</p>
      <h1>Один документ.<br />Все должны сказать «да».</h1>
      <p class="landing-lead">
        Разделы, голоса и подписи в одном месте — без цепочки писем и потерянных версий.
      </p>
      <div class="landing-actions">
        <a v-if="auth.isAuthenticated" class="landing-btn" href="/agreements">К согласованиям</a>
        <a v-else class="landing-btn" href="/login">Войти</a>
      </div>
    </main>
  </div>
</template>

<script setup>
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>
