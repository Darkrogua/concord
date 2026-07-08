const FALLBACK_TIMEZONES = [
  'UTC',
  'Europe/Kaliningrad',
  'Europe/Moscow',
  'Europe/Samara',
  'Asia/Yekaterinburg',
  'Asia/Omsk',
  'Asia/Krasnoyarsk',
  'Asia/Irkutsk',
  'Asia/Yakutsk',
  'Asia/Vladivostok',
  'Asia/Magadan',
  'Asia/Kamchatka',
  'Europe/London',
  'Europe/Berlin',
  'Europe/Istanbul',
  'Asia/Dubai',
  'Asia/Tashkent',
  'Asia/Almaty',
  'Asia/Bangkok',
  'Asia/Shanghai',
  'Asia/Tokyo',
  'Australia/Sydney',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
]

const offsetFormatterCache = new Map()

export function getBrowserTimezone() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
  } catch {
    return 'UTC'
  }
}

export function resolveTimezone(userTimezone) {
  const saved = typeof userTimezone === 'string' ? userTimezone.trim() : ''
  return saved || getBrowserTimezone()
}

function getOffsetMinutes(timeZone, date = new Date()) {
  try {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone,
      timeZoneName: 'shortOffset',
    }).formatToParts(date)

    const value = parts.find((part) => part.type === 'timeZoneName')?.value || 'GMT'
    const match = value.match(/GMT([+-])(\d{1,2})(?::(\d{2}))?/)
    if (!match) {
      return 0
    }

    const sign = match[1] === '-' ? -1 : 1
    const hours = Number(match[2] || 0)
    const minutes = Number(match[3] || 0)

    return sign * (hours * 60 + minutes)
  } catch {
    return 0
  }
}

function formatOffsetLabel(timeZone) {
  if (offsetFormatterCache.has(timeZone)) {
    return offsetFormatterCache.get(timeZone)
  }

  const offsetMinutes = getOffsetMinutes(timeZone)
  const sign = offsetMinutes >= 0 ? '+' : '-'
  const absolute = Math.abs(offsetMinutes)
  const hours = String(Math.floor(absolute / 60)).padStart(2, '0')
  const minutes = String(absolute % 60).padStart(2, '0')
  const label = `UTC${sign}${hours}:${minutes}`
  offsetFormatterCache.set(timeZone, label)

  return label
}

export function formatTimezoneLabel(timeZone) {
  const resolved = resolveTimezone(timeZone)
  return `${resolved.replace(/_/g, ' ')} (${formatOffsetLabel(resolved)})`
}

export function formatTimezoneSetting(timezone) {
  if (timezone?.trim()) {
    return formatTimezoneLabel(timezone)
  }

  return `Авто (${formatTimezoneLabel(null)})`
}

export function listTimezoneOptions() {
  const zones = typeof Intl.supportedValuesOf === 'function'
    ? Intl.supportedValuesOf('timeZone')
    : FALLBACK_TIMEZONES

  const unique = new Set(zones)
  const browser = getBrowserTimezone()
  unique.add(browser)

  return [...unique]
    .map((value) => ({
      value,
      label: `${value.replace(/_/g, ' ')} (${formatOffsetLabel(value)})`,
      offset: getOffsetMinutes(value),
    }))
    .sort((a, b) => a.offset - b.offset || a.value.localeCompare(b.value, 'ru'))
}

function parseUtcCompactTimestamp(timestamp) {
  const raw = String(timestamp || '')
  if (!/^\d{14}$/.test(raw)) {
    return null
  }

  const iso = `${raw.slice(0, 4)}-${raw.slice(4, 6)}-${raw.slice(6, 8)}T${raw.slice(8, 10)}:${raw.slice(10, 12)}:${raw.slice(12, 14)}Z`

  const date = new Date(iso)
  return Number.isNaN(date.getTime()) ? null : date
}

export function formatUtcCompactTimestamp(timestamp, userTimezone) {
  const raw = String(timestamp || '')
  const date = parseUtcCompactTimestamp(raw)
  if (!date) {
    return raw
  }

  const timeZone = resolveTimezone(userTimezone)

  try {
    return new Intl.DateTimeFormat('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      timeZone,
      timeZoneName: 'short',
    }).format(date)
  } catch {
    const year = raw.slice(0, 4)
    const month = raw.slice(4, 6)
    const day = raw.slice(6, 8)
    const hour = raw.slice(8, 10)
    const minute = raw.slice(10, 12)

    return `${day}.${month}.${year}, ${hour}:${minute} UTC`
  }
}
