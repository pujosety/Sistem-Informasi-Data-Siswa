<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        p.meta { margin: 0 0 10px; color: #64748b; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e3a8a; color: #fff; padding: 5px; text-align: left; font-size: 8.5px; }
        td { border: 1px solid #e2e8f0; padding: 4px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .footer { margin-top: 10px; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">Dicetak pada {{ $generatedAt }} &nbsp;|&nbsp; Total: {{ $students->count() }} siswa</p>

    <table>
        <thead>
            <tr>
                <th style="width:22px">#</th><th>NISN</th><th>Nama</th><th>L/P</th><th>Angkatan</th>
                <th>Kelas</th><th>Jurusan</th><th>Asal Sekolah</th><th>Kelengkapan</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $i => $s)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $s->nisn }}</td>
                    <td>{{ $s->full_name }}</td>
                    <td>{{ $s->genderLabel() }}</td>
                    <td>{{ $s->entry_year ?? '-' }}</td>
                    <td>{{ $s->schoolClass?->name ?? '-' }}</td>
                    <td>{{ $s->schoolClass?->department?->name ?? '-' }}</td>
                    <td>{{ $s->previous_school ?? '-' }}</td>
                    <td>{{ $s->registration?->completeness ?? 0 }}%</td>
                    <td>{{ $s->registration?->statusLabel() ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="footer">Sistem Informasi Data Siswa — {{ config('app.name') }}</p>
</body>
</html>
