<?php

declare(strict_types=1);

namespace JoelStein\LaravelT;

use Gettext\Translation;
use Gettext\Translations;

class StringExtractor
{
    /**
     * Matches t('string') and @t('string') with optional array params and
     * optional context (positional or named argument syntax).
     *
     * The (?<![a-zA-Z]) guard prevents matching identifiers that end in t,
     * e.g. format('...') or sprint('...').
     */
    private const PATTERN = '/(?<![a-zA-Z])@?t\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")(?:\s*,\s*\[[^\]]*\])?(?:\s*,\s*(?:context:\s*)?(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"))?\s*\)/';

    /**
     * Find t()/@t() calls in $contents and add them to $translations,
     * recording a reference to $filename and line number for each match.
     */
    public function extract(string $contents, string $filename, Translations $translations): void
    {
        if (! preg_match_all(self::PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches[1] as $index => $match) {
            $message = $match[0] !== '' ? $match[0] : $matches[2][$index][0];
            $message = stripslashes($message);

            $context = null;
            if (isset($matches[3][$index][0]) && $matches[3][$index][0] !== '') {
                $context = stripslashes($matches[3][$index][0]);
            } elseif (isset($matches[4][$index][0]) && $matches[4][$index][0] !== '') {
                $context = stripslashes($matches[4][$index][0]);
            }

            $offset = $match[1] !== -1 ? $match[1] : $matches[2][$index][1];
            $line = substr_count(substr($contents, 0, (int) $offset), "\n") + 1;

            $translation = $translations->find($context, $message);
            if ($translation === null) {
                $translation = Translation::create($context, $message);
                $translations->add($translation);
            }

            $translation->getReferences()->add($filename, $line);
        }
    }
}
