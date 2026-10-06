@props([
    'type' => 'school',
    'alt' => 'Ilustrasi kegiatan SMP 1 LYFLA',
    'aspect' => '16/9',
    'class' => '',
])

@php
    $assets = [
        'school' => 'images/school/campus-entry-checkpoint.webp',
        'profile' => 'images/school/students-indonesia.webp',
        'program' => 'images/school/computer-lab-class.webp',
        'news' => 'images/school/students-tablet-courtyard.webp',
        'contact' => 'images/school/teacher-guidance-classroom.webp',
        'teacher' => 'images/school/staff-meeting-tablet.webp',
        'facility' => 'images/school/students-library-tablet.webp',
    ];
    $path = $assets[$type] ?? $assets['school'];
@endphp

<figure class="group relative overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface-muted)] aspect-[{{ $aspect }}] {{ $class }}">
    <img src="{{ asset($path) }}"
         alt="{{ $alt }}"
         loading="lazy"
         decoding="async"
         class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.02]"
         onerror="this.hidden=true; this.nextElementSibling.hidden=false">
    <div hidden class="absolute inset-0 grid place-items-center bg-[var(--app-surface-muted)] p-6 text-center text-small text-[var(--app-text-muted)]">
        <span>Visual LYFLA belum tersedia.</span>
    </div>
</figure>
