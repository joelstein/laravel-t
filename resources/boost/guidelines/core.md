# Laravel T

Laravel T provides gettext PO-based translations using source strings as keys, with ICU MessageFormat support.

## Usage

- Use `t()` in PHP and `@t()` in Blade templates for all user-facing strings.
- Use `:param` placeholders for simple substitution: `t('Hello, :name!', ['name' => $user->name])`
- Use ICU MessageFormat for plurals: `t('{count, plural, one {# item} other {# items}}', ['count' => $total])`
- Use the `context` parameter to disambiguate identical strings: `t('May', context: 'month')`
- Use closure parameters for inline markup: `t('Click <a>here</a>.', ['a' => fn ($text) => "<a href=\"/next\">{$text}</a>"])`
- Translation files are stored as PO files in the directory configured in `config/t.php`.
- Run `php artisan t:extract` to scan source files and update PO files.
- Run `php artisan t:untranslated` to list untranslated strings by locale.
