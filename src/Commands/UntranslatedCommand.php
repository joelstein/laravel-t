<?php

declare(strict_types=1);

namespace JoelStein\LaravelT\Commands;

use Gettext\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use JoelStein\LaravelT\PoFile;

class UntranslatedCommand extends Command
{
    protected $signature = 't:untranslated
                            {locale? : The locale to list (e.g., es, fr). Lists all non-source locales if omitted.}';

    protected $description = 'List untranslated strings by locale';

    public function handle(): int
    {
        /** @var string|null $locale */
        $locale = $this->argument('locale');
        $sourceLocale = Config::string('t.source_locale', 'en');

        if ($locale !== null) {
            $locales = [$locale];
        } else {
            $locales = array_filter(
                array_filter(Config::array('t.locales', ['en']), 'is_string'),
                fn (string $l): bool => $l !== $sourceLocale
            );
        }

        if (empty($locales)) {
            $this->warn('No non-source locales configured in config/t.php.');

            return self::SUCCESS;
        }

        foreach ($locales as $l) {
            $this->listUntranslated($l);
        }

        return self::SUCCESS;
    }

    protected function listUntranslated(string $locale): void
    {
        $poFile = new PoFile(Config::string('t.path')."/{$locale}.po");

        if (! $poFile->exists()) {
            $this->warn("PO file not found: {$poFile->path}");

            return;
        }

        $untranslated = [];

        /** @var Translation $translation */
        foreach ($poFile->load() as $translation) {
            if ($translation->isDisabled()) {
                continue;
            }

            $msgstr = $translation->getTranslation();
            if ($msgstr === '' || $msgstr === null) {
                $untranslated[] = $translation->getOriginal();
            }
        }

        if (empty($untranslated)) {
            $this->info("<fg=green>{$locale}</> - All strings translated!");

            return;
        }

        $this->info("<fg=yellow>{$locale}</> - ".count($untranslated).' untranslated:');

        foreach ($untranslated as $string) {
            $display = strlen($string) > 80 ? substr($string, 0, 77).'...' : $string;
            $this->line("  - {$display}");
        }

        $this->newLine();
    }
}
