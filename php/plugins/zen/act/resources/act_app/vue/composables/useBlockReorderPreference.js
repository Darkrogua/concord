import { ref } from 'vue'

const STORAGE_KEY = 'act_block_reorder_enabled'

function readEnabled() {
  if (typeof localStorage === 'undefined') {
    return false
  }

  const raw = localStorage.getItem(STORAGE_KEY)
  return raw === '1' || raw === 'true'
}

function writeEnabled(value) {
  if (typeof localStorage === 'undefined') {
    return
  }

  localStorage.setItem(STORAGE_KEY, value ? '1' : '0')
}

export function useBlockReorderPreference() {
  const reorderEnabled = ref(readEnabled())

  const toggleReorder = () => {
    reorderEnabled.value = !reorderEnabled.value
    writeEnabled(reorderEnabled.value)
  }

  return {
    reorderEnabled,
    toggleReorder,
  }
}
