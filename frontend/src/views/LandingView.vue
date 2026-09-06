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
        <Button
          v-if="auth.isAuthenticated"
          label="К согласованиям"
          size="large"
          @click="$router.push('/agreements')"
        />
        <Button v-else label="Войти" size="large" @click="$router.push('/login')" />
      </div>
    </main>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>
