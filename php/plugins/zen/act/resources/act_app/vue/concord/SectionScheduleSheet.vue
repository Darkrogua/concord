<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="closeSheet" />
    <section class="concord-sheet concord-section-schedule-sheet" role="dialog" aria-modal="true" aria-labelledby="section-schedule-title">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 id="section-schedule-title" class="concord-sheet__title">Сроки раздела</h2>
      <p v-if="periodHint" class="concord-section-schedule-sheet__hint">{{ periodHint }}</p>

      <div class="concord-section-schedule-sheet__fields">
        <label class="concord-section-schedule-sheet__field">
          <span>Начало</span>
          <div>
            <input v-model="form.startDate" type="date" :min="startDateMin" :max="startDateMax">
            <input v-model="form.startTime" type="time" :min="startTimeMin" :max="startTimeMax">
          </div>
        </label>
        <label class="concord-section-schedule-sheet__field">
          <span>Окончание</span>
          <div>
            <input v-model="form.endDate" type="date" :min="endDateMin" :max="endDateMax">
            <input v-model="form.endTime" type="time" :min="endTimeMin" :max="endTimeMax">
          </div>
        </label>
      </div>

      <p v-if="hasInvalidRange" class="concord-section-schedule-sheet__error">
        Период раздела должен находиться в рамках периода согласования.
      </p>

      <div class="concord-create-sheet__actions">
        <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--save" @click="closeSheet">
          Готово
        </button>
      </div>
    </section>
  </template>
</template>

<script>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  combineRuDateTime,
  formatIsoDateToRu,
  formatRuDateTimeToFormParts,
} from './mock-agreements.js'
import { sectionScheduleHasInvalidRange } from './agreement-form-utils.js'

const AUTOSAVE_MS = 350

function toRuDateTime(date, time) {
  return date ? combineRuDateTime(formatIsoDateToRu(date), time) : ''
}

export default {
  name: 'SectionScheduleSheet',
  props: {
    open: { type: Boolean, default: false },
    agreement: { type: Object, required: true },
    section: { type: Object, required: true },
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const form = ref({})
    let syncingFromSection = false
    let autosaveTimer = null

    function resetForm() {
      syncingFromSection = true
      const start = formatRuDateTimeToFormParts(props.section?.startDate || '')
      const end = formatRuDateTimeToFormParts(props.section?.deadline || '')
      form.value = {
        startDate: start.date,
        startTime: start.time,
        endDate: end.date,
        endTime: end.time,
      }
      syncingFromSection = false
    }

    watch(() => [props.open, props.section?.id, props.section?.startDate, props.section?.deadline], () => {
      if (!props.open) {
        return
      }
      resetForm()
    }, { immediate: true })

    const agreementPeriod = computed(() => ({
      start: formatRuDateTimeToFormParts(props.agreement?.startDate || ''),
      end: formatRuDateTimeToFormParts(props.agreement?.deadline || ''),
    }))

    const periodHint = computed(() => {
      if (!agreementPeriod.value.start.date && !agreementPeriod.value.end.date) {
        return ''
      }
      return `В рамках согласования: ${props.agreement?.startDate || 'не указано'} — ${props.agreement?.deadline || 'не указано'}`
    })

    const startDateMin = computed(() => agreementPeriod.value.start.date || '')
    const startDateMax = computed(() => agreementPeriod.value.end.date || '')
    const endDateMin = computed(() => agreementPeriod.value.start.date || '')
    const endDateMax = computed(() => agreementPeriod.value.end.date || '')
    const startTimeMin = computed(() => form.value.startDate === agreementPeriod.value.start.date ? agreementPeriod.value.start.time : '')
    const startTimeMax = computed(() => form.value.startDate === agreementPeriod.value.end.date ? agreementPeriod.value.end.time : '')
    const endTimeMin = computed(() => form.value.endDate === agreementPeriod.value.start.date ? agreementPeriod.value.start.time : '')
    const endTimeMax = computed(() => form.value.endDate === agreementPeriod.value.end.date ? agreementPeriod.value.end.time : '')

    const hasInvalidRange = computed(() =>
      sectionScheduleHasInvalidRange(form.value, agreementPeriod.value)
    )

    function buildPayload() {
      return {
        startDate: toRuDateTime(form.value.startDate, form.value.startTime),
        deadline: toRuDateTime(form.value.endDate, form.value.endTime),
      }
    }

    function applyChanges() {
      if (hasInvalidRange.value) {
        return false
      }
      emit('save', buildPayload())
      return true
    }

    function scheduleAutosave() {
      if (syncingFromSection || !props.open) {
        return
      }
      clearTimeout(autosaveTimer)
      autosaveTimer = setTimeout(() => {
        autosaveTimer = null
        applyChanges()
      }, AUTOSAVE_MS)
    }

    watch(form, scheduleAutosave, { deep: true })

    function closeSheet() {
      clearTimeout(autosaveTimer)
      autosaveTimer = null
      applyChanges()
      emit('close')
    }

    onBeforeUnmount(() => {
      clearTimeout(autosaveTimer)
    })

    return {
      form,
      periodHint,
      startDateMin,
      startDateMax,
      endDateMin,
      endDateMax,
      startTimeMin,
      startTimeMax,
      endTimeMin,
      endTimeMax,
      hasInvalidRange,
      closeSheet,
    }
  },
}
</script>
