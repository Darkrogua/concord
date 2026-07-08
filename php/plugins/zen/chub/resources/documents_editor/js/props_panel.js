/**
 * Компактный редактор свойств документа (key, value_text, value_tags).
 */

function split_tags(input_string) {
  return input_string
    .split(/\s*,\s*/)
    .map((tag) => tag.trim())
    .filter(Boolean)
}

function normalize_server_row(raw_row) {
  const key = String(raw_row.key ?? '').trim()
  const tags = Array.isArray(raw_row.value_tags)
    ? raw_row.value_tags.map((tag) => String(tag))
    : []
  const text = String(raw_row.value_text ?? '')

  if (tags.length > 0) {
    return {
      key,
      mode: 'list',
      value_text: '',
      value_tags: tags,
      tags_string: tags.join(', ')
    }
  }

  const trimmed = text.trim()
  if (trimmed.startsWith('{')) {
    try {
      const parsed = JSON.parse(trimmed)
      if (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return {
          key,
          mode: 'json',
          value_text: text,
          value_tags: [],
          tags_string: ''
        }
      }
    } catch (_) {
      /* строка остаётся текстом */
    }
  }

  return {
    key,
    mode: 'text',
    value_text: text,
    value_tags: [],
    tags_string: ''
  }
}

function row_to_server(row) {
  const key = String(row.key ?? '').trim()
  if (key === '') {
    return null
  }

  const base = {
    key,
    value_text: '',
    value_tags: []
  }

  if (row.mode === 'list') {
    base.value_tags = split_tags(String(row.tags_string ?? ''))

    return base
  }

  if (row.mode === 'json') {
    base.value_text = String(row.value_text ?? '')

    return base
  }

  base.value_text = String(row.value_text ?? '')

  return base
}

function commit_rows(sink, rows) {
  const payload = []
  rows.forEach((row) => {
    const converted = row_to_server(row)
    if (converted) {
      payload.push(converted)
    }
  })
  sink.value = JSON.stringify(payload)
}

function append_row_element(root_element, rows, sink, row_index) {
  const rows_root = root_element.querySelector('[data-document-props-rows]')
  const row = rows[row_index]

  const row_wrap = document.createElement('div')
  row_wrap.className = 'document-props-row'

  const key_input = document.createElement('input')
  key_input.type = 'text'
  key_input.className = 'form-control document-props-key'
  key_input.placeholder = 'ключ'
  key_input.value = row.key ?? ''
  key_input.autocomplete = 'off'
  key_input.addEventListener('input', () => {
    rows[row_index].key = key_input.value
    commit_rows(sink, rows)
  })

  const mode_select = document.createElement('select')
  mode_select.className = 'form-control document-props-mode'
  ;[
    ['text', 'Строка'],
    ['list', 'Список'],
    ['json', 'JSON']
  ].forEach(([value, label]) => {
    const opt = document.createElement('option')
    opt.value = value
    opt.textContent = label
    mode_select.appendChild(opt)
  })
  mode_select.value = row.mode
  mode_select.addEventListener('change', () => {
    const next_mode = mode_select.value
    const current = rows[row_index]

    if (next_mode === 'list') {
      current.mode = 'list'
      const had_tags = (current.value_tags ?? []).length > 0
      current.tags_string = had_tags
        ? current.value_tags.join(', ')
        : split_tags(String(current.value_text ?? '')).join(', ')
      current.value_text = ''
      current.value_tags = []
    } else if (next_mode === 'json') {
      current.mode = 'json'
      current.value_text = current.value_text ?? ''
      current.value_tags = []
      current.tags_string = ''
    } else {
      current.mode = 'text'
      current.value_text = current.value_text ?? ''
      current.value_tags = []
      current.tags_string = ''
    }

    commit_rows(sink, rows)
    render_all_rows(root_element, rows, sink)
  })

  const value_wrap = document.createElement('div')
  value_wrap.className = 'document-props-value'

  if (row.mode === 'list') {
    const input_el = document.createElement('input')
    input_el.type = 'text'
    input_el.className = 'form-control document-props-input'
    input_el.placeholder = 'теги через запятую'
    input_el.value = row.tags_string ?? ''
    input_el.autocomplete = 'off'
    input_el.addEventListener('input', () => {
      rows[row_index].tags_string = input_el.value
      commit_rows(sink, rows)
    })
    value_wrap.appendChild(input_el)
  } else {
    const textarea_el = document.createElement('textarea')
    textarea_el.className =
      'form-control document-props-input document-props-input-grow'
    textarea_el.rows = row.mode === 'json' ? 3 : 1
    textarea_el.placeholder =
      row.mode === 'json' ? '{"nested": true}' : 'значение'
    textarea_el.value = row.value_text ?? ''
    textarea_el.addEventListener('input', () => {
      rows[row_index].value_text = textarea_el.value
      commit_rows(sink, rows)
    })
    value_wrap.appendChild(textarea_el)
  }

  const remove_btn = document.createElement('button')
  remove_btn.type = 'button'
  remove_btn.className = 'document-props-remove'
  remove_btn.setAttribute('aria-label', 'Удалить')
  remove_btn.textContent = '×'
  remove_btn.addEventListener('click', () => {
    rows.splice(row_index, 1)
    commit_rows(sink, rows)
    render_all_rows(root_element, rows, sink)
  })

  row_wrap.append(key_input, mode_select, value_wrap, remove_btn)
  rows_root.appendChild(row_wrap)
}

function render_all_rows(root_element, rows, sink) {
  const rows_root = root_element.querySelector('[data-document-props-rows]')
  rows_root.replaceChildren()
  rows.forEach((_row, index) => {
    append_row_element(root_element, rows, sink, index)
  })
}

function parse_initial_rows(json_string) {
  try {
    const parsed = JSON.parse(json_string || '[]')
    if (!Array.isArray(parsed)) {
      return []
    }

    return parsed.map((item) => normalize_server_row(item))
  } catch (_) {
    return []
  }
}

function bind_document_props_editor(root_element) {
  const sink = root_element.querySelector('.document-props-json-sink')
  const add_btn = root_element.querySelector('[data-document-props-add]')
  if (!sink || !add_btn || root_element.dataset.documentPropsBound === '1') {
    return
  }

  root_element.dataset.documentPropsBound = '1'

  let rows = parse_initial_rows(sink.value)

  const sync = () => {
    commit_rows(sink, rows)
  }

  sync()
  render_all_rows(root_element, rows, sink)

  add_btn.addEventListener('click', () => {
    rows.push({
      key: '',
      mode: 'text',
      value_text: '',
      value_tags: [],
      tags_string: ''
    })
    sync()
    render_all_rows(root_element, rows, sink)
  })

  root_element.addEventListener(
    'focusout',
    () => {
      sync()
    },
    true
  )

  const form = root_element.closest('form')
  if (form) {
    form.addEventListener(
      'submit',
      () => {
        sync()
      },
      true
    )
  }
}

export function init_document_props_editors(scope = document) {
  scope.querySelectorAll('[data-document-props-editor]').forEach((root_element) => {
    bind_document_props_editor(root_element)
  })
}
