<template>
  <div class="concord-page concord-page--notifications">
    <ConcordPageHeader title="Уведомления" show-back @back="$emit('back')" />

    <main class="concord-notifications">
      <section class="concord-settings-group">
        <div class="concord-profile-card">
          <label class="concord-notifications__toggle-row">
            <span>Общие чаты</span>
            <input v-model="notificationSettings.generalChats" type="checkbox" class="concord-toggle">
          </label>

          <label class="concord-notifications__toggle-row">
            <span>Личные чаты</span>
            <input v-model="notificationSettings.personalChats" type="checkbox" class="concord-toggle">
          </label>

          <label class="concord-notifications__toggle-row">
            <span>Группы</span>
            <input v-model="notificationSettings.groups" type="checkbox" class="concord-toggle">
          </label>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { computed, ref, toRef } from 'vue'
import { DEFAULT_NOTIFICATION_SETTINGS } from '../concord/mock-notifications.js'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import { useConcordProfile } from '../composables/useConcordProfile.js'

export default {
  name: 'NotificationSettingsView',
  components: { ConcordPageHeader },
  props: {
    accountId: {
      type: String,
      default: '1',
    },
  },
  emits: ['back'],
  setup(props) {
    const { profile } = useConcordProfile(toRef(props, 'accountId'))
    const notificationSettings = ref({ ...DEFAULT_NOTIFICATION_SETTINGS })
    const fullName = computed(() => `${profile.value.firstName} ${profile.value.lastName}`.trim())

    return { profile, notificationSettings, fullName }
  },
}
</script>
