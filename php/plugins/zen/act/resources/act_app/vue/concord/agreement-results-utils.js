import {
  collectSectionVoterIds,
  resolveSectionParticipants,
  resolveSectionVotersCount,
} from './mock-groups.js'

function countVotesByDecision(votes = []) {
  let approved = 0
  let rejected = 0
  for (const vote of votes) {
    if (vote?.decision === 'rejected') {
      rejected += 1
    } else if (vote?.decision === 'approved') {
      approved += 1
    }
  }
  return { approved, rejected }
}

export function getSectionVoteCounts(section, groups = [], contacts = []) {
  const total = resolveSectionVotersCount(section, groups, contacts)
  const { approved, rejected } = countVotesByDecision(
    (section?.votes || []).filter((vote) => vote?.participantId)
  )
  const pending = Math.max(total - approved - rejected, 0)
  const toPercent = (count) => (total ? Math.round((count / total) * 100) : 0)

  return {
    total,
    approved,
    rejected,
    pending,
    approvedPercent: toPercent(approved),
    rejectedPercent: toPercent(rejected),
    pendingPercent: toPercent(pending),
  }
}

export function syncSectionVotingStatsFromVotes(section, groups = [], contacts = []) {
  const counts = getSectionVoteCounts(section, groups, contacts)
  const total = counts.total

  section.votingStats = {
    approved: counts.approvedPercent,
    rejected: counts.rejectedPercent,
    pending: counts.pendingPercent,
  }
  section.voted = counts.approved + counts.rejected
  if (total) {
    section.total = total
  }

  return section
}

export function isSectionFullyApproved(section, groups = [], contacts = []) {
  const total = resolveSectionVotersCount(section, groups, contacts)
  if (!total) {
    return false
  }
  const votes = section?.votes || []
  if (votes.length < total) {
    return false
  }
  return votes.every((vote) => vote?.decision === 'approved')
}

export function isAgreementVotingComplete(agreement, groups = [], contacts = []) {
  const sections = agreement?.sections || []
  if (!sections.length) {
    return false
  }
  return sections.every((section) => {
    const total = resolveSectionVotersCount(section, groups, contacts)
    if (!total) {
      return false
    }
    const votes = (section.votes || []).filter((vote) => vote?.participantId)
    return votes.length >= total
  })
}

export function isAgreementFullyApproved(agreement, groups = [], contacts = []) {
  const sections = agreement?.sections || []
  if (!sections.length) {
    return false
  }
  return isAgreementVotingComplete(agreement, groups, contacts)
    && sections.every((section) => isSectionFullyApproved(section, groups, contacts))
}

export function getAgreementVoteTotals(agreement, groups = [], contacts = []) {
  let approved = 0
  let rejected = 0
  let pending = 0
  let totalSeats = 0

  for (const section of agreement?.sections || []) {
    const sectionTotal = resolveSectionVotersCount(section, groups, contacts)
    const counts = countVotesByDecision(section.votes || [])
    totalSeats += sectionTotal
    approved += counts.approved
    rejected += counts.rejected
    pending += Math.max(sectionTotal - counts.approved - counts.rejected, 0)
  }

  return { approved, rejected, pending, total: totalSeats }
}

export function agreementHasRemarks(agreement, groups = [], contacts = []) {
  if (!isAgreementVotingComplete(agreement, groups, contacts)) {
    return false
  }
  return getAgreementVoteTotals(agreement, groups, contacts).rejected > 0
}

export function getAgreementResultsVerdict(agreement, groups = [], contacts = []) {
  if (agreement?.status === 'approved' || agreement?.status === 'completed') {
    return {
      tone: 'success',
      title: 'Согласовано',
      subtitle: agreement?.approvedAt
        ? `Завершено ${agreement.approvedAt}`
        : 'Все разделы согласованы единогласно',
    }
  }

  if (!isAgreementVotingComplete(agreement, groups, contacts)) {
    const totals = getAgreementVoteTotals(agreement, groups, contacts)
    return {
      tone: 'progress',
      title: 'Идёт голосование',
      subtitle: `Проголосовали ${totals.approved + totals.rejected} из ${totals.total}`,
    }
  }

  const totals = getAgreementVoteTotals(agreement, groups, contacts)
  if (totals.rejected > 0) {
    const blockedSections = (agreement?.sections || []).filter(
      (section) => !isSectionFullyApproved(section, groups, contacts)
    ).length
    return {
      tone: 'warning',
      title: 'Есть замечания',
      subtitle: blockedSections === 1
        ? `1 раздел не согласован · ${totals.rejected} отказа`
        : `${blockedSections} раздела не согласованы · ${totals.rejected} отказа`,
    }
  }

  return {
    tone: 'success',
    title: 'Согласовано',
    subtitle: 'Все участники проголосовали «Да»',
  }
}

export function getAgreementSectionResults(agreement, groups = [], contacts = []) {
  return (agreement?.sections || []).map((section, index) => {
    const participants = resolveSectionParticipants(section, groups, contacts)
    const voteCounts = getSectionVoteCounts(section, groups, contacts)
    const { total, approved, rejected, pending } = voteCounts
    const votesByParticipant = new Map(
      (section.votes || []).map((vote) => [vote.participantId, vote])
    )

    const rejections = (section.votes || [])
      .filter((vote) => vote.decision === 'rejected' && String(vote.reason || '').trim())
      .map((vote) => {
        const person = participants.find((item) => item.id === vote.participantId)
        return {
          participantId: vote.participantId,
          name: person?.name || person?.shortName || vote.participantId,
          initial: person?.initial || '?',
          reason: vote.reason,
          votedAt: vote.votedAt || '',
        }
      })
      .sort((a, b) => (a.votedAt || '').localeCompare(b.votedAt || ''))

    const roster = collectSectionVoterIds(section, groups).map((participantId) => {
      const person = participants.find((item) => item.id === participantId)
      const vote = votesByParticipant.get(participantId)
      return {
        id: participantId,
        name: person?.name || person?.shortName || participantId,
        initial: person?.initial || '?',
        decision: vote?.decision || 'pending',
        votedAt: vote?.votedAt || '',
      }
    })

    let status = 'pending'
    let statusLabel = 'Ждёт голосов'
    if (total > 0 && pending === 0) {
      if (rejected > 0) {
        status = 'rejected'
        statusLabel = 'Не согласован'
      } else {
        status = 'approved'
        statusLabel = 'Согласован'
      }
    } else if (approved + rejected > 0) {
      status = 'progress'
      statusLabel = `${approved + rejected} из ${total}`
    }

    return {
      id: section.id,
      index: index + 1,
      title: section.title || `Раздел ${index + 1}`,
      total,
      approved,
      rejected,
      pending,
      status,
      statusLabel,
      rejections,
      roster,
    }
  })
}

export function getAgreementResultsOverview(agreement, groups = [], contacts = []) {
  const sections = getAgreementSectionResults(agreement, groups, contacts)
  const totals = getAgreementVoteTotals(agreement, groups, contacts)
  const approvedSections = sections.filter((section) => section.status === 'approved').length
  const blockedSections = sections.filter((section) => section.status === 'rejected').length
  const inProgressSections = sections.filter(
    (section) => section.status === 'progress' || section.status === 'pending'
  ).length

  const allRejections = sections.flatMap((section) =>
    section.rejections.map((item) => ({
      ...item,
      sectionId: section.id,
      sectionTitle: section.title,
    }))
  )

  let sectionsSummary = ''
  if (!sections.length) {
    sectionsSummary = 'Разделы не настроены'
  } else if (approvedSections === sections.length) {
    sectionsSummary = `Все ${sections.length} раздела согласованы`
  } else if (blockedSections > 0) {
    sectionsSummary = `${approvedSections} из ${sections.length} разделов согласованы`
  } else if (inProgressSections > 0) {
    sectionsSummary = `Голосование идёт в ${inProgressSections} из ${sections.length} разделов`
  } else {
    sectionsSummary = `${approvedSections} из ${sections.length} разделов согласованы`
  }

  return {
    sections,
    totals,
    approvedSections,
    blockedSections,
    inProgressSections,
    totalSections: sections.length,
    sectionsSummary,
    allRejections,
  }
}
