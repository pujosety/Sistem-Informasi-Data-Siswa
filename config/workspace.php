<?php

return [
    // The current Docker PHP upload limit is 2 MiB. Raise the environment and
    // proxy limits together before increasing this value; large media needs a
    // resumable/chunked upload path rather than a larger synchronous request.
    'max_file_kb' => (int) env('WORKSPACE_MAX_FILE_KB', 2048),

    // WORKSPACE_QUOTA_BYTES: retained bytes per owner in each active school.
    // Default 1 GiB (1073741824 bytes); all historical versions and Trash count.
    // Zero denies additional bytes; reducing quota never deletes existing data.
    // This does not increase the separate 2 MiB synchronous upload limit.
    'quota_bytes' => max(0, (int) env('WORKSPACE_QUOTA_BYTES', 1073741824)),

    // Resumable integration (the parent integrator owns these shared files):
    // routes/web.php: require __DIR__.'/fitur-workspace-uploads.php';
    // resources/js/app.js: import './workspace-resumable-upload';
    // Drive Blade: <x-workspace.resumable-upload :folder="$currentFolder?->public_id" />
    // Scheduler: Schedule::command('workspace:uploads-cleanup')->everyFiveMinutes()
    //     ->withoutOverlapping(); run the Laravel scheduler on the same staging volume.
    //
    // Infrastructure: keep existing PHP 2M limits; multipart chunks never exceed
    // 1 MiB. Staging must be private, persistent and writable. On multiple app
    // instances, share staging (and DB) or use a single sticky upload worker;
    // ephemeral/serverless node-local storage cannot reliably resume sessions.
    // The final workspace disk can be local or private S3. Enforce private bucket
    // policy and install/configure its existing Flysystem adapter separately.
    // Finalization streams synchronously under owner/session locks; align proxy,
    // PHP and S3 timeouts for the configured maximum. Capacity reservations are
    // admission bounds, not filesystem free-space guarantees: monitor volumes,
    // leave headroom for PHP upload_tmp_dir, other data and concurrent requests.
    // Quota is checked on admission and again on finalize; synchronous/WOPI writes
    // do not promise quota reservations to open sessions and can consume capacity.
    // After an ambiguous finalize response, retry the SAME session UUID; completed
    // UUID tombstones outlive upload expiry, but are pruned after retention_days.
    // Browser WebCrypto requires HTTPS/localhost and reads the selected full file
    // into memory for SHA-256; server chunk/assembly/storage copies remain streamed.
    // PHPUnit, real browser and private-S3 acceptance remain deferred until wiring.
    'uploads' => [
        'max_bytes' => max(1, (int) env('WORKSPACE_UPLOAD_MAX_BYTES', 104857600)),
        'chunk_bytes' => max(1, min(1048576, (int) env('WORKSPACE_UPLOAD_CHUNK_BYTES', 1048576))),
        'max_chunks' => max(1, min(4096, (int) env('WORKSPACE_UPLOAD_MAX_CHUNKS', 1024))),
        'ttl_minutes' => max(1, (int) env('WORKSPACE_UPLOAD_TTL_MINUTES', 1440)),
        'max_sessions_per_owner' => max(1, (int) env('WORKSPACE_UPLOAD_MAX_SESSIONS', 3)),
        // Conservative reservations: chunks + assembly + in-flight final object.
        'owner_capacity_bytes' => max(0, (int) env('WORKSPACE_UPLOAD_OWNER_CAPACITY_BYTES', 629145600)),
        'capacity_bytes' => max(0, (int) env('WORKSPACE_UPLOAD_CAPACITY_BYTES', 1073741824)),
        'temp_root' => env('WORKSPACE_UPLOAD_TEMP_ROOT', storage_path('app/workspace-upload-sessions')),
        'result_retention_days' => max(1, (int) env('WORKSPACE_UPLOAD_RESULT_RETENTION_DAYS', 7)),
    ],

    // Fail closed: blank endpoint, unavailable engine and missing history deny access.
    // Numeric private IP avoids unbounded resolver waits; Unix sockets also supported.
    'scanner' => [
        'host' => env('WORKSPACE_CLAMD_HOST', ''),
        'port' => (int) env('WORKSPACE_CLAMD_PORT', 3310),
        'unix_socket' => env('WORKSPACE_CLAMD_UNIX_SOCKET', ''),
        'timeout_seconds' => max(1, min(120, (float) env('WORKSPACE_SCAN_TIMEOUT_SECONDS', 30))),
        'max_bytes' => max(1, min(1073741824, (int) env('WORKSPACE_SCAN_MAX_BYTES', 104857600))),
    ],

    'wopi' => [
        'token_ttl_minutes' => max(1, (int) env('WORKSPACE_WOPI_TOKEN_TTL_MINUTES', 120)),
        'lock_ttl_minutes' => max(1, (int) env('WORKSPACE_WOPI_LOCK_TTL_MINUTES', 30)),
        'host_url' => rtrim(
            (string) (env('WORKSPACE_WOPI_HOST_URL') ?: env('APP_URL', 'http://localhost:8000')),
            '/',
        ),
        'supported_extensions' => array_values(array_filter(array_map(
            static fn (string $extension): string => strtolower(trim($extension)),
            explode(',', (string) env('WORKSPACE_WOPI_EXTENSIONS', 'doc,docx,odt,rtf,txt,xls,xlsx,ods,ppt,pptx,odp,csv')),
        ))),
    ],

    'collabora' => [
        'internal_url' => rtrim((string) env('COLLABORA_INTERNAL_URL', 'http://collabora:9980'), '/'),
        'public_url' => rtrim((string) env('COLLABORA_PUBLIC_URL', 'http://localhost:9980'), '/'),
    ],
];
