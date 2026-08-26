<template>
  <div class="concord-page concord-page--agreement-form concord-page--agreement-settings">
    <ConcordPageHeader title="Настройки согласования" show-back @back="save">
      <template #right>
        <ConcordGroupHeaderActions
          :show-count="false"
          :disabled="hasBlockingDateError"
          @save="save"
        />
      </template>
    </ConcordPageHeader>

    <main class="concord-notifications">
      <AgreementFormFields :form="form" :allow-past-dates="isDraft" />

      <button
        v-if="canDelete"
        type="button"
        class="concord-section-settings__delete concord-agreement-settings__delete"
        @click="deleteConfirmOpen = true"
      >
        Удалить согласование
      </button>
    </main>

    <footer class="concord-section-settings__footer">
      <button
        type="button"
        class="concord-section-settings__done"
        :disabled="hasBlockingDateError"
        @click="save"
      >
        Готово
      </button>
    </footer>

    <ConcordConfirmSheet
      :open="deleteConfirmOpen"
      title="Удалить согласование?"
      :message="deleteConfirmMessage"
      confirm-label="Удалить"
      cancel-label="Отмена"
      @confirm="confirmDelete"
      @cancel="deleteConfirmOpen = false"
      @dismiss="deleteConfirmOpen = false"
    />
  </div>
</template>

<script>
import { computed, onBeforeUnmount, ref, watch, defineExpose } from 'vue'
import AgreementFormFields from '../concord/AgreementFormFields.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import {
  agreementFormHasBlockingDateError,
  agreementFormToPayload,
  createAgreementFormFromAgreement,
  normalizeAgreementFormRange,
} from '../concord/agreement-form-utils.js'
import { isLaunchedAgreement } from '../concord/mock-agreements.js'

const AUTOSAVE_MS = 350

export default {
  name: 'AgreementSettingsView',
  components: {
    AgreementFormFields,
    ConcordConfirmSheet,
    ConcordGroupHeaderActions,
    ConcordPageHeader,
  },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
  },
  emits: ['back', 'save', 'delete'],
  setup(props, { emit }) {
    const form = ref(createAgreementFormFromAgreement(props.agreement))
    const deleteConfirmOpen = ref(false)
    let syncingFromAgreement = false
    let autosaveTimer = null

    const isDraft = computed(() => !isLaunchedAgreement(props.agreement))
    const hasBlockingDateError = computed(() =>
      agreementFormHasBlockingDateError(form.value, { allowPastDates: isDraft.value })
    )

    const canDelete = computed(() =>
      props.agreement?.status === 'draft' || !props.agreement?.createdAt
    )

    const deleteConfirmMessage = computed(() => {
      const title = props.agreement?.title?.trim() || 'Согласование'
      return `«${title}» и все разделы будут удалены без возможности восстановления.`
    })

    watch(
      () => props.agreement,
      (agreement) => {
        if (!agreement) {
          return
        }
        syncingFromAgreement = true
        form.value = createAgreementFormFromAgreement(agreement)
        syncingFromAgreement = false
      },
      { immediate: true }
    )

    watch(
      () => [form.value.startDate, form.value.startTime],
      () => {
        form.value = normalizeAgreementFormRange(form.value)
      }
    )

    watch(
      () => [form.value.endDate, form.value.endTime],
      () => {
        form.value = normalizeAgreementFormRange(form.value)
      }
    )

    function applyChanges() {
      const payload = agreementFormToPayload(form.value)
      if (!payload.title || hasBlockingDateError.value) {
        return false
      }
      emit('save', payload)
      return true
    }

    function scheduleAutosave() {
      if (syncingFromAgreement) {
        return
      }
      clearTimeout(autosaveTimer)
      autosaveTimer = setTimeout(() => {
        autosaveTimer = null
        applyChanges()
      }, AUTOSAVE_MS)
    }

    watch(form, scheduleAutosave, { deep: true })

    onBeforeUnmount(() => {
      clearTimeout(autosaveTimer)
      applyChanges()
    })

    function hasUnsavedChanges() {
      const payload = agreementFormToPayload(form.value)
      const baseline = agreementFormToPayload(createAgreementFormFromAgreement(props.agreement))
      return JSON.stringify(payload) !== JSON.stringify(baseline)
    }

    function save() {
      clearTimeout(autosaveTimer)
      autosaveTimer = null
      if (!applyChanges()) {
        return
      }
      emit('back')
    }

    function confirmDelete() {
      deleteConfirmOpen.value = false
      emit('delete')
    }

    defineExpose({ hasUnsavedChanges, save })

    return {
      form,
      isDraft,
      hasBlockingDateError,
      canDelete,
      deleteConfirmOpen,
      deleteConfirmMessage,
      save,
      confirmDelete,
    }
  },
}
</script>
