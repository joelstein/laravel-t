<?php

declare(strict_types=1);

namespace JoelStein\LaravelT;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use MessageFormatter;

class Translator
{
    /**
     * Cached translations keyed by locale.
     *
     * @var array<string, array<string, string>>
     */
    protected array $translations = [];

    /**
     * Missing translations already reported this request, to avoid log spam.
     *
     * @var array<string, true>
     */
    protected array $missingReported = [];

    /**
     * The base path for translation files.
     */
    protected string $path;

    public function __construct()
    {
        $this->path = Config::string('t.path');
    }

    /**
     * Translate a message.
     *
     * @param  array<string, mixed>  $params
     */
    public function translate(string $message, array $params = [], ?string $context = null, ?string $locale = null): string
    {
        $locale ??= App::getLocale();
        $translated = $this->lookup($message, $locale, $context);

        if ($translated === null) {
            $this->reportMissing($message, $locale, $context);
            $translated = $message;
        }

        // Handle ICU message formatting (plurals, select, etc.)
        if ($params !== [] && $this->isIcuMessage($translated)) {
            $formatted = MessageFormatter::formatMessage($locale, $translated, $params);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        // Handle closure-based component parameters like <a>text</a>
        foreach ($params as $key => $value) {
            if ($value instanceof \Closure) {
                $pattern = '/<'.preg_quote($key, '/').'>(.*?)<\/'.preg_quote($key, '/').'>/s';
                $translated = preg_replace_callback(
                    $pattern,
                    fn (array $matches): string => $this->toString($value($matches[1])),
                    $translated
                ) ?? $translated;
            }
        }

        // Simple parameter replacement for :param style (non-closure values only)
        foreach ($params as $key => $value) {
            if (! $value instanceof \Closure) {
                $translated = str_replace(":{$key}", $this->toString($value), $translated);
            }
        }

        return $translated;
    }

    /**
     * Coerce a scalar / Stringable / null value to string for interpolation.
     */
    protected function toString(mixed $value): string
    {
        if (is_scalar($value) || $value === null || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Look up a translation by message and optional context, walking the
     * locale fallback chain.
     */
    protected function lookup(string $message, string $locale, ?string $context = null): ?string
    {
        $key = $context !== null ? "{$context}\x04{$message}" : $message;

        foreach ($this->fallbackChain($locale) as $tryLocale) {
            $translations = $this->loadTranslations($tryLocale);
            if (isset($translations[$key])) {
                return $translations[$key];
            }
        }

        return null;
    }

    /**
     * Build the lookup chain for a locale. Regional locales fall back to
     * their base (e.g. "es_MX" -> "es"), and the configured fallback_locale
     * is consulted last.
     *
     * @return list<string>
     */
    protected function fallbackChain(string $locale): array
    {
        $chain = [$locale];

        if (str_contains($locale, '_')) {
            $base = substr($locale, 0, (int) strpos($locale, '_'));
            if (! in_array($base, $chain, true)) {
                $chain[] = $base;
            }
        }

        $fallback = Config::get('t.fallback_locale');
        if (is_string($fallback) && $fallback !== '' && ! in_array($fallback, $chain, true)) {
            $chain[] = $fallback;
        }

        return $chain;
    }

    /**
     * Load translations for a locale, preferring a compiled PHP file (see the
     * t:cache command) and falling back to Laravel's cache, then to parsing
     * the PO file directly.
     *
     * @return array<string, string>
     */
    protected function loadTranslations(string $locale): array
    {
        if (isset($this->translations[$locale])) {
            return $this->translations[$locale];
        }

        $data = $this->loadCompiled($locale) ?? $this->loadFromCache($locale);
        $this->translations[$locale] = $data;

        return $data;
    }

    /**
     * Load a locale from its compiled PHP file, if one is present and current.
     *
     * Compiled files are plain `return [...]` arrays, so OPcache holds them in
     * shared memory across every worker: no deserialization and no per-request
     * allocation. Returns null when there is no usable compiled file, leaving
     * the caller to fall back to the cache/parse path.
     *
     * @return array<string, string>|null
     */
    protected function loadCompiled(string $locale): ?array
    {
        $file = $this->compiledPath($locale);

        if (! is_file($file)) {
            return null;
        }

        /** @var mixed $compiled */
        $compiled = require $file;

        if (! is_array($compiled) || ! isset($compiled['source'], $compiled['mtime']) || ! isset($compiled['data']) || ! is_array($compiled['data'])) {
            return null;
        }

        // Guard against a compiled file built for a different translation
        // directory (see setPath()).
        if ($compiled['source'] !== $this->poPath($locale)) {
            return null;
        }

        // Outside production the PO file is the source of truth, so a compiled
        // file that predates an edit is discarded. In production the deploy is
        // the invalidation story, as with config:cache, and skipping the stat
        // keeps the lookup free.
        if (! App::environment('production') && $compiled['mtime'] !== (new PoFile($this->poPath($locale)))->mtime()) {
            return null;
        }

        /** @var array<string, string> $data */
        $data = $compiled['data'];

        return $data;
    }

    /**
     * Load a locale through Laravel's cache, parsing the PO file on a miss.
     *
     * @return array<string, string>
     */
    protected function loadFromCache(string $locale): array
    {
        $poFile = new PoFile($this->poPath($locale));
        $mtime = $poFile->mtime();
        $shouldCache = Config::get('t.cache') ?? App::environment('production');

        if ($shouldCache) {
            /** @var array{mtime: int, data: array<string, string>}|null $cached */
            $cached = Cache::get("laravel-t.{$locale}");

            if ($cached !== null && $cached['mtime'] === $mtime) {
                return $cached['data'];
            }
        }

        $data = $poFile->toLookup();

        if ($shouldCache) {
            Cache::put(
                "laravel-t.{$locale}",
                ['mtime' => $mtime, 'data' => $data],
                Config::integer('t.cache_ttl', 86400),
            );
        }

        return $data;
    }

    /**
     * The PO file path for a locale.
     */
    protected function poPath(string $locale): string
    {
        return "{$this->path}/{$locale}.po";
    }

    /**
     * The compiled PHP file path for a locale.
     */
    public function compiledPath(string $locale): string
    {
        return App::bootstrapPath('cache')."/t-{$locale}.php";
    }

    /**
     * Compile a locale's PO file to a plain PHP array file and return the
     * number of strings written. The file is written to a temporary name and
     * renamed into place so a concurrent request can never require a partially
     * written file.
     */
    public function compile(string $locale): int
    {
        $poFile = new PoFile($this->poPath($locale));
        $data = $poFile->toLookup();

        $contents = "<?php\n\n// Generated by the t:cache command. Do not edit.\n\nreturn ".var_export([
            'source' => $poFile->path,
            'mtime' => $poFile->mtime(),
            'data' => $data,
        ], true).";\n";

        $file = $this->compiledPath($locale);
        $directory = dirname($file);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temp = $file.'.'.getmypid().'.tmp';

        if (file_put_contents($temp, $contents) === false) {
            @unlink($temp);

            throw new \RuntimeException("Unable to write compiled translations for [{$locale}].");
        }

        rename($temp, $file);

        // Covers servers running with opcache.validate_timestamps=0, where a
        // rewritten file would otherwise be ignored until FPM restarts.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }

        return count($data);
    }

    /**
     * Check if a message uses ICU format (contains { } patterns).
     */
    protected function isIcuMessage(string $message): bool
    {
        return str_contains($message, '{') && str_contains($message, '}');
    }

    /**
     * Log a missing translation once per message+locale+context combination,
     * if logging is enabled via config.
     */
    protected function reportMissing(string $message, string $locale, ?string $context): void
    {
        $channel = Config::get('t.log_missing');

        if ($channel === null || $channel === false) {
            return;
        }

        $key = ($context ?? '').'|'.$locale.'|'.$message;
        if (isset($this->missingReported[$key])) {
            return;
        }
        $this->missingReported[$key] = true;

        $logger = is_string($channel) ? Log::channel($channel) : Log::channel();
        $logger->warning('Missing translation', [
            'message' => $message,
            'locale' => $locale,
            'context' => $context,
        ]);
    }

    /**
     * Clear the translation cache: in-memory state, Laravel's cache, and any
     * compiled PHP files.
     */
    public function clearCache(): void
    {
        $this->translations = [];
        $this->missingReported = [];

        foreach (array_filter(Config::array('t.locales', ['en']), 'is_string') as $locale) {
            Cache::forget("laravel-t.{$locale}");

            $file = $this->compiledPath($locale);

            if (is_file($file)) {
                unlink($file);

                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($file, true);
                }
            }
        }
    }

    /**
     * Set a custom path for translation files. Clears in-memory state;
     * the Laravel cache is unaffected.
     */
    public function setPath(string $path): static
    {
        $this->path = $path;
        $this->translations = [];

        return $this;
    }
}
