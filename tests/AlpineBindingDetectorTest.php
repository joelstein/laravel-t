<?php

use JoelStein\LaravelT\AlpineBindingDetector;

function detectBindings(string $contents): array
{
    return (new AlpineBindingDetector)->detect($contents);
}

it('flags t() in a colon binding on a plain element', function () {
    expect(detectBindings(<<<'BLADE'
        <div wire:sort:handle :aria-label="t('Hello')"></div>
        BLADE))->toBe([
        ['line' => 1, 'tag' => 'div', 'attribute' => ':aria-label'],
    ]);
});

it('flags t() in an x-bind binding on a plain element', function () {
    expect(detectBindings(<<<'BLADE'
        <button x-bind:title="t('Save')">Save</button>
        BLADE))->toBe([
        ['line' => 1, 'tag' => 'button', 'attribute' => 'x-bind:title'],
    ]);
});

it('flags @t() in a colon binding on a plain element', function () {
    expect(detectBindings(<<<'BLADE'
        <img :alt="@t('Welcome')">
        BLADE))->toBe([
        ['line' => 1, 'tag' => 'img', 'attribute' => ':alt'],
    ]);
});

it('flags bindings in tags spread across multiple lines', function () {
    expect(detectBindings(<<<'BLADE'
        <p>Hello</p>
        <span
            class="icon"
            x-on:click="open = count > 1"
            :title="t('Welcome')"
        >
        </span>
        BLADE))->toBe([
        ['line' => 5, 'tag' => 'span', 'attribute' => ':title'],
    ]);
});

it('allows t() bindings on component tags', function () {
    expect(detectBindings(<<<'BLADE'
        <flux:input :label="t('Name')" />
        <x-foo :title="t('Hello')" />
        <x:foo :title="t('Hello')" />
        <livewire:foo :title="t('Hello')" />
        BLADE))->toBe([]);
});

it('allows t() echoes in plain element attributes', function () {
    expect(detectBindings(<<<'BLADE'
        <div aria-label="{{ t('Hello') }}">@t('Welcome')</div>
        BLADE))->toBe([]);
});

it('allows bindings that do not call t()', function () {
    expect(detectBindings(<<<'BLADE'
        <div :class="format('x')" :title="$t('Hello')" x-text="t('Hello')"></div>
        BLADE))->toBe([]);
});
