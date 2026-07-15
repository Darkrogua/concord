<template>
  <div class="concord-page concord-page--editor">
    <header class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Новый лист согласования</h1>
    </header>

    <main class="concord-agreement-create">
      <label class="concord-agreement-create__field">
        <span class="concord-agreement-create__label">название проекта</span>
        <input
          v-model="form.title"
          class="concord-agreement-create__input"
          type="text"
          placeholder="Введите название проекта"
        >
      </label>

      <label class="concord-agreement-create__field">
        <span class="concord-agreement-create__label">описание проекта</span>
        <textarea
          v-model="form.description"
          class="concord-agreement-create__input concord-agreement-create__textarea"
          rows="4"
          placeholder="Введите текст"
        />
      </label>

      <div class="concord-agreement-create__field">
        <div class="concord-agreement-create__dates-head">
          <span class="concord-agreement-create__label">сроки согласования</span>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
            <rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.6"/>
            <path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="concord-agreement-create__dates">
          <label class="concord-agreement-create__date-field">
            <span class="concord-agreement-create__date-label">Дата начала</span>
            <input v-model="form.startDate" class="concord-agreement-create__input" type="date">
          </label>
          <label class="concord-agreement-create__date-field">
            <span class="concord-agreement-create__date-label">Дата окончания</span>
            <input v-model="form.endDate" class="concord-agreement-create__input" type="date">
          </label>
        </div>
      </div>

      <div class="concord-agreement-create__actions">
        <button type="button" class="concord-agreement-create__btn concord-agreement-create__btn--cancel" @click="$emit('back')">
          Отмена
        </button>
        <button
          type="button"
          class="concord-agreement-create__btn concord-agreement-create__btn--save"
          :disabled="!canSave"
          @click="saveDraft"
        >
          Сохранить
        </button>
      </div>
    </main>

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
import { computed, ref } from 'vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'

const EMPTY_FORM = {
  title: '',
  description: '',
  startDate: '',
  endDate: '',
}

export default {
  name: 'CreateAgreementView',
  components: { ConcordConfirmSheet },
  emits: ['back', 'save-draft'],
  setup(props, { emit }) {
    const form = ref({ ...EMPTY_FORM })
    const datesWarningOpen = ref(false)

    const canSave = computed(() => Boolean(form.value.title.trim()))

    const hasAgreementDates = computed(() =>
      Boolean(form.value.startDate && form.value.endDate)
    )

    function emitSave() {
      emit('save-draft', { ...form.value })
      datesWarningOpen.value = false
    }

    function saveDraft() {
      if (!canSave.value) {
        return
      }
      if (!hasAgreementDates.value) {
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
      datesWarningOpen,
      saveDraft,
      confirmSaveWithoutDates,
    }
  },
}
</script>
