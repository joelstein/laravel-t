<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Translation Files Path
    |--------------------------------------------------------------------------
    |
    | The directory where PO translation files are stored, relative to the
    | application's lang_path(). Each locale should have its own .po file
    | (e.g., en.po, es.po, fr.po).
    |
    */

    'path' => lang_path('t'),

    /*
    |--------------------------------------------------------------------------
    | Source Locale
    |--------------------------------------------------------------------------
    |
    | The locale used as the source language in your PO files. Extracted
    | strings will have msgstr = msgid for this locale.
    |
    */

    'source_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | The locales your application supports. The extract command will
    | generate a PO file for each of these locales.
    |
    */

    'locales' => ['en'],

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | Locale to consult when the requested locale has no translation for a
    | string. The lookup chain is: requested locale -> base locale (e.g.
    | "es_MX" falls back to "es" automatically) -> this fallback locale.
    | Set to null to disable the explicit fallback (regional-to-base fallback
    | still applies).
    |
    */

    'fallback_locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for t() and @t() calls when extracting
    | translatable strings. Paths are relative to the application root.
    |
    */

    'scan_paths' => [
        'app',
        'resources/views',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Translations
    |--------------------------------------------------------------------------
    |
    | When enabled, translations are cached using Laravel's cache system.
    | This is recommended for production. Set to null to use the
    | APP_ENV check (caches only in production).
    |
    | Note that the t:cache command compiles PO files to plain PHP arrays in
    | bootstrap/cache, which OPcache serves from shared memory. A compiled
    | file takes precedence over this setting entirely.
    |
    */

    'cache' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache translations, in seconds. Defaults to 24 hours.
    |
    */

    'cache_ttl' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Log Missing Translations
    |--------------------------------------------------------------------------
    |
    | When enabled, missing translations (where the source string is used
    | as the fallback) are logged. Set to true to use the default channel,
    | a channel name (string) to use a specific channel, or null/false to
    | disable. Deduplicated per request by message+locale+context.
    |
    */

    'log_missing' => null,

];
