<?php

declare(strict_types=1);

namespace JoelStein\LaravelT\Commands;

use Gettext\Translation;
use Gettext\Translations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use JoelStein\LaravelT\PoFile;
use JoelStein\LaravelT\StringExtractor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ExtractCommand extends Command
{
    protected $signature = 't:extract
                            {--purge : Hard-delete obsolete translations instead of preserving them as disabled (#~) entries}';

    protected $description = 'Extract translatable strings from source files to PO files';

    public function handle(StringExtractor $extractor): int
    {
        $this->info('Extracting translatable strings...');

        $outputPath = Config::string('t.path');

        if (! is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $extracted = $this->extractStrings($extractor);

        $this->info(sprintf('Found %d translatable strings.', count($extracted)));

        $sourceLocale = Config::string('t.source_locale', 'en');

        foreach (array_filter(Config::array('t.locales', ['en']), 'is_string') as $locale) {
            $this->updatePoFile($locale, $extracted, $outputPath, $sourceLocale);
        }

        $this->info('Extraction complete!');

        return self::SUCCESS;
    }

    /**
     * Extract all translatable strings from source files.
     */
    protected function extractStrings(StringExtractor $extractor): Translations
    {
        $translations = Translations::create('messages');
        $translations->getHeaders()->set('Content-Type', 'text/plain; charset=utf-8');
        $translations->getHeaders()->set('Language', Config::string('t.source_locale', 'en'));

        foreach (array_filter(Config::array('t.scan_paths', ['app', 'resources/views']), 'is_string') as $path) {
            $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
            if (! is_dir($fullPath)) {
                continue;
            }

            $this->scanDirectory($fullPath, $translations, $extractor);
        }

        return $translations;
    }

    /**
     * Recursively scan a directory for PHP files.
     */
    protected function scanDirectory(string $directory, Translations $translations, StringExtractor $extractor): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, '/vendor/') || str_contains($path, '/node_modules/')) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $relativePath = str_starts_with($path, base_path().'/')
                ? substr($path, strlen(base_path()) + 1)
                : $path;
            $extractor->extract($contents, $relativePath, $translations);
        }
    }

    /**
     * Update a PO file for a specific locale.
     */
    protected function updatePoFile(string $locale, Translations $extracted, string $outputPath, string $sourceLocale): void
    {
        $poFile = new PoFile("{$outputPath}/{$locale}.po");
        $existing = $poFile->load();
        $purge = (bool) $this->option('purge');

        $merged = Translations::create('messages');
        $merged->getHeaders()->set('Content-Type', 'text/plain; charset=utf-8');
        $merged->getHeaders()->set('Language', $locale);

        $newCount = 0;
        $obsoleteCount = 0;
        $purgedCount = 0;

        /** @var Translation $translation */
        foreach ($extracted as $translation) {
            $existingTranslation = $this->findTranslation($existing, $translation);

            if ($existingTranslation !== null) {
                // A previously-obsolete string has reappeared; un-disable it.
                if ($existingTranslation->isDisabled()) {
                    $existingTranslation->disable(false);
                }
                $merged->add($existingTranslation);
            } else {
                $newEntry = clone $translation;
                if ($locale === $sourceLocale) {
                    $newEntry->translate($translation->getOriginal());
                }
                $merged->add($newEntry);
                $newCount++;
            }
        }

        /** @var Translation $existingTrans */
        foreach ($existing as $existingTrans) {
            if ($this->findTranslation($extracted, $existingTrans) !== null) {
                continue;
            }

            $msgstr = $existingTrans->getTranslation();
            $hasTranslatorWork = $msgstr !== '' && $msgstr !== null;

            // Drop obsolete entries with no translator work, or when --purge.
            // Otherwise preserve them as disabled (#~) per gettext convention.
            if ($purge || ! $hasTranslatorWork) {
                $purgedCount++;

                continue;
            }

            $existingTrans->disable();
            $merged->add($existingTrans);
            $obsoleteCount++;
        }

        // Sort translations alphabetically by msgid
        $sorted = Translations::create('messages');
        $sorted->getHeaders()->set('Content-Type', 'text/plain; charset=utf-8');
        $sorted->getHeaders()->set('Language', $locale);

        $untranslatedCount = 0;

        /** @var array<int, Translation> $translations */
        $translations = iterator_to_array($merged);
        usort($translations, fn (Translation $a, Translation $b) => strcasecmp($a->getOriginal(), $b->getOriginal()));

        foreach ($translations as $translation) {
            $sorted->add($translation);

            if ($translation->isDisabled()) {
                continue;
            }

            $msgstr = $translation->getTranslation();
            if ($msgstr === '' || $msgstr === null) {
                $untranslatedCount++;
            }
        }

        $poFile->write($sorted);

        $segments = [];
        if ($newCount > 0) {
            $segments[] = "<comment>{$newCount} new</comment>";
        }
        if ($obsoleteCount > 0) {
            $segments[] = "<fg=yellow>{$obsoleteCount} obsolete</>";
        }
        if ($purgedCount > 0) {
            $segments[] = "<fg=red>{$purgedCount} removed</>";
        }
        if ($untranslatedCount > 0) {
            $segments[] = "<fg=yellow>{$untranslatedCount} untranslated</>";
        }

        $this->line(sprintf(
            '  <info>%s</info>: %d total%s',
            $locale,
            count($merged),
            $segments === [] ? '' : ', '.implode(', ', $segments),
        ));
    }

    /**
     * Find a matching translation in a collection.
     */
    protected function findTranslation(Translations $translations, Translation $needle): ?Translation
    {
        /** @var Translation $translation */
        foreach ($translations as $translation) {
            if (
                $translation->getOriginal() === $needle->getOriginal() &&
                $translation->getContext() === $needle->getContext()
            ) {
                return $translation;
            }
        }

        return null;
    }
}
