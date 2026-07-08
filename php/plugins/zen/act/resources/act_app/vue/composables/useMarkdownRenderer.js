import MarkdownIt from 'markdown-it'

let renderer = null

export function useMarkdownRenderer() {
  if (!renderer) {
    renderer = new MarkdownIt({
      html: false,
      linkify: true,
      breaks: true,
    })
  }

  const renderMarkdown = (markdown) => {
    const source = typeof markdown === 'string' ? markdown.trim() : ''
    if (!source) {
      return ''
    }
    return renderer.render(source)
  }

  return { renderMarkdown }
}
