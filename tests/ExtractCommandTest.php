<?php

use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->scanDir = $this->makeTempDir('laravel-t-scan-');
    $this->outputDir = $this->makeTempDir('laravel-t-out-');

    Config::set('t.path', $this->outputDir);
    Config::set('t.scan_paths', [$this->scanDir]);
    Config::set('t.source_locale', 'en');
    Config::set('t.locales', ['en', 'es']);
});

function loadPo(string $path): array
{
    $translations = (new PoLoader)->loadFile($path);
    $result = [];

    /** @var Translation $translation */
    foreach ($translations as $translation) {
        $result[$translation->getOriginal()] = [
            'msgstr' => $translation->getTranslation(),
            'context' => $translation->getContext(),
            'references' => $translation->getReferences()->toArray(),
            'disabled' => $translation->isDisabled(),
        ];
    }

    return $result;
}

it('creates a PO file for each configured locale', function () {
    file_put_contents($this->scanDir.'/Example.php', "<?php t('Hello world');");

    $this->artisan('t:extract')->assertSuccessful();

    expect(file_exists($this->outputDir.'/en.po'))->toBeTrue();
    expect(file_exists($this->outputDir.'/es.po'))->toBeTrue();
});

it('auto-populates msgstr = msgid for the source locale', function () {
    file_put_contents($this->scanDir.'/Example.php', "<?php t('Hello world');");

    $this->artisan('t:extract')->assertSuccessful();

    $en = loadPo($this->outputDir.'/en.po');
    expect($en['Hello world']['msgstr'])->toBe('Hello world');

    $es = loadPo($this->outputDir.'/es.po');
    expect($es['Hello world']['msgstr'])->toBe('');
});

it('preserves existing translations when re-extracting', function () {
    // Seed an existing es.po with a translation
    file_put_contents($this->outputDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "Hello world"
        msgstr "Hola mundo"
        PO);

    file_put_contents($this->scanDir.'/Example.php', "<?php t('Hello world'); t('New string');");

    $this->artisan('t:extract')->assertSuccessful();

    $es = loadPo($this->outputDir.'/es.po');
    expect($es['Hello world']['msgstr'])->toBe('Hola mundo');
    expect($es)->toHaveKey('New string');
    expect($es['New string']['msgstr'])->toBe('');
});

it('preserves obsolete translations as disabled entries by default', function () {
    // Seed es.po with a translated string that won't be re-extracted
    file_put_contents($this->outputDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "Old string"
        msgstr "Cadena antigua"
        PO);

    file_put_contents($this->scanDir.'/Example.php', "<?php t('Current string');");

    $this->artisan('t:extract')
        ->expectsOutputToContain('1 obsolete')
        ->assertSuccessful();

    $es = loadPo($this->outputDir.'/es.po');
    expect($es)->toHaveKey('Old string');
    expect($es['Old string']['disabled'])->toBeTrue();
    expect($es['Old string']['msgstr'])->toBe('Cadena antigua');
    expect($es)->toHaveKey('Current string');
    expect($es['Current string']['disabled'])->toBeFalse();
});

it('hard-deletes obsolete translations with --purge', function () {
    file_put_contents($this->outputDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "Old string"
        msgstr "Cadena antigua"
        PO);

    file_put_contents($this->scanDir.'/Example.php', "<?php t('Current string');");

    $this->artisan('t:extract --purge')
        ->expectsOutputToContain('1 removed')
        ->assertSuccessful();

    $es = loadPo($this->outputDir.'/es.po');
    expect($es)->not->toHaveKey('Old string');
    expect($es)->toHaveKey('Current string');
});

it('drops obsolete entries that have no translator work', function () {
    // An untranslated msgstr has nothing worth preserving.
    file_put_contents($this->outputDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        msgid "Old unused string"
        msgstr ""
        PO);

    file_put_contents($this->scanDir.'/Example.php', "<?php t('Current string');");

    $this->artisan('t:extract')->assertSuccessful();

    $es = loadPo($this->outputDir.'/es.po');
    expect($es)->not->toHaveKey('Old unused string');
});

it('re-enables a previously-obsolete entry when the string reappears', function () {
    // Seed with a disabled (obsolete) entry
    file_put_contents($this->outputDir.'/es.po', <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=utf-8\n"
        "Language: es\n"

        #~ msgid "Comeback string"
        #~ msgstr "Cadena que regresa"
        PO);

    file_put_contents($this->scanDir.'/Example.php', "<?php t('Comeback string');");

    $this->artisan('t:extract')->assertSuccessful();

    $es = loadPo($this->outputDir.'/es.po');
    expect($es)->toHaveKey('Comeback string');
    expect($es['Comeback string']['disabled'])->toBeFalse();
    expect($es['Comeback string']['msgstr'])->toBe('Cadena que regresa');
});

it('sorts translations alphabetically by msgid', function () {
    file_put_contents($this->scanDir.'/Example.php',
        "<?php t('Zebra'); t('Apple'); t('Mango');"
    );

    $this->artisan('t:extract')->assertSuccessful();

    $keys = array_keys(loadPo($this->outputDir.'/en.po'));
    expect($keys)->toBe(['Apple', 'Mango', 'Zebra']);
});

it('records file and line references', function () {
    file_put_contents($this->scanDir.'/Example.php', "<?php\nt('First');\n\n\nt('Second');\n");

    $this->artisan('t:extract')->assertSuccessful();

    $en = loadPo($this->outputDir.'/en.po');
    expect($en['First']['references'])->toHaveKey($this->scanDir.'/Example.php');
    expect($en['First']['references'][$this->scanDir.'/Example.php'])->toBe([2]);
    expect($en['Second']['references'][$this->scanDir.'/Example.php'])->toBe([5]);
});

it('scans subdirectories recursively', function () {
    mkdir($this->scanDir.'/sub');
    file_put_contents($this->scanDir.'/sub/Nested.php', "<?php t('Deep');");

    $this->artisan('t:extract')->assertSuccessful();

    expect(loadPo($this->outputDir.'/en.po'))->toHaveKey('Deep');
});

it('ignores non-PHP files and vendor directories', function () {
    file_put_contents($this->scanDir.'/Example.php', "<?php t('Kept');");
    file_put_contents($this->scanDir.'/notes.txt', "t('Ignored-txt')");

    mkdir($this->scanDir.'/vendor/pkg', 0755, true);
    file_put_contents($this->scanDir.'/vendor/pkg/Dep.php', "<?php t('Ignored-vendor');");

    $this->artisan('t:extract')->assertSuccessful();

    $en = loadPo($this->outputDir.'/en.po');
    expect($en)->toHaveKey('Kept');
    expect($en)->not->toHaveKey('Ignored-txt');
    expect($en)->not->toHaveKey('Ignored-vendor');
});
