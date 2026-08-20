<template>
  <div class="concord-agreement-form">
    <section class="concord-agreement-form__section">
      <h2 class="concord-agreement-form__section-title">Основное</h2>
      <div class="concord-agreement-form__section-body">
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
    </section>

    <section class="concord-agreement-form__section">
      <h2 class="concord-agreement-form__section-title">Сроки согласования</h2>
      <div class="concord-agreement-form__section-body">
        <div class="concord-agreement-form__period">
          <span class="concord-agreement-form__period-label">Начало</span>
          <div class="concord-agreement-form__datetime">
            <input
              v-model="form.startDate"
              class="concord-agreement-form__input"
              type="date"
              :min="today"
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
          <span class="concord-agreement-form__period-label">Окончание</span>
          <div class="concord-agreement-form__datetime">
            <input
              v-model="form.endDate"
              class="concord-agreement-form__input"
              type="date"
              :min="endDateMin"
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
      </div>
    </section>

    <section class="concord-section-settings__row concord-agreement-form__urgency-row">
      <span class="concord-section-settings__row-label">Срочность</span>
      <label class="concord-section-settings__row-toggle">
        <span>{{ form.isImportant ? 'Да' : 'Нет' }}</span>
        <input v-model="form.isImportant" type="checkbox" class="concord-toggle">
      </label>
    </section>
  </div>
</template>

<script>
import { computed } from 'vue'
import {
  getAgreementFormDateError,
  getTodayIsoDate,
} from './agreement-form-utils.js'

export default {
  name: 'AgreementFormFields',
  props: {
    form: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    const today = getTodayIsoDate()
    const dateError = computed(() => getAgreementFormDateError(props.form, today))
    const startDateMax = computed(() => (
      props.form.endDate >= today ? props.form.endDate : undefined
    ))
    const endDateMin = computed(() => (
      props.form.startDate > today ? props.form.startDate : today
    ))

    return {
      today,
      dateError,
      startDateMax,
      endDateMin,
    }
  },
}
</script>
