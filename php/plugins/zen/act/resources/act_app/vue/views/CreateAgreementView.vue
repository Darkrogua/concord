<template>
  <div class="concord-page concord-page--agreement-form">
    <ConcordPageHeader title="Новый лист согласования" show-back @back="$emit('back')" />

    <main class="concord-notifications">
      <AgreementFormFields :form="form" />
    </main>

    <footer class="concord-agreement-form__footer">
      <button
        type="button"
        class="concord-agreement-form__submit"
        :disabled="!canSave || hasInvalidDateRange"
        @click="saveDraft"
      >
        Сохранить
      </button>
    </footer>

    <ConcordConfirmSheet
      :open="datesWarningOpen"
      title="Сроки не указаны"
      message="Даты начала и окончания согласования не заполнены. Сохранить черновик без сроков?"
      confirm-label="Сохранить"
      cancel-label="Отмена"
      confirm-tone="primary"
      @confirm="confirmSaveWithoutDates"
      @cancel="datesWarningOpen = false"
    />
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import AgreementFormFields from '../concord/AgreementFormFields.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import {
  EMPTY_AGREEMENT_FORM,
  agreementFormHasDates,
  agreementFormHasInvalidRange,
  normalizeAgreementFormRange,
} from '../concord/agreement-form-utils.js'

export default {
  name: 'CreateAgreementView',
  components: { AgreementFormFields, ConcordConfirmSheet, ConcordPageHeader },
  emits: ['back', 'save-draft'],
  setup(props, { emit }) {
    const form = ref({ ...EMPTY_AGREEMENT_FORM })
    const datesWarningOpen = ref(false)

    const canSave = computed(() => Boolean(form.value.title.trim()))
    const hasInvalidDateRange = computed(() => agreementFormHasInvalidRange(form.value))

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

    function emitSave() {
      emit('save-draft', { ...form.value })
      datesWarningOpen.value = false
    }

    function saveDraft() {
      if (!canSave.value || hasInvalidDateRange.value) {
        return
      }
      if (!agreementFormHasDates(form.value)) {
        datesWarningOpen.value = true
        return
      }
      emitSave()
    }

    function confirmSaveWithoutDates() {
      emitSave()
    }

    return {
      form,
      canSave,
      hasInvalidDateRange,
      datesWarningOpen,
      saveDraft,
      confirmSaveWithoutDates,
    }
  },
}
</script>
