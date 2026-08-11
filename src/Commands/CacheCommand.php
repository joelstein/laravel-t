<?php

declare(strict_types=1);

namespace JoelStein\LaravelT\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use JoelStein\LaravelT\PoFile;
use JoelStein\LaravelT\Translator;

class CacheCommand extends Command
{
    protected $signature = 't:cache';

    protected $description = 'Compile PO files into PHP arrays for fast production loading';

    public function handle(Translator $translator): int
    {
        $translator->clearCache();

        $path = Config::string('t.path');
        $compiled = 0;

        foreach (array_filter(Config::array('t.locales', ['en']), 'is_string') as $locale) {
            if (! (new PoFile("{$path}/{$locale}.po"))->exists()) {
                $this->line("  <fg=yellow>{$locale}</>: no PO file, skipped");

                continue;
            }

            $count = $translator->compile($locale);
            $compiled++;

            $this->line(sprintf(
                '  <info>%s</info>: %d strings -> %s',
                $locale,
                $count,
                $translator->compiledPath($locale),
            ));
        }

        if ($compiled === 0) {
            $this->warn('No PO files found to compile.');

            return self::SUCCESS;
        }

        $this->info('Translations cached.');

        return self::SUCCESS;
    }
}
