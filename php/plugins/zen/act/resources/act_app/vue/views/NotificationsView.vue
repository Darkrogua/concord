<template>
  <div class="concord-page concord-page--alerts">
    <ConcordPageHeader title="События" show-back @back="$emit('back')" />

    <main class="concord-alerts">
      <p v-if="!visibleSections.length" class="concord-alerts__empty">
        Пока нет уведомлений
      </p>

      <template v-for="section in visibleSections" :key="section.dateLabel">
        <div class="concord-alerts__day-divider">
          <span>{{ section.dateLabel }}</span>
        </div>

        <article
          v-for="item in section.items"
          :key="item.id"
          :class="[
            'concord-alerts__message',
            item.isRead ? 'concord-alerts__message--read' : 'concord-alerts__message--unread',
          ]"
          @click="openAgreement(item)"
        >
          <div class="concord-alerts__avatar-wrap">
            <img
              v-if="item.avatarUrl"
              :src="item.avatarUrl"
              alt=""
              class="concord-alerts__avatar concord-alerts__avatar--image"
            >
            <span v-else class="concord-alerts__avatar" aria-hidden="true">{{ item.avatarInitial }}</span>
            <span v-if="!item.isRead" class="concord-alerts__unread-dot" aria-hidden="true" />
          </div>

          <div class="concord-alerts__bubble">
            <p v-if="item.title" class="concord-alerts__bubble-sender">{{ item.title }}</p>
            <p class="concord-alerts__bubble-text">
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
            <time v-if="item.time" class="concord-alerts__bubble-time">{{ item.time }}</time>
          </div>
        </article>
      </template>
    </main>
  </div>
</template>

<script>
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'

export default {
  name: 'NotificationsView',
  components: { ConcordPageHeader },
  props: {
    sections: {
      type: Array,
      required: true,
    },
  },
  emits: ['back', 'open-agreement', 'mark-read'],
  computed: {
    visibleSections() {
      return this.sections
        .map((section) => ({
          ...section,
          items: section.items.filter((item) => !item.empty),
        }))
        .filter((section) => section.items.length > 0)
    },
  },
  methods: {
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
