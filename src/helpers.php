<?php

declare(strict_types=1);

use JoelStein\LaravelT\Translator;

if (! function_exists('t')) {
    /**
     * Translate a message string.
     *
     * Examples:
     *   t('Hello, world!')
     *   t('Hello, :name!', ['name' => 'Joel'])
     *   t('May', context: 'month')
     *   t('{count, plural, one {# item} other {# items}}', ['count' => 5])
     *   t('Saved.', locale: 'es')
     *
     * @param  array<string, mixed>  $params
     */
    function t(string $message, array $params = [], ?string $context = null, ?string $locale = null): string
    {
        return app(Translator::class)->translate($message, $params, $context, $locale);
    }
}
