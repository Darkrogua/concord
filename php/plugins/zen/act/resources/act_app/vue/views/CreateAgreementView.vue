<template>
  <div class="concord-page concord-page--agreement-form">
    <header class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Новый лист согласования</h1>
    </header>

    <main class="concord-section-settings">
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
import {
  EMPTY_AGREEMENT_FORM,
  agreementFormHasDates,
  agreementFormHasInvalidRange,
  normalizeAgreementFormRange,
} from '../concord/agreement-form-utils.js'

export default {
  name: 'CreateAgreementView',
  components: { AgreementFormFields, ConcordConfirmSheet },
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
