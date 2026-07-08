import Editor from '@toast-ui/editor'
import '@toast-ui/editor/dist/toastui-editor.css'
import '../scss/documents_editor.scss'
import { init_document_props_editors } from './props_panel.js'

const editor_instances = new Map()

function calculateViewportHeight(root_element) {
  const rect = root_element.getBoundingClientRect()
  const viewport_height = window.innerHeight || document.documentElement.clientHeight || 900
  const bottom_padding = 24
  const min_height = 460
  const max_height = 2200
  const dynamic_height = Math.floor(viewport_height - rect.top - bottom_padding)

  return Math.max(min_height, Math.min(max_height, dynamic_height))
}

function calculateContentHeight(root_element) {
  const wysiwyg_content = root_element.querySelector('.toastui-editor-ww-container .ProseMirror')
  const markdown_content = root_element.querySelector('.toastui-editor-md-container .toastui-editor-contents')
  const content_element = wysiwyg_content || markdown_content

  if (!content_element) {
    return null
  }

  const content_style = window.getComputedStyle(content_element)
  const margin_bottom = parseFloat(content_style.marginBottom || '0') || 0
  const chrome_padding = 150
  const min_height = 460
  const max_height = 5000
  const content_height = Math.ceil(content_element.scrollHeight + margin_bottom + chrome_padding)

  return Math.max(min_height, Math.min(max_height, content_height))
}

function applyWrapperStretch(root_element, editor_height) {
  const wrapper_min_height = editor_height + 64
  root_element.style.minHeight = `${wrapper_min_height}px`

  const stretch_targets = [
    root_element.closest('.form-tabless-fields'),
    root_element.closest('.tab-pane'),
    root_element.closest('.tab-content'),
    root_element.closest('.layout-cell')
  ].filter(Boolean)

  stretch_targets.forEach((target_element) => {
    const current_min_height = parseInt(target_element.style.minHeight || '0', 10)
    if (!Number.isFinite(current_min_height) || current_min_height < wrapper_min_height) {
      target_element.style.minHeight = `${wrapper_min_height}px`
    }
  })
}

function createEditor(root_element) {
  if (!root_element || editor_instances.has(root_element)) {
    return
  }

  const textarea = root_element.querySelector('textarea')
  const mount_target = root_element.querySelector('[data-documents-markdown-editor-target]')

  if (!textarea || !mount_target) {
    return
  }

  const initial_editor_height = calculateViewportHeight(root_element)
  applyWrapperStretch(root_element, initial_editor_height)

  const editor = new Editor({
    el: mount_target,
    initialValue: textarea.value || '',
    initialEditType: 'wysiwyg',
    previewStyle: 'tab',
    height: `${initial_editor_height}px`,
    usageStatistics: false,
    hideModeSwitch: true,
    autofocus: false
  })

  const syncTextarea = () => {
    textarea.value = editor.getMarkdown()
  }

  let resize_frame_id = null
  const adjustEditorHeight = () => {
    const viewport_height = calculateViewportHeight(root_element)
    const content_height = calculateContentHeight(root_element)
    const next_height = content_height === null
      ? viewport_height
      : Math.max(viewport_height, content_height)

    applyWrapperStretch(root_element, next_height)
    editor.setHeight(`${next_height}px`)
  }

  const scheduleAdjustEditorHeight = () => {
    if (resize_frame_id !== null) {
      cancelAnimationFrame(resize_frame_id)
    }

    resize_frame_id = requestAnimationFrame(() => {
      resize_frame_id = null
      adjustEditorHeight()
    })
  }

  editor.on('change', () => {
    syncTextarea()
    scheduleAdjustEditorHeight()
  })
  syncTextarea()
  scheduleAdjustEditorHeight()

  const onWindowResize = () => {
    scheduleAdjustEditorHeight()
  }

  window.addEventListener('resize', onWindowResize)
  editor_instances.set(root_element, {
    editor,
    onWindowResize,
    scheduleAdjustEditorHeight
  })

  setTimeout(() => {
    scheduleAdjustEditorHeight()
  }, 60)
}

function destroyEditor(root_element) {
  const instance = editor_instances.get(root_element)
  if (!instance) {
    return
  }

  window.removeEventListener('resize', instance.onWindowResize)
  instance.editor.destroy()
  editor_instances.delete(root_element)
}

function initEditors() {
  document.querySelectorAll('[data-documents-markdown-editor]').forEach((root_element) => {
    createEditor(root_element)
  })
}

function initDocumentFormUi() {
  initEditors()
  init_document_props_editors()
}

document.addEventListener('DOMContentLoaded', initDocumentFormUi)
document.addEventListener('render', initDocumentFormUi)
document.addEventListener('ajaxUpdateComplete', initDocumentFormUi)

window.addEventListener('beforeunload', () => {
  editor_instances.forEach((_instance, root_element) => {
    destroyEditor(root_element)
  })
})
