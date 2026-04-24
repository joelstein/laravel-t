<?php

use Illuminate\Support\Facades\Config;
use JoelStein\LaravelT\Translator;

it('prefers a regional locale when the translation exists there', function () {
    app()->setLocale('es_MX');

    expect(app(Translator::class)->translate('Hello'))->toBe('Qué onda');
});

it('falls back from a regional locale to its base locale', function () {
    app()->setLocale('es_MX');

    // "May" (with context "month") is only in es.po, not es_MX.po
    expect(app(Translator::class)->translate('May', context: 'month'))->toBe('Mayo');
});

it('uses the configured fallback_locale when neither the locale nor its base match', function () {
    Config::set('t.fallback_locale', 'es');
    app()->setLocale('fr');

    expect(app(Translator::class)->translate('Hello'))->toBe('Hola');
});

it('returns the original message when nothing in the chain has a translation', function () {
    Config::set('t.fallback_locale', 'es');
    app()->setLocale('fr');

    expect(app(Translator::class)->translate('Totally new string'))->toBe('Totally new string');
});

it('does not fall back when the requested locale already has the translation', function () {
    Config::set('t.fallback_locale', 'en');
    app()->setLocale('es');

    expect(app(Translator::class)->translate('Hello'))->toBe('Hola');
});

it('formats ICU messages using the originally requested locale even after fallback', function () {
    // Seed a plural translation only in the base locale; request the region
    // variant. The translation should be found in the base, but plural
    // selection should still use the region variant's rules.
    app()->setLocale('es_MX');

    expect(app(Translator::class)->translate('{count, plural, one {# item} other {# items}}', ['count' => 1]))
        ->toBe('1 elemento');
});
