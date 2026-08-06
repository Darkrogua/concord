<template>
  <div class="concord-page concord-page--editor concord-page--agreement-settings">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="save">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Настройки согласования</h1>
      <ConcordGroupHeaderActions :show-count="false" @save="save" />
    </header>

    <main class="concord-agreement-create">
      <label class="concord-agreement-create__field">
        <span class="concord-agreement-create__label">Название проекта</span>
        <input
          v-model="form.title"
          class="concord-agreement-create__input"
          type="text"
          placeholder="Введите название проекта"
        >
      </label>

      <label class="concord-agreement-create__field">
        <span class="concord-agreement-create__label">Описание проекта</span>
        <textarea
          v-model="form.description"
          class="concord-agreement-create__input concord-agreement-create__textarea"
          rows="4"
          placeholder="Введите текст"
        />
      </label>

      <div class="concord-agreement-create__field">
        <div class="concord-agreement-create__dates-head">
          <span class="concord-agreement-create__label">Сроки согласования</span>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
            <rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.6"/>
            <path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="concord-agreement-create__dates">
          <label class="concord-agreement-create__date-field">
            <span class="concord-agreement-create__date-label">Дата начала</span>
            <input
              v-model="form.startDate"
              class="concord-agreement-create__input"
              type="date"
              :max="form.endDate || undefined"
            >
          </label>
          <label class="concord-agreement-create__date-field">
            <span class="concord-agreement-create__date-label">Дата окончания</span>
            <input
              v-model="form.endDate"
              class="concord-agreement-create__input"
              type="date"
              :min="form.startDate || undefined"
            >
          </label>
        </div>
        <p v-if="hasInvalidDateRange" class="concord-agreement-create__hint concord-agreement-create__hint--error">
          Дата окончания не может быть раньше даты начала
        </p>
      </div>

      <section class="concord-section-settings__row concord-agreement-create__importance-row">
        <span class="concord-section-settings__row-label">Важность</span>
        <label class="concord-section-settings__row-toggle">
          <span>{{ form.isImportant ? 'Да' : 'Нет' }}</span>
          <input v-model="form.isImportant" type="checkbox" class="concord-toggle">
        </label>
      </section>
    </main>

    <footer class="concord-section-settings__footer">
      <button type="button" class="concord-section-settings__done" :disabled="hasInvalidDateRange" @click="save">
        Готово
      </button>
    </footer>
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import {
  formatAgreementDaysLabel,
  formatIsoDateToRu,
  formatRuDateToIso,
} from '../concord/mock-agreements.js'

function createFormFromAgreement(agreement) {
  return {
    title: agreement?.title || '',
    description: agreement?.description || '',
    startDate: formatRuDateToIso(agreement?.startDate || agreement?.createdAt || ''),
    endDate: formatRuDateToIso(agreement?.deadline || ''),
    isImportant: Boolean(agreement?.isUrgent),
  }
}

export default {
  name: 'AgreementSettingsView',
  components: { ConcordGroupHeaderActions },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
  },
  emits: ['back', 'save'],
  setup(props, { emit }) {
    const form = ref(createFormFromAgreement(props.agreement))

    const hasInvalidDateRange = computed(() => {
      const { startDate, endDate } = form.value
      if (!startDate || !endDate) {
        return false
      }
      return endDate < startDate
    })

    watch(
      () => props.agreement,
      (agreement) => {
        if (!agreement) {
          return
        }
        form.value = createFormFromAgreement(agreement)
      },
      { immediate: true }
    )

    watch(
      () => form.value.startDate,
      (startDate) => {
        if (!startDate || !form.value.endDate) {
          return
        }
        if (form.value.endDate < startDate) {
          form.value.endDate = startDate
        }
      }
    )

    watch(
      () => form.value.endDate,
      (endDate) => {
        const { startDate } = form.value
        if (!startDate || !endDate) {
          return
        }
        if (endDate < startDate) {
          form.value.endDate = startDate
        }
      }
    )

    function save() {
      const title = form.value.title.trim()
      if (!title || hasInvalidDateRange.value) {
        return
      }

      const startDate = formatIsoDateToRu(form.value.startDate)
      const deadline = formatIsoDateToRu(form.value.endDate)

      emit('save', {
        title,
        description: form.value.description.trim(),
        startDate,
        deadline,
        isImportant: form.value.isImportant,
        daysLabel: startDate && deadline ? formatAgreementDaysLabel(startDate, deadline) : '—',
      })
      emit('back')
    }

    return { form, hasInvalidDateRange, save }
  },
}
</script>
