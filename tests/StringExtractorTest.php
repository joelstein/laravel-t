<?php

use Gettext\Translations;
use JoelStein\LaravelT\StringExtractor;

it('extracts simple t() calls', function () {
    $translations = extractStrings("<?php t('Hello world');");

    expect($translations->find(null, 'Hello world'))->not->toBeNull();
});

it('extracts @t() calls in blade templates', function () {
    $translations = extractStrings("{{ @t('Welcome') }}");

    expect($translations->find(null, 'Welcome'))->not->toBeNull();
});

it('extracts t() calls with array parameters', function () {
    $translations = extractStrings("<?php t('Hello {name}', ['name' => \$name]);");

    expect($translations->find(null, 'Hello {name}'))->not->toBeNull();
});

it('extracts t() calls with positional context', function () {
    $translations = extractStrings("<?php t('Save', [], 'button');");

    $translation = $translations->find('button', 'Save');
    expect($translation)->not->toBeNull();
    expect($translation->getContext())->toBe('button');
});

it('extracts t() calls with named context argument', function () {
    $translations = extractStrings("<?php t('1st', context: 'week');");

    $translation = $translations->find('week', '1st');
    expect($translation)->not->toBeNull();
    expect($translation->getContext())->toBe('week');
});

it('extracts t() calls with array and named context', function () {
    $translations = extractStrings("<?php t('Page {num}', ['num' => 1], context: 'pagination');");

    $translation = $translations->find('pagination', 'Page {num}');
    expect($translation)->not->toBeNull();
    expect($translation->getContext())->toBe('pagination');
});

it('does not match function calls ending in t', function () {
    $translations = extractStrings("<?php format('Y-m-d'); sprint('test');");

    expect(iterator_count($translations))->toBe(0);
});

it('extracts strings with escaped quotes', function () {
    $translations = extractStrings("<?php t('It\\'s working');");

    expect($translations->find(null, "It's working"))->not->toBeNull();
});

it('extracts double-quoted strings', function () {
    $translations = extractStrings('<?php t("It\'s fine");');

    expect($translations->find(null, "It's fine"))->not->toBeNull();
});

it('extracts @t() with double-quoted strings', function () {
    $translations = extractStrings('@t("Greetings")');

    expect($translations->find(null, 'Greetings'))->not->toBeNull();
});

it('extracts ICU plural syntax', function () {
    $translations = extractStrings("<?php t('{count, plural, one {# hour} other {# hours}}', ['count' => \$minutes]);");

    expect($translations->find(null, '{count, plural, one {# hour} other {# hours}}'))->not->toBeNull();
});

it('extracts complex ICU strings with nested braces', function () {
    $translations = extractStrings("<?php t('{hour, plural, =1 {{day} at {time}} other {{day} at {time}}}', ['hour' => 1, 'day' => 'Monday', 'time' => '9am']);");

    expect($translations->find(null, '{hour, plural, =1 {{day} at {time}} other {{day} at {time}}}'))->not->toBeNull();
});

it('extracts t() with named context but no array parameter', function () {
    $translations = extractStrings("<?php t('All', context: 'options');");

    $translation = $translations->find('options', 'All');
    expect($translation)->not->toBeNull();
    expect($translation->getContext())->toBe('options');
});

it('records file and line references for each match', function () {
    $contents = "<?php\nt('First');\n\nt('Second');\n";
    $translations = Translations::create('messages');

    (new StringExtractor)->extract($contents, 'app/Example.php', $translations);

    expect($translations->find(null, 'First')->getReferences()->toArray())
        ->toBe(['app/Example.php' => [2]]);
    expect($translations->find(null, 'Second')->getReferences()->toArray())
        ->toBe(['app/Example.php' => [4]]);
});

it('merges references for the same string found multiple times', function () {
    $contents = "<?php\nt('Repeated');\nt('Repeated');\n";
    $translations = Translations::create('messages');

    (new StringExtractor)->extract($contents, 'app/Example.php', $translations);

    $translation = $translations->find(null, 'Repeated');
    expect($translation)->not->toBeNull();
    expect($translation->getReferences()->toArray())->toBe(['app/Example.php' => [2, 3]]);
});

/**
 * Helper to run the extractor against a string of source code.
 */
function extractStrings(string $contents): Translations
{
    $translations = Translations::create('messages');
    (new StringExtractor)->extract($contents, 'test.php', $translations);

    return $translations;
}
