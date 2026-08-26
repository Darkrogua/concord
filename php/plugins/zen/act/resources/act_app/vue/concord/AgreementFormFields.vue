<template>
  <div class="concord-agreement-form">
    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Основное</h2>
      <div class="concord-profile-card">
        <div class="concord-agreement-form__card-body">
          <label class="concord-agreement-form__field">
            <span class="concord-agreement-form__label">Название</span>
            <input
              v-model="form.title"
              class="concord-agreement-form__input"
              type="text"
              placeholder="Введите название согласования"
            >
          </label>

          <label class="concord-agreement-form__field">
            <span class="concord-agreement-form__label">Описание</span>
            <textarea
              v-model="form.description"
              class="concord-agreement-form__input concord-agreement-form__textarea"
              rows="4"
              placeholder="Кратко опишите, что нужно согласовать"
            />
          </label>
        </div>
      </div>
    </section>

    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Сроки согласования</h2>
      <div class="concord-profile-card">
        <div class="concord-agreement-form__card-body">
          <div class="concord-agreement-form__period">
            <span class="concord-agreement-form__label">Начало</span>
            <div class="concord-agreement-form__datetime">
              <input
                v-model="form.startDate"
                class="concord-agreement-form__input"
                type="date"
                :min="enforceMinDate ? today : undefined"
                :max="startDateMax"
              >
              <input
                v-model="form.startTime"
                class="concord-agreement-form__input concord-agreement-form__time"
                type="time"
              >
            </div>
          </div>

          <div class="concord-agreement-form__period">
            <span class="concord-agreement-form__label">Окончание</span>
            <div class="concord-agreement-form__datetime">
              <input
                v-model="form.endDate"
                class="concord-agreement-form__input"
                type="date"
                :min="enforceMinDate ? endDateMin : undefined"
              >
              <input
                v-model="form.endTime"
                class="concord-agreement-form__input concord-agreement-form__time"
                type="time"
              >
            </div>
          </div>

          <p v-if="dateError" class="concord-agreement-form__hint concord-agreement-form__hint--error">
            {{ dateError }}
          </p>
          <p v-else-if="dateWarning" class="concord-agreement-form__hint concord-agreement-form__hint--warning">
            {{ dateWarning }}
          </p>
        </div>
      </div>
    </section>

    <section class="concord-settings-group">
      <div class="concord-profile-card">
        <label class="concord-notifications__toggle-row concord-agreement-form__urgency-row">
          <span class="concord-agreement-form__urgency-label">Срочность</span>
          <span class="concord-agreement-form__toggle-trail">
            <span>{{ form.isImportant ? 'Да' : 'Нет' }}</span>
            <input v-model="form.isImportant" type="checkbox" class="concord-toggle">
          </span>
        </label>
      </div>
    </section>
  </div>
</template>

<script>
import { computed } from 'vue'
import {
  getAgreementFormPastDateError,
  getAgreementFormStructuralDateError,
  getTodayIsoDate,
} from './agreement-form-utils.js'

export default {
  name: 'AgreementFormFields',
  props: {
    form: {
      type: Object,
      required: true,
    },
    allowPastDates: {
      type: Boolean,
      default: false,
    },
  },
  setup(props) {
    const today = getTodayIsoDate()
    const enforceMinDate = computed(() => !props.allowPastDates)
    const dateError = computed(() => {
      const structuralError = getAgreementFormStructuralDateError(props.form)
      if (structuralError) {
        return structuralError
      }
      if (!props.allowPastDates) {
        return getAgreementFormPastDateError(props.form, today)
      }
      return ''
    })
    const dateWarning = computed(() => {
      if (!props.allowPastDates) {
        return ''
      }
      return getAgreementFormPastDateError(props.form, today)
    })
    const startDateMax = computed(() => (
      props.form.endDate >= today ? props.form.endDate : undefined
    ))
    const endDateMin = computed(() => (
      props.form.startDate > today ? props.form.startDate : today
    ))

    return {
      today,
      enforceMinDate,
      dateError,
      dateWarning,
      startDateMax,
      endDateMin,
    }
  },
}
</script>
