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
  const start = formatRuDateTimeToFormParts(agreement?.startDate || '')
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

export function getTodayIsoDate(now = new Date()) {
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function getAgreementFormStructuralDateError(form) {
  if (!form.startDate || !form.endDate) {
    return ''
  }

  const start = parseRuDateTime(
    combineRuDateTime(formatIsoDateToRu(form.startDate), form.startTime || '00:00')
  )
  const end = parseRuDateTime(
    combineRuDateTime(formatIsoDateToRu(form.endDate), form.endTime || '23:59')
  )

  if (!start || !end) {
    return ''
  }

  return end.getTime() < start.getTime()
    ? 'Дата и время окончания не могут быть раньше начала'
    : ''
}

export function getAgreementFormPastDateError(form, today = getTodayIsoDate()) {
  if (form.startDate && form.startDate < today) {
    return 'Дата начала не может быть раньше сегодняшнего дня'
  }

  if (form.endDate && form.endDate < today) {
    return 'Дата окончания не может быть раньше сегодняшнего дня'
  }

  return ''
}

export function getAgreementFormDateError(form, options = {}) {
  const {
    today = getTodayIsoDate(),
    allowPastDates = false,
  } = options

  if (!allowPastDates) {
    const pastDateError = getAgreementFormPastDateError(form, today)
    if (pastDateError) {
      return pastDateError
    }
  }

  return getAgreementFormStructuralDateError(form)
}

export function agreementFormHasInvalidRange(form, options = {}) {
  return Boolean(getAgreementFormDateError(form, options))
}

export function agreementFormHasBlockingDateError(form, options = {}) {
  if (getAgreementFormStructuralDateError(form)) {
    return true
  }

  if (options.allowPastDates) {
    return false
  }

  return Boolean(getAgreementFormPastDateError(form, options.today))
}

function parseSectionScheduleDateTime(isoDate, time, fallbackTime) {
  if (!isoDate) {
    return null
  }
  return parseRuDateTime(
    combineRuDateTime(formatIsoDateToRu(isoDate), time || fallbackTime)
  )
}

export function sectionScheduleHasInvalidRange(form, agreementPeriod) {
  const { startDate, startTime, endDate, endTime } = form
  if (!startDate && !endDate) {
    return false
  }

  const agreementStart = parseSectionScheduleDateTime(
    agreementPeriod?.start?.date,
    agreementPeriod?.start?.time,
    '00:00'
  )
  const agreementEnd = parseSectionScheduleDateTime(
    agreementPeriod?.end?.date,
    agreementPeriod?.end?.time,
    '23:59'
  )
  const sectionStart = parseSectionScheduleDateTime(startDate, startTime, '00:00')
  const sectionEnd = parseSectionScheduleDateTime(endDate, endTime, '23:59')

  if (sectionStart && !sectionEnd) {
    return Boolean(
      (agreementStart && sectionStart < agreementStart)
      || (agreementEnd && sectionStart > agreementEnd)
    )
  }

  if (!sectionStart && sectionEnd) {
    return Boolean(
      (agreementStart && sectionEnd < agreementStart)
      || (agreementEnd && sectionEnd > agreementEnd)
    )
  }

  return Boolean(
    (sectionStart && sectionEnd && sectionEnd < sectionStart)
    || (sectionStart && agreementStart && sectionStart < agreementStart)
    || (sectionEnd && agreementEnd && sectionEnd > agreementEnd)
  )
}

export function normalizeAgreementFormRange(form) {
  if (!getAgreementFormStructuralDateError(form)) {
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
