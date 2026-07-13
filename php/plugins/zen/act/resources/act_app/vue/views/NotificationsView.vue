<template>
  <div class="concord-page concord-page--alerts">
    <header class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Уведомления</h1>
    </header>

    <main class="concord-alerts">
      <section
        v-for="section in sections"
        :key="section.dateLabel"
        class="concord-alerts__section"
      >
        <h2 class="concord-alerts__date">{{ section.dateLabel }}</h2>

        <article
          v-for="item in section.items"
          :key="item.id"
          :class="[
            'concord-alerts__card',
            item.empty ? 'concord-alerts__card--empty' : '',
            item.isRead ? 'concord-alerts__card--read' : 'concord-alerts__card--unread',
          ]"
          @click="onCardClick(item)"
        >
          <template v-if="!item.empty">
            <div class="concord-alerts__card-top">
              <img
                v-if="item.avatarUrl"
                :src="item.avatarUrl"
                alt=""
                class="concord-alerts__avatar concord-alerts__avatar--image"
              >
              <span v-else class="concord-alerts__avatar" aria-hidden="true">{{ item.avatarInitial }}</span>

              <div class="concord-alerts__content">
                <h3 class="concord-alerts__title">{{ item.title }}</h3>
                <p class="concord-alerts__text">
                  {{ item.body }}
                  <button
                    v-if="item.linkText"
                    type="button"
                    class="concord-alerts__link"
                    @click.stop="openAgreement(item)"
                  >
                    {{ item.linkText }}
                  </button>
                </p>
              </div>
            </div>

            <button
              type="button"
              class="concord-alerts__action"
              @click.stop="openAgreement(item)"
            >
              перейти
              <span class="concord-alerts__action-arrow" aria-hidden="true">→</span>
            </button>
          </template>
        </article>
      </section>
    </main>
  </div>
</template>

<script>
export default {
  name: 'NotificationsView',
  props: {
    sections: {
      type: Array,
      required: true,
    },
  },
  emits: ['back', 'open-agreement', 'mark-read'],
  methods: {
    onCardClick(item) {
      if (item.empty || !item.agreementId) {
        return
      }
      this.openAgreement(item)
    },
    openAgreement(item) {
      if (!item.agreementId) {
        return
      }
      this.$emit('mark-read', item.id)
      this.$emit('open-agreement', item.agreementId)
    },
  },
}
</script>
