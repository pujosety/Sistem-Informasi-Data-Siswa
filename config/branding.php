<?php

/*
|--------------------------------------------------------------------------
| Branding
|--------------------------------------------------------------------------
|
| ONE PLACE for every brand string and every brand colour.
|
| WHY THIS FILE EXISTS
|
| The wordmark used to be written into the templates: 43 occurrences of "SIDA"
| across 105 views, plus the title pattern, the PWA manifest, the Android theme
| colour and the login lockup. A rename meant finding all of them, and missing
| one left the application with two names on the same screen.
|
| The rebrand to LYFLA exposed that immediately: the token layer moved to
| maroon, but `theme-color` stayed navy and half the dashboard still referenced
| `brand-500` — the old blue — directly instead of through a semantic token. The
| brand was in two places at once, which is the condition this file removes.
|
| WHAT IS *NOT* HERE, DELIBERATELY
|
| Route names, table names, model names, the 103 permission names and the env
| vars. Those are identifiers, not copy. Renaming 167 route names or 103
| permissions is a silent auth outage for no user-visible gain.
|
| SCHOOL vs PLATFORM
|
| `platform` is LYFLA and belongs to this file. `school` is the institution and
| belongs to the settings table, because an administrator changes it without a
| deploy. The two are deliberately not merged: LYFLA is the product, the school
| is the tenant, and a future multi-school install keeps the first while changing
| the second.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Platform brand
    |--------------------------------------------------------------------------
    |
    | The product identity. Fixed in code on purpose — this is not something an
    | administrator may edit, because a school that renames the platform it is
    | running on is a support call.
    |
    */

    'platform' => [
        'name' => 'LYFLA',
        'full_name' => 'LYFLA',
        'expansion' => 'Learning & Your Future, Linked Anywhere',

        'positioning' => 'Modern Education Management Platform',

        // Optional emotional line. Deliberately short and uppercase: it sits
        // under the wordmark on the login screen and in the sidebar footer, and
        // anything longer stops being a tagline and starts being a sentence.
        'phrase' => 'LEARN • GROW • BELONG',
    ],

    /*
    |--------------------------------------------------------------------------
    | Colour tokens
    |--------------------------------------------------------------------------
    |
    | Maroon is the LYFLA primary. The neutrals are WARM — cream, taupe, dusty
    | rose — because a cool grey against maroon reads as two different products
    | meeting, which is the exact feeling the brief rejects.
    |
    | `gold` is defined and deliberately unused. The brief allows it as a very
    | limited premium accent; defining it here means the day it is used it is
    | used consistently, rather than as #D4AF37 typed into whichever view
    | happened to want it that week.
    |
    */

    'colors' => [

        // Primary — maroon / burgundy
        'primary' => '#681D2A',
        'primary_hover' => '#541722',
        'primary_dark' => '#47141D',
        'primary_alt' => '#7A2538',
        'accent' => '#A83C4C',
        'soft' => '#F7F3EF',

        // Supporting neutrals — warm
        'background' => '#FBF9F8',
        'surface' => '#FFFFFF',
        'surface_secondary' => '#F5F1EF',
        'text_primary' => '#241A1C',
        'text_secondary' => '#5C4A4E',
        'text_muted' => '#8A7479',
        'border' => '#E4DADD',
        'border_soft' => '#F0EAEC',

        // Status — chosen to sit beside maroon without belonging to it
        'success' => '#2E7D5B',
        'warning' => '#B4761A',
        'danger' => '#B3261E',
        'info' => '#2B5F8A',

        // Reserved. Not referenced by any token.
        'gold' => '#B08D3F',
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | Paths only. A missing file must not be fatal: `asset()` on a nonexistent
    | path returns a URL that 404s, and the components that consume these have
    | a text fallback, so a half-finished rebrand degrades to a wordmark rather
    | than to a broken-image icon.
    |
    */

    /*
     * These are the supplied brand renders, not placeholders: PNG with a real
     * alpha channel, cropped to the artwork and downsampled. The originals
     * carry 71% fully-transparent pixels, so nothing had to be keyed — an
     * attempt to alpha-key them against white erased the entire artwork,
     * because the background is transparent black rather than white pixels.
     *
     * `logo` is the horizontal lockup (globe + cap + LYFLA + tagline).
     */
    'assets' => [
        'logo' => 'branding/lyfla-logo.png',
        'logo_icon' => 'branding/lyfla-mark-flame.png',
        'logo_horizontal' => 'branding/lyfla-logo.png',

        // Alternative marks, supplied alongside the primary lockup. Kept as
        // separate files rather than crops of one image so each can be tuned
        // for its own optical size later without re-cutting the others.
        'mark_flame' => 'branding/lyfla-mark-flame.png',
        'mark_books' => 'branding/lyfla-mark-books.png',
        'mark_globe' => 'branding/lyfla-mark-globe.png',
        'mark_laptop' => 'branding/lyfla-mark-laptop.png',
        'mark_backpack' => 'branding/lyfla-mark-backpack.png',
        'mark_desk' => 'branding/lyfla-mark-desk.png',

        // Promotional and illustrative. Deliberately NOT used inside data-heavy
        // pages: a 3D mascot next to a table of students is decoration where
        // the brief asks for operational clarity.
        'campus' => 'branding/lyfla-building.png',
        'mascot_student' => 'branding/lyfla-mascot-student.png',
        'mascot_staff' => 'branding/lyfla-mascot-staff.png',

        // The icon-only mark, pre-sized for the PWA manifest.
        'logo_icon' => 'branding/lyfla-mark-flame.png',
        'favicon' => 'branding/favicon.ico',
        'apple_touch_icon' => 'branding/apple-touch-icon.png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser / PWA
    |--------------------------------------------------------------------------
    |
    | `theme_color` was navy while `--app-primary` was maroon, so an Android
    | status bar showed one brand above a page in another. They are read from the
    | same value now and cannot disagree.
    |
    */

    'theme_color' => '#681D2A',

    /*
    |--------------------------------------------------------------------------
    | Document defaults
    |--------------------------------------------------------------------------
    |
    | What a generated report or exported PDF says about its own producer.
    | Previously each export hardcoded its own string.
    |
    */

    'document' => [
        'produced_by' => 'LYFLA — Learning & Your Future, Linked Anywhere',
        'footer' => 'LYFLA • Modern Education Management Platform',
        'confidentiality' => 'Dokumen ini dihasilkan oleh LYFLA dan bersifat resmi.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth
    |--------------------------------------------------------------------------
    |
    | Off until Socialite is installed and credentials exist. The rebrand brief
    | asks for Google login, and the rule that matters is not in this file: an
    | unknown Google user must never receive an administrative role, and must
    | not bypass a closed-registration policy. See AuthController wiring.
    |
    */

    'socialite' => [
        'google' => [
            'enabled' => (bool) env('GOOGLE_OAUTH_ENABLED', false),
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        ],
    ],
];