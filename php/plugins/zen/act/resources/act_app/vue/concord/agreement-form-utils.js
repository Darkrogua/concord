import {
  combineRuDateTime,
  formatAgreementDaysLabel,
  formatIsoDateToRu,
  formatRuDateTimeToFormParts,
  parseRuDateTime,
} from './mock-agreements.js'

export const EMPTY_AGREEMENT_FORM = {
  title: '',
  description: '',
  startDate: '',
  startTime: '',
  endDate: '',
  endTime: '',
  isImportant: false,
}

export function createAgreementFormFromAgreement(agreement) {
  const start = formatRuDateTimeToFormParts(agreement?.startDate || agreement?.createdAt || '')
  const end = formatRuDateTimeToFormParts(agreement?.deadline || '')

  return {
    title: agreement?.title || '',
    description: agreement?.description || '',
    startDate: start.date,
    startTime: start.time,
    endDate: end.date,
    endTime: end.time,
    isImportant: Boolean(agreement?.isUrgent),
  }
}

export function agreementFormHasDates(form) {
  return Boolean(form.startDate && form.endDate)
}

export function agreementFormHasInvalidRange(form) {
  if (!form.startDate || !form.endDate) {
    return false
  }

  const start = parseRuDateTime(
    combineRuDateTime(formatIsoDateToRu(form.startDate), form.startTime || '00:00')
  )
  const end = parseRuDateTime(
    combineRuDateTime(formatIsoDateToRu(form.endDate), form.endTime || '23:59')
  )

  if (!start || !end) {
    return false
  }

  return end.getTime() < start.getTime()
}

export function normalizeAgreementFormRange(form) {
  if (!agreementFormHasInvalidRange(form)) {
    return form
  }

  return {
    ...form,
    endDate: form.startDate,
    endTime: form.startTime || form.endTime,
  }
}

export function agreementFormToPayload(form) {
  const title = form.title.trim()
  const startDate = combineRuDateTime(formatIsoDateToRu(form.startDate), form.startTime)
  const deadline = combineRuDateTime(formatIsoDateToRu(form.endDate), form.endTime)

  return {
    title,
    description: form.description.trim(),
    startDate,
    deadline,
    isImportant: form.isImportant,
    daysLabel: startDate && deadline ? formatAgreementDaysLabel(startDate, deadline) : '—',
  }
}
