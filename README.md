# Laravel T

Gettext PO-based translations for Laravel with ICU MessageFormat support. Use source strings as keys — no more `auth.failed` style lookup tables — and manage translations with standard PO files that every translation tool already understands.

## What It Does

Replaces Laravel's key-based translator with a `t()` helper (and `@t()` Blade directive) that looks up translations by their English source string:

```php
t('Hello, :name!', ['name' => $user->name])
```

- **PO files as the source of truth.** One `.po` file per locale, loaded via [gettext/gettext](https://github.com/php-gettext/Gettext). Works with Poedit, Crowdin, Weblate, and every other gettext-aware tool.
- **Readable fallbacks.** When a translation is missing, the source string renders as-is — no `translation.missing.key` artifacts.
- **ICU MessageFormat.** Full plural, select, and number formatting via PHP's `MessageFormatter`: `t('{count, plural, one {# item} other {# items}}', ['count' => $total])`.
- **Contextual translations.** Disambiguate identical strings with `msgctxt`: `t('May', context: 'month')`.
- **Closure-based inline markup.** Wrap translated text in dynamic tags without splitting the sentence: `t('Click <a>here</a>.', ['a' => fn ($s) => "<a href=\"/next\">{$s}</a>"])`.
- **Cached in production.** Parsed PO files are cached through Laravel's cache system.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- `ext-intl` (for ICU MessageFormat)

## Installation

```bash
composer require joelstein/laravel-t
php artisan vendor:publish --tag=t-config
```

The service provider is auto-discovered. Create your translation directory (default `lang/t/`) and add a PO file per locale.

## Usage

```php
// Plain strings
t('Hello, world!')

// Named placeholders
t('Hello, :name!', ['name' => $user->name])

// ICU plurals
t('{count, plural, one {# item} other {# items}}', ['count' => $total])

// Contexts (for homographs)
t('May', context: 'month')

// Inline markup via closures
t('Click <a>here</a>.', [
    'a' => fn ($text) => "<a href=\"/next\">{$text}</a>",
])

// Explicit locale override
t('Saved.', locale: 'es')
```

In Blade:

```blade
<h1>@t('Welcome back, :name!', ['name' => $user->name])</h1>
```

## Commands

```bash
# Scan source files and update PO files for every configured locale
php artisan t:extract

# List untranslated strings, optionally for one locale
php artisan t:untranslated
php artisan t:untranslated es

# Validate PO files (parse errors, placeholder mismatches, invalid ICU)
php artisan t:lint
php artisan t:lint es

# Clear the translation cache
php artisan t:clear
```

`t:extract` preserves existing translations, adds new entries, and marks strings that no longer exist in source as obsolete (`#~` entries in the PO file). Obsolete translations keep the translator's work so they snap back if the source string reappears. Run `t:extract --purge` to hard-delete obsolete entries instead. The source locale (default `en`) gets `msgstr = msgid` auto-populated.

## Fallback Chain

When a translation is missing, the lookup walks a fallback chain:

1. The requested locale (e.g. `es_MX`)
2. The base locale, if the requested one has a region suffix (`es_MX` → `es`)
3. The configured `fallback_locale`, if set

If nothing in the chain has the string, the original msgid is returned. ICU formatting always uses the originally requested locale, so plural rules and number formats still match the user's region even when the translation comes from a fallback.

## Configuration

`config/t.php`:

```php
return [
    'path' => lang_path('t'),
    'source_locale' => 'en',
    'locales' => ['en'],
    'fallback_locale' => null,
    'scan_paths' => ['app', 'resources/views'],
    'cache' => null,       // null = cache in production only
    'cache_ttl' => 86400,
    'log_missing' => null, // true, a channel name, or null to disable
];
```

## CI Integration

```yaml
- name: Check translations are extracted
  run: |
    php artisan t:extract
    git diff --exit-code lang/t
```

Combine with `t:untranslated` to fail on missing translations when that matters for your release.

## License

MIT
