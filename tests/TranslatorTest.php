<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JoelStein\LaravelT\Translator;

it('returns the original message when no translation exists', function () {
    app()->setLocale('en');

    expect(app(Translator::class)->translate('Hello'))->toBe('Hello');
});

it('translates a simple message', function () {
    app()->setLocale('es');

    expect(app(Translator::class)->translate('Hello'))->toBe('Hola');
});

it('replaces :param placeholders', function () {
    app()->setLocale('es');

    expect(app(Translator::class)->translate('Hello, :name!', ['name' => 'Joel']))->toBe('Hola, Joel!');
});

it('replaces multiple :param placeholders in one message', function () {
    app()->setLocale('en');

    expect(app(Translator::class)->translate(':greeting, :name!', ['greeting' => 'Hi', 'name' => 'Joel']))
        ->toBe('Hi, Joel!');
});

it('coerces stringable and null param values', function () {
    app()->setLocale('en');

    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'world';
        }
    };

    expect(app(Translator::class)->translate('Hello, :who!', ['who' => $stringable]))
        ->toBe('Hello, world!');
    expect(app(Translator::class)->translate('Count: :n', ['n' => 42]))
        ->toBe('Count: 42');
    expect(app(Translator::class)->translate('Empty: :x', ['x' => null]))
        ->toBe('Empty: ');
    expect(app(Translator::class)->translate('Skipped: :x', ['x' => ['array']]))
        ->toBe('Skipped: ');
});

it('supports contextual translations', function () {
    app()->setLocale('es');

    expect(app(Translator::class)->translate('May', context: 'month'))->toBe('Mayo');
});

it('formats ICU plural messages', function () {
    app()->setLocale('es');

    expect(app(Translator::class)->translate('{count, plural, one {# item} other {# items}}', ['count' => 1]))
        ->toBe('1 elemento');

    expect(app(Translator::class)->translate('{count, plural, one {# item} other {# items}}', ['count' => 5]))
        ->toBe('5 elementos');
});

it('handles closure-based tag parameters', function () {
    app()->setLocale('es');

    $result = app(Translator::class)->translate('Click <a>here</a> to continue.', [
        'a' => fn ($text) => "<a href=\"/next\">{$text}</a>",
    ]);

    expect($result)->toBe('Haz clic <a href="/next">aqui</a> para continuar.');
});

it('falls back to the original message for missing translations', function () {
    app()->setLocale('es');

    expect(app(Translator::class)->translate('This is not translated'))->toBe('This is not translated');
});

it('allows overriding the locale', function () {
    app()->setLocale('en');

    expect(app(Translator::class)->translate('Hello', locale: 'es'))->toBe('Hola');
});

it('returns an empty array for a missing locale file', function () {
    app()->setLocale('fr');

    expect(app(Translator::class)->translate('Hello'))->toBe('Hello');
});

it('caches loaded translations when caching is enabled', function () {
    Config::set('t.cache', true);
    app()->setLocale('es');

    $translator = new Translator;
    $translator->translate('Hello');

    $cached = Cache::get('laravel-t.es');
    expect($cached)->toBeArray();
    expect($cached['data'])->toHaveKey('Hello');
    expect($cached['data']['Hello'])->toBe('Hola');
});

it('invalidates the cache when the PO file mtime changes', function () {
    Config::set('t.cache', true);

    Cache::put('laravel-t.es', [
        'mtime' => 1,
        'data' => ['Hello' => 'STALE'],
    ], 60);

    app()->setLocale('es');
    $translator = new Translator;

    // Real file has a different mtime, so the stale entry should be ignored
    // and replaced with the freshly-loaded translations.
    expect($translator->translate('Hello'))->toBe('Hola');
});

it('clears both in-memory and persistent cache', function () {
    Config::set('t.cache', true);
    app()->setLocale('es');

    $translator = app(Translator::class);
    $translator->translate('Hello');
    expect(Cache::has('laravel-t.es'))->toBeTrue();

    $translator->clearCache();
    expect(Cache::has('laravel-t.es'))->toBeFalse();
});

it('logs missing translations when log_missing is enabled', function () {
    Config::set('t.log_missing', true);
    app()->setLocale('es');

    Log::shouldReceive('channel')->once()->with()->andReturnSelf();
    Log::shouldReceive('warning')->once()->with('Missing translation', Mockery::on(
        fn ($ctx) => $ctx['message'] === 'Not present'
            && $ctx['locale'] === 'es'
            && $ctx['context'] === null
    ));

    app(Translator::class)->translate('Not present');
});

it('deduplicates missing-translation logs within a request', function () {
    Config::set('t.log_missing', true);
    app()->setLocale('es');

    Log::shouldReceive('channel')->once()->andReturnSelf();
    Log::shouldReceive('warning')->once();

    $translator = app(Translator::class);
    $translator->translate('Same missing string');
    $translator->translate('Same missing string');
    $translator->translate('Same missing string');
});

it('does not log when log_missing is disabled', function () {
    Config::set('t.log_missing', null);
    app()->setLocale('es');

    Log::shouldReceive('channel')->never();
    Log::shouldReceive('warning')->never();

    app(Translator::class)->translate('Another missing string');
});

it('setPath clears in-memory cache and reloads from the new path', function () {
    app()->setLocale('es');
    $translator = app(Translator::class);

    expect($translator->translate('Hello'))->toBe('Hola');

    $translator->setPath('/nonexistent/path');

    expect($translator->translate('Hello'))->toBe('Hello');
});
