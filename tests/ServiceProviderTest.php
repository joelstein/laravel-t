<?php

use Illuminate\Support\Facades\Blade;
use JoelStein\LaravelT\Translator;

it('binds Translator as a singleton', function () {
    expect(app(Translator::class))->toBe(app(Translator::class));
});

it('registers the t() helper', function () {
    app()->setLocale('es');

    expect(t('Hello'))->toBe('Hola');
});

it('registers the @t Blade directive', function () {
    app()->setLocale('es');

    $compiled = Blade::compileString("@t('Hello')");

    expect($compiled)->toBe("<?php echo t('Hello'); ?>");
});

it('renders translated output via the @t Blade directive', function () {
    app()->setLocale('es');

    $rendered = Blade::render("@t('Hello, :name!', ['name' => \$name])", ['name' => 'Joel']);

    expect($rendered)->toBe('Hola, Joel!');
});

it('registers the package console commands', function () {
    $commands = array_keys(app('Illuminate\Contracts\Console\Kernel')->all());

    expect($commands)->toContain('t:extract');
    expect($commands)->toContain('t:untranslated');
    expect($commands)->toContain('t:clear');
    expect($commands)->toContain('t:lint');
});
