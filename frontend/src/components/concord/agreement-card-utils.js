export function getPersonInitials(name = '') {
  const parts = String(name).trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase()
}

export function pluralizeDays(count) {
  const numeric = Number(count)
  if (Number.isNaN(numeric)) return '0 дней'
  const sign = numeric < 0 ? '-' : ''
  const value = Math.abs(numeric)
  const mod10 = value % 10
  const mod100 = value % 100
  if (mod10 === 1 && mod100 !== 11) return `${sign}${value} день`
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return `${sign}${value} дня`
  return `${sign}${value} дней`
}

export function pluralizeParticipants(count) {
  const value = Math.abs(Number(count) || 0)
  const mod10 = value % 10
  const mod100 = value % 100
  if (mod10 === 1 && mod100 !== 11) return `${value} участник`
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return `${value} участника`
  return `${value} участников`
}

export function getAgreementDaysRemaining(deadlineValue, today = new Date()) {
  if (!deadlineValue) return null
  const end = new Date(deadlineValue)
  if (Number.isNaN(end.getTime())) return null
  const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  const endStart = new Date(end.getFullYear(), end.getMonth(), end.getDate())
  const msPerDay = 24 * 60 * 60 * 1000
  return Math.round((endStart.getTime() - todayStart.getTime()) / msPerDay)
}

export function getAgreementMetrics(agreement) {
  let total = 0
  let voted = 0
  for (const section of agreement?.sections || []) {
    const sectionTotal = section.progress?.total || 0
    const sectionVoted = section.progress?.yes || 0
    total += sectionTotal
    voted += sectionVoted
  }
  return { total, voted: Math.min(total, voted) }
}

/** Map Laravel API agreement to card view-model. */
export function normalizeAgreement(item) {
  const metrics = getAgreementMetrics(item)
  const deadline = item.deadline
    ? new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(item.deadline))
    : ''

  return {
    id: item.id,
    number: item.id,
    title: item.title,
    status: item.status,
    deadline,
    deadlineRaw: item.deadline,
    author: item.author || { name: '—' },
    sections: item.sections || [],
    isOwner: Boolean(item.is_author),
    isFavorite: Boolean(item.is_favorite),
    isUrgent: false,
    urgentAcknowledged: true,
    createdAt: item.created_at || true,
    total: metrics.total,
    voted: metrics.voted,
    mySectionApproved: item.status === 'completed',
    mySectionRejected: false,
  }
}
