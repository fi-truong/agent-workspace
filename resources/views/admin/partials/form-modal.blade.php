{{--
  Reusable form modal for create/edit
  Usage:
  @include('admin.partials.form-modal', [
    'id' => 'promptModal',
    'title' => 'Create Prompt',
    'route' => route('admin.prompts.store'),
    'method' => 'POST',
    'fields' => [
      ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter prompt title'],
      ['name' => 'subject', 'label' => 'Subject', 'type' => 'select', 'options' => $subjects, 'required' => true],
      ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
      ['name' => 'preview_text', 'label' => 'Prompt Text', 'type' => 'textarea', 'required' => true],
    ],
    'submitLabel' => 'Save Prompt',
  ])
--}}

@php
  $id = $id ?? 'formModal';
  $title = $title ?? 'Create';
  $route = $route ?? '#';
  $method = $method ?? 'POST';
  $fields = $fields ?? [];
  $submitLabel = $submitLabel ?? 'Save';
  $editMode = $editMode ?? false;
  $editData = $editData ?? [];
  $extraHidden = $extraHidden ?? [];
@endphp

<div class="modal-overlay" id="{{ $id }}">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">{{ $title }}</h2>
      <button class="modal-close" data-close-modal="{{ $id }}">&times;</button>
    </div>

    <form action="{{ $route }}" method="{{ $method }}" id="{{ $id }}Form">
      @csrf
      @if($method !== 'POST')
        @method($method)
      @endif

      @foreach($extraHidden as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
      @endforeach

      <div class="modal-body">
        @foreach($fields as $field)
          <div class="form-group">
            <label for="{{ $field['name'] }}">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif</label>

            @php
              $name = $field['name'];
              $type = $field['type'] ?? 'text';
              $required = $field['required'] ?? false;
              $placeholder = $field['placeholder'] ?? '';
              $options = $field['options'] ?? [];
              $value = $editMode && isset($editData[$name]) ? $editData[$name] : ($field['value'] ?? old($name));
              $hint = $field['hint'] ?? null;
              $attrs = $field['attrs'] ?? [];
            @endphp

            @if($type === 'select')
              <select name="{{ $name }}" id="{{ $name }}" class="form-select" {{ $required ? 'required' : '' }}>
                <option value="">— Select —</option>
                @foreach($options as $optValue => $optLabel)
                  <option value="{{ $optValue }}" {{ $value == $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
                @endforeach
              </select>
            @elseif($type === 'textarea')
              <textarea name="{{ $name }}" id="{{ $name }}" class="form-textarea" placeholder="{{ $placeholder }}" {{ $required ? 'required' : '' }}>{{ $value }}</textarea>
            @elseif($type === 'checkbox')
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="{{ $name }}" value="1" {{ $value ? 'checked' : '' }} style="width:18px;height:18px;">
                <span>{{ $field['checkbox_label'] ?? 'Enable' }}</span>
              </label>
            @else
              <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" class="form-input" placeholder="{{ $placeholder }}" value="{{ $value }}" {{ $required ? 'required' : '' }} @foreach($attrs as $k => $v) {{ $k }}="{{ $v }}" @endforeach>
            @endif

            @if($hint)
              <p class="form-hint">{{ $hint }}</p>
            @endif
          </div>
        @endforeach
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" data-close-modal="{{ $id }}">Cancel</button>
        <button type="submit" class="btn-primary">{{ $submitLabel }}</button>
      </div>
    </form>
  </div>
</div>

@pushOnce('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Close modal handlers
  document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modalId = btn.dataset.closeModal;
      const modal = document.getElementById(modalId);
      if (modal) modal.classList.remove('open');
    });
  });

  // Close on overlay click
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.classList.remove('open');
      }
    });
  });

  // Escape key to close
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
  });
});

// Global function to open modal
window.openAdminModal = function(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.add('open');
};

// Global function to close modal
window.closeAdminModal = function(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.remove('open');
};
</script>
@endPushOnce