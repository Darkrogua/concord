<template>
  <AccessSettingsModal
    :open="open"
    title="Настройки блока"
    title-id="block-settings-modal-title"
    :act-id="actId"
    resource-type="block"
    :resource-id="block?.id || ''"
    :saving="saving"
    :error="error"
    public-label="Наследовать публичный доступ"
    public-hint="Если включено, блок виден всем, кто видит акт. Пустой список пользователей — наследование от акта."
    users-hint="Явный список сужает доступ к этому блоку (в рамках прав акта)."
    :show-role-picker="true"
    default-role-value="viewer"
    @close="$emit('close')"
    @save="$emit('save', $event)"
  />
</template>

<script>
import AccessSettingsModal from '../AccessSettingsModal.vue'

export default {
  name: 'BlockSettingsModal',
  components: { AccessSettingsModal },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    actId: {
      type: String,
      required: true,
    },
    block: {
      type: Object,
      default: null,
    },
    saving: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'save'],
}
</script>
