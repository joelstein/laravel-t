<?php

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->tempDir = $this->makeTempDir();
    Config::set('t.path', $this->tempDir);
    Config::set('t.locales', ['es']);
});

it('reports OK for clean PO files', function () {
    file_put_contents($this->tempDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "Hello, :name!"
        msgstr "Hola, :name!"

        msgid "{count, plural, one {# item} other {# items}}"
        msgstr "{count, plural, one {# elemento} other {# elementos}}"
        PO);

    $this->artisan('t:lint')
        ->expectsOutputToContain('OK')
        ->assertSuccessful();
});

it('flags placeholders missing from the translation', function () {
    file_put_contents($this->tempDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "Hello, :name! You have :count messages."
        msgstr "Hola!"
        PO);

    $this->artisan('t:lint')
        ->expectsOutputToContain('missing in translation: :name, :count')
        ->assertFailed();
});

it('flags invalid ICU syntax in translations', function () {
    file_put_contents($this->tempDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "{count, plural, one {# item} other {# items}}"
        msgstr "{count, plural, one {# elemento}"
        PO);

    $this->artisan('t:lint')
        ->expectsOutputToContain('Invalid ICU syntax')
        ->assertFailed();
});

it('warns when a PO file does not exist', function () {
    $this->artisan('t:lint')
        ->expectsOutputToContain('PO file not found')
        ->assertSuccessful();
});

it('lints a single locale when given as argument', function () {
    Config::set('t.locales', ['es', 'fr']);

    file_put_contents($this->tempDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "Hi"
        msgstr "Hola"
        PO);

    $this->artisan('t:lint es')
        ->expectsOutputToContain('es')
        ->doesntExpectOutputToContain('fr')
        ->assertSuccessful();
});
