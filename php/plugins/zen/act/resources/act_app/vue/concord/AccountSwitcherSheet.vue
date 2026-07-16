<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-sheet concord-account-sheet" role="dialog" aria-label="Сменить аккаунт">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <div class="concord-account-sheet__header">
        <h2 class="concord-sheet__title">Сменить аккаунт</h2>
        <button type="button" class="concord-account-sheet__cancel" @click="$emit('close')">
          Отмена
        </button>
      </div>

      <ul class="concord-account-sheet__list" role="radiogroup" aria-label="Аккаунты">
        <li v-for="account in accounts" :key="account.id">
          <button
            type="button"
            class="concord-account-sheet__item"
            role="radio"
            :aria-checked="account.id === activeId"
            @click="selectAccount(account.id)"
          >
            <span class="concord-account-sheet__avatar" aria-hidden="true">
              <img v-if="account.avatarUrl" :src="account.avatarUrl" alt="">
              <span v-else>{{ account.initial }}</span>
            </span>
            <span class="concord-account-sheet__name">{{ account.name }}</span>
            <span
              class="concord-sheet__radio"
              :class="{ 'concord-sheet__radio--active': account.id === activeId }"
              aria-hidden="true"
            >
              <span v-if="account.id === activeId" class="concord-sheet__radio-dot" />
            </span>
          </button>
        </li>

        <li>
          <button
            type="button"
            class="concord-account-sheet__item concord-account-sheet__item--create"
            @click="onCreateAccount"
          >
            <span class="concord-account-sheet__avatar concord-account-sheet__avatar--create" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 6v12M6 12h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </span>
            <span class="concord-account-sheet__name concord-account-sheet__name--create">
              Создать новый аккаунт
            </span>
          </button>
        </li>
      </ul>
    </div>
  </template>
</template>

<script>
import { ref, watch } from 'vue'

const DEFAULT_ACCOUNTS = [
  { id: '1', name: 'Александр Аблизин', initial: 'А', avatarUrl: '' },
  { id: '2', name: 'McMraak', initial: 'А', avatarUrl: '' },
  { id: '3', name: 'Alex', initial: 'А', avatarUrl: '' },
]

export default {
  name: 'AccountSwitcherSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    modelValue: {
      type: String,
      default: '1',
    },
  },
  emits: ['close', 'update:modelValue', 'create-account'],
  setup(props, { emit }) {
    const accounts = DEFAULT_ACCOUNTS
    const activeId = ref(props.modelValue)

    watch(
      () => props.modelValue,
      (value) => {
        activeId.value = value
      }
    )

    function selectAccount(id) {
      activeId.value = id
      emit('update:modelValue', id)
      emit('close')
    }

    function onCreateAccount() {
      emit('create-account')
      emit('close')
    }

    return { accounts, activeId, selectAccount, onCreateAccount }
  },
}
</script>
