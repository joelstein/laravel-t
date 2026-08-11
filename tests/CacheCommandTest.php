<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use JoelStein\LaravelT\Translator;

/**
 * Remove any compiled files left behind so tests never leak into each other.
 */
afterEach(function () {
    foreach (['en', 'es', 'es_MX', 'fr'] as $locale) {
        $file = app(Translator::class)->compiledPath($locale);
        if (is_file($file)) {
            unlink($file);
        }
    }
});

it('compiles a PHP array file for every configured locale', function () {
    Config::set('t.locales', ['es', 'es_MX']);
    $translator = app(Translator::class);

    $this->artisan('t:cache')
        ->expectsOutputToContain('Translations cached.')
        ->assertSuccessful();

    expect(is_file($translator->compiledPath('es')))->toBeTrue();
    expect(is_file($translator->compiledPath('es_MX')))->toBeTrue();

    $compiled = require $translator->compiledPath('es');

    expect($compiled)->toHaveKeys(['source', 'mtime', 'data']);
    expect($compiled['source'])->toBe(__DIR__.'/fixtures/lang/es.po');
    expect($compiled['data']['Hello'])->toBe('Hola');
});

it('skips locales that have no PO file', function () {
    Config::set('t.locales', ['es', 'fr']);
    $translator = app(Translator::class);

    $this->artisan('t:cache')
        ->expectsOutputToContain('no PO file, skipped')
        ->assertSuccessful();

    expect(is_file($translator->compiledPath('es')))->toBeTrue();
    expect(is_file($translator->compiledPath('fr')))->toBeFalse();
});

it('omits untranslated strings from the compiled file', function () {
    $this->artisan('t:cache')->assertSuccessful();

    $compiled = require app(Translator::class)->compiledPath('es');

    expect($compiled['data'])->not->toHaveKey('Untranslated string');
});

it('preserves context-keyed entries through compilation', function () {
    $this->artisan('t:cache')->assertSuccessful();

    $compiled = require app(Translator::class)->compiledPath('es');

    expect($compiled['data']["month\x04May"])->toBe('Mayo');
});

it('translates from the compiled file rather than the PO file', function () {
    $translator = app(Translator::class);
    $this->artisan('t:cache')->assertSuccessful();

    // Rewrite the compiled file with a value that appears in no PO file. If the
    // translator reads it, the compiled file is genuinely being used.
    $file = $translator->compiledPath('es');
    $compiled = require $file;
    $compiled['data']['Hello'] = 'Compiled hello';
    file_put_contents($file, "<?php\n\nreturn ".var_export($compiled, true).";\n");

    app()->setLocale('es');

    expect(app(Translator::class)->translate('Hello'))->toBe('Compiled hello');
});

it('ignores a compiled file whose PO file has since changed', function () {
    $dir = $this->makeTempDir();
    copy(__DIR__.'/fixtures/lang/es.po', "{$dir}/es.po");
    Config::set('t.path', $dir);
    Config::set('t.locales', ['es']);

    $this->artisan('t:cache')->assertSuccessful();

    // Touch the PO file into the future so its mtime no longer matches.
    touch("{$dir}/es.po", time() + 10);

    app()->setLocale('es');
    app()->forgetInstance(Translator::class);

    // Falls back to parsing the PO file, which still has the original value.
    expect(app(Translator::class)->translate('Hello'))->toBe('Hola');
});

it('ignores a compiled file built for a different translation directory', function () {
    $translator = app(Translator::class);
    $this->artisan('t:cache')->assertSuccessful();

    $file = $translator->compiledPath('es');
    $compiled = require $file;
    $compiled['source'] = '/some/other/path/es.po';
    $compiled['data']['Hello'] = 'Wrong directory';
    file_put_contents($file, "<?php\n\nreturn ".var_export($compiled, true).";\n");

    app()->setLocale('es');

    expect(app(Translator::class)->translate('Hello'))->toBe('Hola');
});

it('falls back to the PO file when the compiled file is malformed', function () {
    $translator = app(Translator::class);
    $this->artisan('t:cache')->assertSuccessful();

    file_put_contents($translator->compiledPath('es'), "<?php\n\nreturn 'not an array';\n");

    app()->setLocale('es');

    expect(app(Translator::class)->translate('Hello'))->toBe('Hola');
});

it('clears compiled files alongside the cache', function () {
    $translator = app(Translator::class);
    Cache::put('laravel-t.es', ['mtime' => 1, 'data' => []], 60);

    $this->artisan('t:cache')->assertSuccessful();
    expect(is_file($translator->compiledPath('es')))->toBeTrue();

    $this->artisan('t:clear')
        ->expectsOutputToContain('Translation cache cleared.')
        ->assertSuccessful();

    expect(is_file($translator->compiledPath('es')))->toBeFalse();
    expect(Cache::has('laravel-t.es'))->toBeFalse();
});

it('recompiles cleanly over an existing compiled file', function () {
    $translator = app(Translator::class);

    $this->artisan('t:cache')->assertSuccessful();
    $this->artisan('t:cache')->assertSuccessful();

    $compiled = require $translator->compiledPath('es');

    expect($compiled['data']['Hello'])->toBe('Hola');
    expect(glob(dirname($translator->compiledPath('es')).'/*.tmp'))->toBe([]);
});
