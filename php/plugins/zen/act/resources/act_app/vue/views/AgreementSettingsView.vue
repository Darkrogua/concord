<template>
  <div class="concord-page concord-page--editor concord-page--agreement-settings">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="save">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Настройки согласования</h1>
      <ConcordGroupHeaderActions :show-count="false" @save="save" />
    </header>

    <main class="concord-agreement-create">
      <label class="concord-agreement-create__field">
        <span class="concord-agreement-create__label">Название проекта</span>
        <input
          v-model="form.title"
          class="concord-agreement-create__input"
          type="text"
          placeholder="Введите название проекта"
        >
      </label>

      <label class="concord-agreement-create__field">
        <span class="concord-agreement-create__label">Описание проекта</span>
        <textarea
          v-model="form.description"
          class="concord-agreement-create__input concord-agreement-create__textarea"
          rows="4"
          placeholder="Введите текст"
        />
      </label>
    </main>

    <footer class="concord-section-settings__footer">
      <button type="button" class="concord-section-settings__done" @click="save">
        Готово
      </button>
    </footer>
  </div>
</template>

<script>
import { ref, watch } from 'vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'

export default {
  name: 'AgreementSettingsView',
  components: { ConcordGroupHeaderActions },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
  },
  emits: ['back', 'save'],
  setup(props, { emit }) {
    const form = ref({
      title: '',
      description: '',
    })

    watch(
      () => props.agreement,
      (agreement) => {
        if (!agreement) {
          return
        }
        form.value = {
          title: agreement.title || '',
          description: agreement.description || '',
        }
      },
      { immediate: true }
    )

    function save() {
      const title = form.value.title.trim()
      if (!title) {
        return
      }
      emit('save', {
        title,
        description: form.value.description.trim(),
      })
      emit('back')
    }

    return { form, save }
  },
}
</script>
