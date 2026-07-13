<template>
  <div class="concord-simple-editor">
    <div class="concord-simple-editor__toolbar" role="toolbar" aria-label="Форматирование">
      <button
        type="button"
        class="concord-simple-editor__btn"
        :class="{ 'concord-simple-editor__btn--active': isActive('bold') }"
        title="Жирный"
        @click="run((editor) => editor.chain().focus().toggleBold().run())"
      >
        B
      </button>
      <button
        type="button"
        class="concord-simple-editor__btn"
        :class="{ 'concord-simple-editor__btn--active': isActive('italic') }"
        title="Курсив"
        @click="run((editor) => editor.chain().focus().toggleItalic().run())"
      >
        I
      </button>
      <button
        type="button"
        class="concord-simple-editor__btn"
        :class="{ 'concord-simple-editor__btn--active': isActive('bulletList') }"
        title="Маркированный список"
        @click="run((editor) => editor.chain().focus().toggleBulletList().run())"
      >
        •
      </button>
      <button
        type="button"
        class="concord-simple-editor__btn"
        :class="{ 'concord-simple-editor__btn--active': isActive('orderedList') }"
        title="Нумерованный список"
        @click="run((editor) => editor.chain().focus().toggleOrderedList().run())"
      >
        1.
      </button>
      <button
        type="button"
        class="concord-simple-editor__btn"
        title="Ссылка"
        @click="setLink"
      >
        🔗
      </button>
    </div>
    <EditorContent :editor="editor" class="concord-simple-editor__surface" />
  </div>
</template>

<script>
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { Markdown } from '@tiptap/markdown'
import { onBeforeUnmount, watch } from 'vue'

export default {
  name: 'ConcordSimpleEditor',
  components: { EditorContent },
  props: {
    modelValue: {
      type: String,
      default: '',
    },
    placeholder: {
      type: String,
      default: 'Введите текст…',
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const editor = useEditor({
      extensions: [
        StarterKit,
        Link.configure({
          openOnClick: false,
          autolink: true,
          defaultProtocol: 'https',
        }),
        Placeholder.configure({
          placeholder: props.placeholder,
        }),
        Markdown,
      ],
      content: props.modelValue || '',
      contentType: 'markdown',
      onUpdate: ({ editor: current }) => {
        emit('update:modelValue', current.getMarkdown())
      },
    })

    watch(
      () => props.modelValue,
      (value) => {
        const current = editor.value
        if (!current) {
          return
        }
        const markdown = value || ''
        if (markdown !== current.getMarkdown()) {
          current.commands.setContent(markdown, false, { contentType: 'markdown' })
        }
      }
    )

    onBeforeUnmount(() => {
      editor.value?.destroy()
    })

    function run(action) {
      if (!editor.value) {
        return
      }
      action(editor.value)
    }

    function isActive(name) {
      return editor.value?.isActive(name) || false
    }

    function setLink() {
      if (!editor.value) {
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

    return {
      editor,
      run,
      isActive,
      setLink,
    }
  },
}
</script>
