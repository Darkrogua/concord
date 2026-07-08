<template>
  <template v-if="showMarks">
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('bold') }"
      :disabled="disabled"
      title="Жирный"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleBold().run())"
    >
      <strong>B</strong>
    </button>
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('italic') }"
      :disabled="disabled"
      title="Курсив"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleItalic().run())"
    >
      <em>I</em>
    </button>
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('strike') }"
      :disabled="disabled"
      title="Зачёркнутый"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleStrike().run())"
    >
      <s>S</s>
    </button>
    <button
      v-if="variant === 'marks'"
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('link') }"
      :disabled="disabled"
      title="Ссылка"
      @mousedown.prevent
      @click="onLink"
    >
      ↗
    </button>
  </template>

  <span v-if="showMarks && showBlocks" class="tg-markdown-toolbar__sep" aria-hidden="true" />

  <template v-if="showBlocks">
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('heading', { level: 2 }) }"
      :disabled="disabled"
      title="Заголовок 2"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleHeading({ level: 2 }).run())"
    >
      H2
    </button>
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('heading', { level: 3 }) }"
      :disabled="disabled"
      title="Заголовок 3"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleHeading({ level: 3 }).run())"
    >
      H3
    </button>
    <span v-if="variant === 'full'" class="tg-markdown-toolbar__sep" aria-hidden="true" />
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('bulletList') }"
      :disabled="disabled"
      title="Маркированный список"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleBulletList().run())"
    >
      •
    </button>
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('orderedList') }"
      :disabled="disabled"
      title="Нумерованный список"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleOrderedList().run())"
    >
      1.
    </button>
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('blockquote') }"
      :disabled="disabled"
      title="Цитата"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleBlockquote().run())"
    >
      “
    </button>
    <button
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('codeBlock') }"
      :disabled="disabled"
      title="Блок кода"
      @mousedown.prevent
      @click="run((current) => current.chain().focus().toggleCodeBlock().run())"
    >
      &lt;/&gt;
    </button>
    <button
      v-if="variant === 'full'"
      type="button"
      class="tg-markdown-toolbar__btn"
      :class="{ 'tg-markdown-toolbar__btn--active': isActive('link') }"
      :disabled="disabled"
      title="Ссылка"
      @mousedown.prevent
      @click="onLink"
    >
      ↗
    </button>
  </template>
</template>

<script>
export default {
  name: 'MarkdownToolbarButtons',
  props: {
    variant: {
      type: String,
      default: 'full',
      validator: (value) => ['full', 'marks', 'blocks'].includes(value),
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    isActive: {
      type: Function,
      required: true,
    },
    run: {
      type: Function,
      required: true,
    },
    onLink: {
      type: Function,
      required: true,
    },
  },
  computed: {
    showMarks() {
      return this.variant === 'full' || this.variant === 'marks'
    },
    showBlocks() {
      return this.variant === 'full' || this.variant === 'blocks'
    },
  },
}
</script>
