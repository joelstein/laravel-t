<?php

use Illuminate\Support\Facades\Config;

it('lists untranslated strings for all non-source locales by default', function () {
    $this->artisan('t:untranslated')
        ->expectsOutputToContain('es')
        ->expectsOutputToContain('Untranslated string')
        ->assertSuccessful();
});

it('lists untranslated strings for a specific locale when given', function () {
    $this->artisan('t:untranslated es')
        ->expectsOutputToContain('Untranslated string')
        ->assertSuccessful();
});

it('warns when the PO file is missing', function () {
    $this->artisan('t:untranslated fr')
        ->expectsOutputToContain('PO file not found')
        ->assertSuccessful();
});

it('warns when no non-source locales are configured', function () {
    Config::set('t.locales', ['en']);

    $this->artisan('t:untranslated')
        ->expectsOutputToContain('No non-source locales configured')
        ->assertSuccessful();
});

it('reports when all strings are translated', function (string $fixtureContent) {
    $tempDir = $this->makeTempDir();
    file_put_contents($tempDir.'/es.po', $fixtureContent);
    Config::set('t.path', $tempDir);

    $this->artisan('t:untranslated es')
        ->expectsOutputToContain('All strings translated')
        ->assertSuccessful();
})->with([
    ['msgid ""
msgstr ""
"Content-Type: text/plain; charset=utf-8\n"
"Language: es\n"

msgid "Hello"
msgstr "Hola"
'],
]);
