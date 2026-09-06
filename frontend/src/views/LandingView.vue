<template>
  <div class="landing">
    <a class="skip-link" href="#main">К содержанию</a>
    <header class="landing-top">
      <span class="brand" translate="no">Concord</span>
      <a v-if="auth.isAuthenticated" class="btn btn-ghost" href="/agreements">К согласованиям</a>
      <a v-else class="btn btn-ghost" href="/login">Войти</a>
    </header>

    <main id="main" class="landing-hero">
      <div class="landing-copy">
        <h1>Документ не готов, пока не сказали все.</h1>
        <p class="landing-lead">
          Concord собирает разделы, голоса и подписи в одном месте. Согласование проходит, только если каждый участник ответил «да».
        </p>
        <div class="landing-actions">
          <a v-if="auth.isAuthenticated" class="btn btn-primary" href="/agreements">Открыть согласования</a>
          <a v-else class="btn btn-primary" href="/login">Войти</a>
          <a class="btn btn-ghost" href="#how">Как это устроено</a>
        </div>
      </div>

      <div class="doc-stack" aria-hidden="true">
        <article class="doc-sheet">
          <h2>Регламент поставки</h2>
          <div class="doc-row"><span>Сроки и ответственность</span><strong>3 / 3</strong></div>
          <div class="doc-row"><span>Финансовые условия</span><strong>2 / 3</strong></div>
          <div class="doc-row"><span>Приложения</span><strong>ожидает</strong></div>
        </article>
      </div>
    </main>

    <section id="how" class="landing-process">
      <div>
        <span>01</span>
        <h2>Разделы</h2>
        <p>Документ режется на независимые части. Каждую согласовывают свои люди.</p>
      </div>
      <div>
        <span>02</span>
        <h2>Голос</h2>
        <p>Только «да» или «нет» с комментарием. Один отказ возвращает раздел автору.</p>
      </div>
      <div>
        <span>03</span>
        <h2>Подпись</h2>
        <p>Голос привязан к активной подписи. История остаётся вместе с документом.</p>
      </div>
    </section>

    <footer class="landing-foot">Concord — единогласное согласование документов</footer>
  </div>
</template>

<script setup>
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>
