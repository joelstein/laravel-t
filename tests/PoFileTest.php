<?php

use Gettext\Translation;
use Gettext\Translations;
use JoelStein\LaravelT\PoFile;

beforeEach(function () {
    $this->tempDir = $this->makeTempDir();
});

it('reports existence and mtime', function () {
    $path = $this->tempDir.'/es.po';
    $missing = new PoFile($path);

    expect($missing->exists())->toBeFalse();
    expect($missing->mtime())->toBe(0);

    file_put_contents($path, "msgid \"\"\nmsgstr \"\"\n");
    $present = new PoFile($path);

    expect($present->exists())->toBeTrue();
    expect($present->mtime())->toBeGreaterThan(0);
});

it('loads an empty Translations collection for missing files', function () {
    $file = new PoFile($this->tempDir.'/nope.po');

    expect(iterator_count($file->load()))->toBe(0);
});

it('skips disabled (obsolete) entries in the lookup map', function () {
    $path = $this->tempDir.'/es.po';
    file_put_contents($path, <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"

        msgid "Active"
        msgstr "Activo"

        #~ msgid "Retired"
        #~ msgstr "Retirado"
        PO);

    expect((new PoFile($path))->toLookup())->toBe(['Active' => 'Activo']);
});

it('flattens to a lookup map and skips empty msgstr', function () {
    $path = $this->tempDir.'/es.po';
    file_put_contents($path, <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"

        msgid "Hello"
        msgstr "Hola"

        msgctxt "month"
        msgid "May"
        msgstr "Mayo"

        msgid "Untranslated"
        msgstr ""
        PO);

    $lookup = (new PoFile($path))->toLookup();

    expect($lookup)->toBe([
        'Hello' => 'Hola',
        "month\x04May" => 'Mayo',
    ]);
});

it('writes Translations to disk', function () {
    $path = $this->tempDir.'/out.po';
    $translations = Translations::create('messages');
    $translations->add(Translation::create(null, 'Hi')->translate('Hola'));

    (new PoFile($path))->write($translations);

    expect(file_exists($path))->toBeTrue();
    expect(file_get_contents($path))->toContain('msgid "Hi"');
    expect(file_get_contents($path))->toContain('msgstr "Hola"');
});
