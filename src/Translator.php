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
     * Load translations for a locale (with caching).
     *
     * @return array<string, string>
     */
    protected function loadTranslations(string $locale): array
    {
        if (isset($this->translations[$locale])) {
            return $this->translations[$locale];
        }

        $poFile = new PoFile("{$this->path}/{$locale}.po");
        $mtime = $poFile->mtime();
        $shouldCache = Config::get('t.cache') ?? App::environment('production');

        if ($shouldCache) {
            /** @var array{mtime: int, data: array<string, string>}|null $cached */
            $cached = Cache::get("laravel-t.{$locale}");

            if ($cached !== null && $cached['mtime'] === $mtime) {
                $this->translations[$locale] = $cached['data'];

                return $cached['data'];
            }
        }

        $data = $poFile->toLookup();
        $this->translations[$locale] = $data;

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
     * Clear the translation cache.
     */
    public function clearCache(): void
    {
        $this->translations = [];
        $this->missingReported = [];

        foreach (array_filter(Config::array('t.locales', ['en']), 'is_string') as $locale) {
            Cache::forget("laravel-t.{$locale}");
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
