<template>
  <div ref="rootRef" class="tg-site-html" />
</template>

<script>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { mountHtmlFragment, unmountHtmlFragment } from './html-fragment'

export default {
  name: 'HtmlFragment',
  props: {
    html: {
      type: String,
      default: '',
    },
  },
  setup(props) {
    const rootRef = ref(null)

    const render = () => {
      mountHtmlFragment(rootRef.value, props.html)
    }

    onMounted(render)

    watch(
      () => props.html,
      () => {
        render()
      }
    )

    onBeforeUnmount(() => {
      unmountHtmlFragment(rootRef.value)
    })

    return { rootRef }
  },
}
</script>
