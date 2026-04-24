---
name: translate
description: "Translate untranslated strings in PO files. Activates when translating strings, working with PO files, running t:extract or t:untranslated, or when the user mentions translations, i18n, locales, or untranslated strings."
license: MIT
metadata:
  author: joelstein
---

# Translate Untranslated Strings

## When to Apply

Activate this skill when:

- Translating untranslated strings in PO files
- Running `t:extract` or `t:untranslated` commands
- Working with localization or i18n tasks
- The user asks about translation status or coverage

## Workflow

1. Run `php artisan t:extract` to extract strings from source files.

2. Run `php artisan t:untranslated` to see which strings need translation.

3. Work through ONE language at a time. For each language with untranslated strings:

   a. Read the PO file at the path configured in `config/t.php` (default: `lang/t/{locale}.po`)

   b. Find all entries where `msgstr` is empty

   c. Translate each `msgid` to the target language, preserving:
      - All placeholders (`:name`, `{param}`, etc.)
      - ICU plural syntax (`{count, plural, ...}`)
      - HTML entities if present

   d. Update the PO file with the translations using the Edit tool

   e. Run `php artisan t:untranslated {locale}` to verify the count decreased

4. After completing each language, move to the next one.

## Important Notes

- Do NOT translate the source locale file (typically `en`) — it is the source of truth
- Keep translations natural and idiomatic for each language
- For ICU plurals, translate the text inside the braces but keep the ICU syntax intact
- When in doubt about a translation, prefer clarity over literal translation
