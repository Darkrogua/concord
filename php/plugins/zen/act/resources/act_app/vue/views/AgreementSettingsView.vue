<template>
  <div class="concord-page concord-page--agreement-form concord-page--agreement-settings">
    <ConcordPageHeader title="Настройки согласования" show-back @back="save">
      <template #right>
        <ConcordGroupHeaderActions
          :show-count="false"
          :disabled="hasInvalidDateRange"
          @save="save"
        />
      </template>
    </ConcordPageHeader>

    <main class="concord-notifications">
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
import { computed, ref, watch, defineExpose } from 'vue'
import AgreementFormFields from '../concord/AgreementFormFields.vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import {
  agreementFormHasInvalidRange,
  agreementFormToPayload,
  createAgreementFormFromAgreement,
  normalizeAgreementFormRange,
} from '../concord/agreement-form-utils.js'

export default {
  name: 'AgreementSettingsView',
  components: { AgreementFormFields, ConcordGroupHeaderActions, ConcordPageHeader },
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

    function hasUnsavedChanges() {
      const payload = agreementFormToPayload(form.value)
      const baseline = agreementFormToPayload(createAgreementFormFromAgreement(props.agreement))
      return JSON.stringify(payload) !== JSON.stringify(baseline)
    }

    function save() {
      const payload = agreementFormToPayload(form.value)
      if (!payload.title || hasInvalidDateRange.value) {
        return
      }

      emit('save', payload)
      emit('back')
    }

    defineExpose({ hasUnsavedChanges, save })

    return { form, hasInvalidDateRange, save }
  },
}
</script>
