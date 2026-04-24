<?php

declare(strict_types=1);

namespace JoelStein\LaravelT;

use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations;

class PoFile
{
    public function __construct(public readonly string $path) {}

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    /**
     * File modification time in unix seconds, or 0 if the file does not exist.
     */
    public function mtime(): int
    {
        if (! $this->exists()) {
            return 0;
        }

        $mtime = filemtime($this->path);

        return $mtime === false ? 0 : $mtime;
    }

    /**
     * Load the PO file as a Gettext Translations collection.
     * Returns an empty collection if the file does not exist.
     */
    public function load(): Translations
    {
        if (! $this->exists()) {
            return Translations::create('messages');
        }

        return (new PoLoader)->loadFile($this->path);
    }

    /**
     * Load the PO file as a msgid => msgstr lookup map, suitable for runtime
     * translation lookups. Entries with empty msgstr are skipped. Entries
     * with a context are keyed as "{context}\x04{msgid}".
     *
     * @return array<string, string>
     */
    public function toLookup(): array
    {
        $result = [];

        /** @var Translation $translation */
        foreach ($this->load() as $translation) {
            if ($translation->isDisabled()) {
                continue;
            }

            $msgstr = $translation->getTranslation();

            if ($msgstr === '' || $msgstr === null) {
                continue;
            }

            $msgid = $translation->getOriginal();
            $context = $translation->getContext();
            $key = $context !== null ? "{$context}\x04{$msgid}" : $msgid;
            $result[$key] = $msgstr;
        }

        return $result;
    }

    public function write(Translations $translations): void
    {
        (new PoGenerator)->generateFile($translations, $this->path);
    }
}
