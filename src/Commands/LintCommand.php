<?php

declare(strict_types=1);

namespace JoelStein\LaravelT\Commands;

use Gettext\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use JoelStein\LaravelT\PoFile;
use MessageFormatter;
use Throwable;

class LintCommand extends Command
{
    protected $signature = 't:lint
                            {locale? : The locale to lint. Lints all configured locales if omitted.}';

    protected $description = 'Validate PO files for parse errors, placeholder mismatches, and ICU syntax errors';

    public function handle(): int
    {
        /** @var string|null $only */
        $only = $this->argument('locale');

        $locales = $only !== null
            ? [$only]
            : array_filter(Config::array('t.locales', ['en']), 'is_string');

        $totalErrors = 0;

        foreach ($locales as $locale) {
            $totalErrors += $this->lintLocale($locale);
        }

        if ($totalErrors > 0) {
            $this->error(sprintf('Found %d issue(s).', $totalErrors));

            return self::FAILURE;
        }

        $this->info('All PO files pass lint.');

        return self::SUCCESS;
    }

    protected function lintLocale(string $locale): int
    {
        $poFile = new PoFile(Config::string('t.path')."/{$locale}.po");

        if (! $poFile->exists()) {
            $this->warn("<fg=yellow>{$locale}</> - PO file not found: {$poFile->path}");

            return 0;
        }

        try {
            $translations = $poFile->load();
        } catch (Throwable $e) {
            $this->error("<fg=red>{$locale}</> - Failed to parse: {$e->getMessage()}");

            return 1;
        }

        $errors = [];

        /** @var Translation $translation */
        foreach ($translations as $translation) {
            if ($translation->isDisabled()) {
                continue;
            }

            $msgid = $translation->getOriginal();
            $msgstr = $translation->getTranslation();

            if ($msgstr === '' || $msgstr === null) {
                continue;
            }

            foreach ($this->checkPlaceholders($msgid, $msgstr) as $error) {
                $errors[] = $error;
            }

            foreach ($this->checkIcu($locale, $msgid, $msgstr) as $error) {
                $errors[] = $error;
            }
        }

        if ($errors === []) {
            $this->info("<fg=green>{$locale}</> - OK");

            return 0;
        }

        $this->warn("<fg=yellow>{$locale}</> - ".count($errors).' issue(s):');
        foreach ($errors as $error) {
            $this->line("  - {$error}");
        }
        $this->newLine();

        return count($errors);
    }

    /**
     * Verify that every :name placeholder in the msgid also appears in the
     * msgstr (missing placeholders break runtime rendering).
     *
     * @return list<string>
     */
    protected function checkPlaceholders(string $msgid, string $msgstr): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $msgid, $msgidMatches);
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $msgstr, $msgstrMatches);

        $missing = array_diff($msgidMatches[1], $msgstrMatches[1]);

        if ($missing === []) {
            return [];
        }

        return [sprintf(
            'Placeholder mismatch in "%s": missing in translation: %s',
            $this->truncate($msgid),
            implode(', ', array_map(fn ($p) => ":{$p}", $missing)),
        )];
    }

    /**
     * If the msgid looks like ICU (contains a `{name, ...}` pattern with a
     * type argument), validate that the msgstr parses as ICU too.
     *
     * @return list<string>
     */
    protected function checkIcu(string $locale, string $msgid, string $msgstr): array
    {
        if (! preg_match('/\{[A-Za-z_][A-Za-z0-9_]*\s*,/', $msgid)) {
            return [];
        }

        try {
            new MessageFormatter($locale, $msgstr);
        } catch (Throwable $e) {
            return [sprintf(
                'Invalid ICU syntax in translation of "%s": %s',
                $this->truncate($msgid),
                $e->getMessage(),
            )];
        }

        return [];
    }

    protected function truncate(string $s, int $length = 60): string
    {
        return strlen($s) > $length ? substr($s, 0, $length - 3).'...' : $s;
    }
}
