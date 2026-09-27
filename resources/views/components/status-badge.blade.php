@props(['status' => 'draft'])

@php
    // Two vocabularies share this badge: registration states and document states.
    // Both map onto the same tone scale so the UI reads consistently.
    $map = [
        // Registration
        'draft'     => ['label' => 'Belum Lengkap',  'tone' => 'neutral', 'icon' => 'clock'],
        'submitted' => ['label' => 'Terkirim',        'tone' => 'info',    'icon' => 'upload'],
        'pending'   => ['label' => 'Menunggu',        'tone' => 'warning', 'icon' => 'clock'],
        'revision'  => ['label' => 'Perlu Perbaikan', 'tone' => 'danger',  'icon' => 'alert-triangle'],
        'verified'  => ['label' => 'Terverifikasi',   'tone' => 'success', 'icon' => 'check-circle'],
        'rejected'  => ['label' => 'Ditolak',         'tone' => 'danger',  'icon' => 'x'],
        // Document
        'missing'   => ['label' => 'Belum diunggah',  'tone' => 'neutral', 'icon' => 'upload'],
        'valid'     => ['label' => 'Valid',           'tone' => 'success', 'icon' => 'check-circle'],

        // Academic year
        'upcoming'  => ['label' => 'Akan Datang',     'tone' => 'info',    'icon' => 'clock'],
        'archived'  => ['label' => 'Diarsipkan',      'tone' => 'neutral', 'icon' => 'archive'],

        // Classroom
        'inactive'  => ['label' => 'Tidak Aktif',     'tone' => 'warning', 'icon' => 'pause'],

        // Enrollment
        'promoted'  => ['label' => 'Naik Kelas',      'tone' => 'success', 'icon' => 'trending-up'],
        'retained'  => ['label' => 'Tinggal Kelas',   'tone' => 'warning', 'icon' => 'rotate-ccw'],
        'transferred'=>['label' => 'Pindah',          'tone' => 'info',    'icon' => 'move-right'],
        'graduated' => ['label' => 'Lulus',           'tone' => 'success', 'icon' => 'award'],
        'withdrawn' => ['label' => 'Berhenti',        'tone' => 'neutral', 'icon' => 'user-minus'],
        'completed' => ['label' => 'Selesai',         'tone' => 'success', 'icon' => 'check-circle'],
    ];

    // Accept a Registration model or a Student as a convenience, and never let
    // a non-scalar (e.g. a full model) reach the array lookup — that produced
    // "Cannot access offset of type Registration on array".
    if ($status instanceof \App\Models\Registration) {
        $status = $status->status;
    } elseif ($status instanceof \App\Models\Student) {
        $status = $status->status();
    }

    $key = is_string($status) ? $status : '';
    $s = $map[$key] ?? [
        'label' => $key !== '' ? ucfirst($key) : 'Tidak Diketahui',
        'tone' => 'neutral',
        'icon' => 'info',
    ];
@endphp

<span {{ $attributes->merge(['class' => "badge badge-{$s['tone']}"]) }}>
    <x-icon :name="$s['icon']" class="w-3 h-3" />
    {{ $s['label'] }}
</span>
