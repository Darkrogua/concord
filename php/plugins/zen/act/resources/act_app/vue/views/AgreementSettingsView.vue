<template>
  <div class="concord-page concord-page--agreement-form concord-page--agreement-settings">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="save">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Настройки согласования</h1>
      <ConcordGroupHeaderActions :show-count="false" @save="save" />
    </header>

    <main class="concord-section-settings">
      <AgreementFormFields :form="form" />
    </main>

    <footer class="concord-section-settings__footer">
      <button
        type="button"
        class="concord-section-settings__done"
        :disabled="hasInvalidDateRange"
        @click="save"
      >
        Готово
      </button>
    </footer>
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import AgreementFormFields from '../concord/AgreementFormFields.vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import {
  agreementFormHasInvalidRange,
  agreementFormToPayload,
  createAgreementFormFromAgreement,
  normalizeAgreementFormRange,
} from '../concord/agreement-form-utils.js'

export default {
  name: 'AgreementSettingsView',
  components: { AgreementFormFields, ConcordGroupHeaderActions },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
  },
  emits: ['back', 'save'],
  setup(props, { emit }) {
    const form = ref(createAgreementFormFromAgreement(props.agreement))

    const hasInvalidDateRange = computed(() => agreementFormHasInvalidRange(form.value))

    watch(
      () => props.agreement,
      (agreement) => {
        if (!agreement) {
          return
        }
        form.value = createAgreementFormFromAgreement(agreement)
      },
      { immediate: true }
    )

    watch(
      () => [form.value.startDate, form.value.startTime],
      () => {
        form.value = normalizeAgreementFormRange(form.value)
      }
    )

    watch(
      () => [form.value.endDate, form.value.endTime],
      () => {
        form.value = normalizeAgreementFormRange(form.value)
      }
    )

    function save() {
      const payload = agreementFormToPayload(form.value)
      if (!payload.title || hasInvalidDateRange.value) {
        return
      }

      emit('save', payload)
      emit('back')
    }

    return { form, hasInvalidDateRange, save }
  },
}
</script>
