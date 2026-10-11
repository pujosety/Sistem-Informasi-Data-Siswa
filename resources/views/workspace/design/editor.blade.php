<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $project->name }} — Workspace Design</title>
@php
$manifestPath = public_path('workspace-design-build/.vite/manifest.json');
$manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
$entry = $manifest['src/main.tsx'] ?? null;
@endphp
@if($entry)
@foreach($entry['css'] ?? [] as $css)<link rel="stylesheet" href="{{ '/workspace-design-build/'.$css }}">@endforeach
<script type="module" src="{{ '/workspace-design-build/'.$entry['file'] }}"></script>
@endif
</head><body>
@if($entry)
<script id="workspace-design-config" type="application/json">{!! json_encode([
'project' => $project->public_id,
'endpoint' => route('workspace.design.show', $project->public_id, false),
'assets' => route('workspace.design.assets.attach', $project->public_id, false),
'index' => route('workspace.design.index', [], false),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
<noscript><p>This editor needs JavaScript. <a href="{{ route('workspace.design.index') }}">Back to designs</a></p></noscript>
<div id="app" aria-label="Private design editor"></div>
@else
<main><h1>Editor assets not installed</h1><p>Run npm ci in resources/workspace-design, then npm run build from the repository root to publish the editor assets.</p></main>
@endif
</body></html>
