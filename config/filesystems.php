<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Should the "public" disk be backed by S3?
    |--------------------------------------------------------------------------
    |
    | Every environment that supplies an S3 bucket gets object storage for
    | the `public` disk automatically. The switch is the presence of a bucket
    | name rather than a separate on/off flag, so a host cannot end up with
    | half the configuration applied.
    |
    | WHY THIS IS A SINGLE DISK RATHER THAN A SEPARATE "s3" DISK
    |
    | The application stores files through `Storage::disk('public')` in four
    | places: uploaded student documents, the two branding assets, and their
    | deletions. Those call sites are correct for any driver and are exactly
    | what should not have to know which host the app is on. Introducing a
    | separate disk name would mean every one of them has to branch, and the
    | branch would be wrong the moment a new call site was added.
    |
    | WHY S3 IS REQUIRED ON SERVERLESS HOSTS
    |
    | A Vercel function filesystem is read-only outside /tmp and is discarded
    | with the container. A document uploaded there would vanish on the next
    | cold start, taking the student's KK with it. `storage:link` also cannot
    | help: the symlink resolves inside a container that no longer exists, so
    | the file 404s even before it is recycled.
    |
    */

    'public_disk_is_s3' => (bool) env('AWS_BUCKET'),

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            /*
             | The root moves to a writable path on a host that has no
             | writable storage/ — serverless in particular.
             |
             | storage/app/private is inside the read-only application bundle
             | there, so the first thing Flysystem does is mkdir it, and that
             | throws UnableToCreateDirectory. Because Filesystem is resolved
             | by the FIRST middleware, before any controller and before the
             | session store, the failure looks identical on every route: a
             | plain 500 with no application output. Nothing about the request
             | distinguishes a missing writable disk from a broken database.
             |
             | STORAGE_PRIVATE_PATH is set by the deploy config. It is read from
             | the environment rather than hard-coded so the same config works
             | on a normal host, where storage/ is writable and the local path
             | is the right answer.
             */
            'root' => env('STORAGE_PRIVATE_PATH', storage_path('app/private')),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => env('AWS_BUCKET') ? 's3' : 'local',
            'root' => env('AWS_BUCKET') ? '' : env('STORAGE_PUBLIC_PATH', storage_path('app/public')),
            /*
             | Host-relative by default.
             |
             | This used to be built from APP_URL with a http://localhost
             | fallback, so with APP_URL unset every document and branding URL
             | resolved to localhost even on the real domain. A relative URL is
             | resolved by the browser against the current host, which is right
             | on every environment, and env('ASSET_URL') still allows an
             | absolute CDN when one is configured.
             */
            'url' => env('ASSET_URL')
                ? rtrim(env('ASSET_URL'), '/').'/storage'
                : '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'http' => [
                // Wasmer's volume S3 endpoint needs an explicit CA bundle in
                // PHPix; relying only on libcurl's system default made every
                // exists()/readStream() call fail with cURL error 60.
                'verify' => file_exists(base_path('resources/certs/cacert.pem'))
                    ? base_path('resources/certs/cacert.pem')
                    : true,
            ],
            'options' => [
                'http' => [
                    'verify' => file_exists(base_path('resources/certs/cacert.pem'))
                        ? base_path('resources/certs/cacert.pem')
                        : true,
                ],
            ],
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
