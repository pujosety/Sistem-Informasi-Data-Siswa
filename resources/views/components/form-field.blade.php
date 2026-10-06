@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'rows' => null,
    'min' => null,
    'max' => null,
    'step' => null,
    'placeholder' => null,
    'autocomplete' => null,
])

@php
    $id = $attributes->get('id') ?: ($name ? 'f-'.preg_replace('/[^a-z0-9]+/i', '-', $name) : 'f-'.uniqid());
    $hasError = $errors->has($name);
    $fieldAttributes = $attributes->except(['class', 'id']);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'w-full']) }}>
    @if ($label)
        <label class="label" for="{{ $id }}">
            {{ $label }}
            @if ($required)
                <span class="text-[var(--app-danger)]" aria-hidden="true">*</span>
                <span class="sr-only">(wajib diisi)</span>
            @endif
        </label>
    @endif

    @if ($type === 'textarea')
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows ?? 3 }}"
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $fieldAttributes }}
            class="field @if ($hasError) field-error @endif"
        >{{ old($name, $value) }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $fieldAttributes }}
            class="field @if ($hasError) field-error @endif"
        >
            @if ($placeholder ?? true)
                <option value="">{{ $placeholder ?? '— Pilih —' }}</option>
            @endif
            {{ $slot }}
        </select>
    @else
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            @if ($step !== null) step="{{ $step }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $fieldAttributes }}
            class="field @if ($hasError) field-error @endif"
        >
    @endif

    @if ($hasError)
        <p id="{{ $id }}-error" class="error-text flex items-start gap-1">
            <x-icon name="alert-circle" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{{ $errors->first($name) }}</span>
        </p>
    @elseif ($hint)
        <p class="help-text">{{ $hint }}</p>
    @endif
</div>
