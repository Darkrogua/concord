<template>
  <div class="concord-page concord-landing">
    <header class="concord-landing__header">
      <router-link to="/" class="concord-landing__brand">
        <ConcordLogoMark size="sm" />
        <span>Concord</span>
      </router-link>
      <div class="concord-landing__header-actions">
        <a
          v-if="auth.isAuthenticated"
          class="concord-landing__btn concord-landing__btn--ghost"
          href="/agreements"
        >
          К согласованиям
        </a>
        <a v-else class="concord-landing__btn concord-landing__btn--primary" href="/login">
          Войти
        </a>
      </div>
    </header>

    <section class="concord-landing__hero">
      <div class="concord-landing__hero-copy">
        <p class="concord-landing__eyebrow">Согласование документов</p>
        <h1 class="concord-landing__title">
          Документ не готов, пока не сказали все.
        </h1>
        <p class="concord-landing__lead">
          Разделы, голоса и подписи в одном месте. Согласование проходит,
          только если каждый участник ответил «да».
        </p>
        <div class="concord-landing__hero-actions">
          <a
            v-if="auth.isAuthenticated"
            class="concord-landing__btn concord-landing__btn--primary concord-landing__btn--large"
            href="/agreements"
          >
            Открыть согласования
          </a>
          <template v-else>
            <a class="concord-landing__btn concord-landing__btn--primary concord-landing__btn--large" href="/login">
              Начать бесплатно
            </a>
            <a class="concord-landing__btn concord-landing__btn--ghost concord-landing__btn--large" href="/login">
              Войти
            </a>
          </template>
        </div>
      </div>

      <div class="concord-landing__preview" aria-hidden="true">
        <div class="concord-landing__preview-glow" />
        <div class="concord-landing__preview-card">
          <div class="concord-landing__preview-header">
            <span class="concord-landing__preview-number">#1042</span>
            <span class="concord-landing__preview-status">Ждёт решения</span>
          </div>
          <div class="concord-landing__preview-body">
            <h2 class="concord-landing__preview-title">Договор аренды офиса, корпус Б</h2>
            <div class="concord-landing__preview-meta">
              <span class="concord-landing__preview-avatar">АК</span>
              <span>Анна Козлова · до 12 сентября</span>
            </div>
            <div class="concord-landing__preview-progress">
              <div class="concord-landing__preview-track">
                <div class="concord-landing__preview-fill" />
              </div>
              <span class="concord-landing__preview-count">3 из 4</span>
            </div>
            <div class="concord-landing__preview-votes">
              <div class="concord-landing__preview-vote">
                <span class="concord-landing__preview-vote-name">Юридический отдел</span>
                <span class="concord-landing__preview-vote-badge concord-landing__preview-vote-badge--yes">Да</span>
              </div>
              <div class="concord-landing__preview-vote">
                <span class="concord-landing__preview-vote-name">Финансы</span>
                <span class="concord-landing__preview-vote-badge concord-landing__preview-vote-badge--yes">Да</span>
              </div>
              <div class="concord-landing__preview-vote">
                <span class="concord-landing__preview-vote-name">Директор</span>
                <span class="concord-landing__preview-vote-badge concord-landing__preview-vote-badge--wait">Ожидает</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="concord-landing__features">
      <h2 class="concord-landing__section-title">Всё для согласования</h2>
      <div class="concord-landing__feature-grid">
        <article class="concord-landing__feature">
          <div class="concord-landing__feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 6h16M4 12h10M4 18h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
          </div>
          <h3 class="concord-landing__feature-title">Разделы</h3>
          <p class="concord-landing__feature-text">
            Структурируйте документ по блокам — текст, файлы, галерея. Участники видят только нужное.
          </p>
        </article>
        <article class="concord-landing__feature">
          <div class="concord-landing__feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M7 11.5 10 14.5 17 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
              <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6" />
            </svg>
          </div>
          <h3 class="concord-landing__feature-title">Голоса</h3>
          <p class="concord-landing__feature-text">
            Каждый участник голосует отдельно. Прогресс виден сразу — кто уже согласился, кто ещё думает.
          </p>
        </article>
        <article class="concord-landing__feature">
          <div class="concord-landing__feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 4h12v16H6z" stroke="currentColor" stroke-width="1.6" />
              <path d="M9 8h6M9 12h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
          </div>
          <h3 class="concord-landing__feature-title">Подписи</h3>
          <p class="concord-landing__feature-text">
            Финальный документ фиксируется только после единогласного «да» от всех согласующих.
          </p>
        </article>
      </div>
    </section>

    <section class="concord-landing__steps">
      <h2 class="concord-landing__section-title">Как это работает</h2>
      <ol class="concord-landing__steps-list">
        <li class="concord-landing__step">
          <span class="concord-landing__step-num">1</span>
          <div>
            <h3 class="concord-landing__step-title">Создайте согласование</h3>
            <p class="concord-landing__step-text">Добавьте разделы, участников и срок.</p>
          </div>
        </li>
        <li class="concord-landing__step">
          <span class="concord-landing__step-num">2</span>
          <div>
            <h3 class="concord-landing__step-title">Соберите голоса</h3>
            <p class="concord-landing__step-text">Участники получают уведомления и голосуют в приложении.</p>
          </div>
        </li>
        <li class="concord-landing__step">
          <span class="concord-landing__step-num">3</span>
          <div>
            <h3 class="concord-landing__step-title">Зафиксируйте результат</h3>
            <p class="concord-landing__step-text">Когда все согласны — документ готов к подписанию.</p>
          </div>
        </li>
      </ol>
    </section>

    <section class="concord-landing__cta">
      <h2 class="concord-landing__cta-title">Готовы собрать согласия?</h2>
      <p class="concord-landing__cta-text">
        Создайте первое согласование за пару минут.
      </p>
      <a
        v-if="auth.isAuthenticated"
        class="concord-landing__btn concord-landing__btn--primary concord-landing__btn--large"
        href="/agreements/create"
      >
        Создать согласование
      </a>
      <a
        v-else
        class="concord-landing__btn concord-landing__btn--primary concord-landing__btn--large"
        href="/register"
      >
        Зарегистрироваться
      </a>
    </section>

    <footer class="concord-landing__footer">
      Concord — согласование документов
    </footer>
  </div>
</template>

<script setup>
import ConcordLogoMark from '../components/concord/ConcordLogoMark.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>
