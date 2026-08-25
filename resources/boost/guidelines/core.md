# Laravel T

Laravel T provides gettext PO-based translations using source strings as keys, with ICU MessageFormat support.

## Usage

- Use `t()` in PHP and `@t()` in Blade templates for all user-facing strings.
- Use `:param` placeholders for simple substitution: `t('Hello, :name!', ['name' => $user->name])`
- Use ICU MessageFormat for plurals: `t('{count, plural, one {# item} other {# items}}', ['count' => $total])`
- Use the `context` parameter to disambiguate identical strings: `t('May', context: 'month')`
- Use closure parameters for inline markup: `t('Click <a>here</a>.', ['a' => fn ($text) => "<a href=\"/next\">{$text}</a>"])`
- Do NOT use Livewire's `#[Title('...')]` attribute — it cannot be translated. Set the title at render time instead: `$view->title(t('Dashboard'))` in a `rendering($view)` hook.
- Translation files are stored as PO files in the directory configured in `config/t.php`.
- Run `php artisan t:extract` to scan source files and update PO files.
- Run `php artisan t:untranslated` to list untranslated strings by locale.

@scoped(['resources/views/**'])
## Blade Views

- Never echo an ICU plural string with `{{ }}`. Blade reads the plural's closing `}}` as the end of the echo, so `{{ t('{count, plural, one {# item} other {# items}}', ['count' => $total]) }}` compiles to a truncated statement and the page dies with `Unclosed '(' does not match '}'` — pointing at the compiled view, not at the line that caused it.
- Pass the plural through a component attribute instead: `<x-badge :label="t('{count, plural, one {# item} other {# items}}', ['count' => $total])" />`. An attribute value is not a `{{ }}` echo, so the braces survive — this is why plurals work in component labels.
- When the string is standalone prose rather than an attribute, build it in a `#[Computed]` property (or any method) and echo the result: `{{ $this->itemCountLabel }}`.
- The `@t()` directive is safe either way — its arguments are matched by balanced parentheses, not by `}}`: `@t('{count, plural, one {# item} other {# items}}', ['count' => $total])`.
@endscoped
