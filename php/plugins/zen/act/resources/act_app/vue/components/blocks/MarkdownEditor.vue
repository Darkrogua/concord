<template>
  <div
    class="tg-markdown-editor"
    :class="{ 'tg-markdown-editor--mobile': isMobile }"
  >
    <div
      v-if="!isMobile"
      class="tg-markdown-toolbar"
      role="toolbar"
      aria-label="Форматирование"
    >
      <template v-if="!sourceMode">
        <MarkdownToolbarButtons
          variant="full"
          :disabled="disabled"
          :is-active="isActive"
          :run="run"
          :on-link="setLink"
        />
      </template>
      <button
        type="button"
        class="tg-markdown-toolbar__btn tg-markdown-toolbar__btn--source"
        :class="{ 'tg-markdown-toolbar__btn--active': sourceMode }"
        :disabled="disabled"
        :title="sourceMode ? 'Визуальный режим' : 'Исходный код'"
        @click="toggleSourceMode"
      >
        {{ sourceMode ? 'WYS' : '{}' }}
      </button>
    </div>

    <template v-else>
      <div class="tg-markdown-editor__chrome">
        <button
          v-if="!sourceMode"
          type="button"
          class="tg-markdown-toolbar__btn tg-markdown-toolbar__btn--menu"
          :class="{ 'tg-markdown-toolbar__btn--active': blockToolsOpen }"
          :disabled="disabled"
          title="Форматирование блока"
          :aria-expanded="blockToolsOpen"
          @click="blockToolsOpen = !blockToolsOpen"
        >
          Aa
        </button>
        <button
          type="button"
          class="tg-markdown-toolbar__btn tg-markdown-toolbar__btn--source"
          :class="{ 'tg-markdown-toolbar__btn--active': sourceMode }"
          :disabled="disabled"
          :title="sourceMode ? 'Визуальный режим' : 'Исходный код'"
          @click="toggleSourceMode"
        >
          {{ sourceMode ? 'WYS' : '{}' }}
        </button>
      </div>

      <div
        v-if="blockToolsOpen && !sourceMode"
        class="tg-markdown-toolbar tg-markdown-toolbar--block-sheet"
        role="toolbar"
        aria-label="Форматирование блока"
      >
        <MarkdownToolbarButtons
          variant="blocks"
          :disabled="disabled"
          :is-active="isActive"
          :run="run"
          :on-link="setLink"
        />
      </div>

      <BubbleMenu
        v-if="editor && !sourceMode"
        :editor="editor"
        :should-show="shouldShowSelectionBubble"
        :options="{ placement: 'top', offset: 8 }"
        class="tg-markdown-bubble"
      >
        <MarkdownToolbarButtons
          variant="marks"
          :disabled="disabled"
          :is-active="isActive"
          :run="run"
          :on-link="setLink"
        />
      </BubbleMenu>
    </template>

    <EditorContent
      v-show="!sourceMode"
      :editor="editor"
      class="tg-markdown-editor__surface"
    />

    <textarea
      v-show="sourceMode"
      v-model="sourceDraft"
      class="tg-block-textarea tg-markdown-editor__source"
      :disabled="disabled"
      rows="8"
      placeholder="Markdown…"
      spellcheck="false"
      @input="onSourceInput"
    />
  </div>
</template>

<script>
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { BubbleMenu } from '@tiptap/vue-3/menus'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { Markdown } from '@tiptap/markdown'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useAppEnvironment } from '../../app-environment'
import MarkdownToolbarButtons from './MarkdownToolbarButtons.vue'

export default {
  name: 'MarkdownEditor',
  components: {
    BubbleMenu,
    EditorContent,
    MarkdownToolbarButtons,
  },
  props: {
    modelValue: {
      type: String,
      default: '',
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const { environment } = useAppEnvironment()
    const isMobile = computed(() => environment.isMobile)
    const sourceMode = ref(false)
    const sourceDraft = ref(props.modelValue || '')
    const blockToolsOpen = ref(false)

    const editor = useEditor({
      extensions: [
        StarterKit,
        Link.configure({
          openOnClick: false,
          autolink: true,
          defaultProtocol: 'https',
        }),
        Placeholder.configure({
          placeholder: 'Начните писать…',
        }),
        Markdown,
      ],
      content: props.modelValue || '',
      contentType: 'markdown',
      editable: !props.disabled,
      onUpdate: ({ editor: current }) => {
        if (sourceMode.value) {
          return
        }
        emit('update:modelValue', current.getMarkdown())
      },
      onSelectionUpdate: () => {
        if (!isMobile.value) {
          return
        }
        blockToolsOpen.value = false
      },
    })

    const run = (action) => {
      const current = editor.value
      if (!current || props.disabled) {
        return
      }
      action(current)
    }

    const isActive = (name, attributes = {}) => {
      if (!editor.value) {
        return false
      }
      return editor.value.isActive(name, attributes)
    }

    const setLink = () => {
      if (!editor.value || props.disabled) {
        return
      }

      const previousUrl = editor.value.getAttributes('link').href
      const url = window.prompt('URL ссылки', previousUrl || 'https://')

      if (url === null) {
        return
      }

      if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
        return
      }

      editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
    }

    const shouldShowSelectionBubble = ({ editor: current, state }) => {
      if (!current?.isEditable || sourceMode.value) {
        return false
      }

      const { from, to, empty } = state.selection
      return !empty && from !== to
    }

    const onSourceInput = () => {
      emit('update:modelValue', sourceDraft.value)
    }

    const toggleSourceMode = () => {
      if (!editor.value || props.disabled) {
        return
      }

      blockToolsOpen.value = false

      if (sourceMode.value) {
        editor.value.commands.setContent(sourceDraft.value || '', { contentType: 'markdown' })
        sourceMode.value = false
        emit('update:modelValue', editor.value.getMarkdown())
        return
      }

      sourceDraft.value = editor.value.getMarkdown()
      sourceMode.value = true
    }

    watch(
      () => props.modelValue,
      (value) => {
        const next = value || ''
        sourceDraft.value = next

        if (!editor.value || sourceMode.value) {
          return
        }

        if (editor.value.getMarkdown() !== next) {
          editor.value.commands.setContent(next, { contentType: 'markdown' })
        }
      }
    )

    watch(
      () => props.disabled,
      (value) => {
        editor.value?.setEditable(!value)
      }
    )

    watch(sourceMode, (value) => {
      if (value) {
        blockToolsOpen.value = false
      }
    })

    onBeforeUnmount(() => {
      editor.value?.destroy()
    })

    return {
      editor,
      isMobile,
      sourceMode,
      sourceDraft,
      blockToolsOpen,
      run,
      isActive,
      setLink,
      shouldShowSelectionBubble,
      onSourceInput,
      toggleSourceMode,
    }
  },
}
</script>
