<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CMS media library
    |--------------------------------------------------------------------------
    |
    | Images attached to articles and pages. The rules live here rather than in
    | the controller because they are operational facts, not UI facts: the
    | service, the controller and the test all need to agree on what a legal
    | upload is, and a constant duplicated in two places is two answers the
    | day someone changes one of them.
    |
    */

    'media' => [

        /*
         * `public` on purpose, and the same disk the student documents and
         * branding assets already use. A second disk name for CMS images would
         * mean every call site branching on driver, and the branch would be
         * wrong on the first host where AWS_BUCKET is set — see the note in
         * config/filesystems.php.
         */
        'disk' => 'public',

        /*
         * 4 MB. A school photo off a phone is 2–4 MB; a logo is 20 KB. Beyond
         * this the honest answer is "that is a video", and a video uploaded as
         * an image is how a public disk fills up.
         */
        'max_kb' => (int) env('CMS_MEDIA_MAX_KB', 4096),

        /*
         * mime => the extension we will STORE, mapped from what the bytes say
         * they are, never from what the browser claimed.
         *
         * No SVG. An SVG is a document that renders in the site's own origin,
         * so one carrying <script> is stored XSS. Excluding it entirely is
         * cheaper and safer than trying to sanitise XML.
         */
        'allowed_mimes' => [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ],

        /*
         * The names the uploader MAY use. Checked against the browser-supplied
         * name as a first cheap filter only — the stored extension above is
         * what actually lands on disk, so getting past this buys an attacker
         * nothing.
         */
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],

        /*
         * Sharded by month so a library with ten thousand images does not
         * become one directory with ten thousand entries.
         */
        'directory' => 'cms-media',

    ],

];
