<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

it('clears cached translations for every configured locale', function () {
    Cache::put('laravel-t.en', ['mtime' => 1, 'data' => []], 60);
    Cache::put('laravel-t.es', ['mtime' => 1, 'data' => []], 60);

    $this->artisan('t:clear')
        ->expectsOutputToContain('Translation cache cleared.')
        ->assertSuccessful();

    expect(Cache::has('laravel-t.en'))->toBeFalse();
    expect(Cache::has('laravel-t.es'))->toBeFalse();
});

it('only clears configured locales', function () {
    Config::set('t.locales', ['en']);
    Cache::put('laravel-t.en', ['mtime' => 1, 'data' => []], 60);
    Cache::put('laravel-t.fr', ['mtime' => 1, 'data' => []], 60);

    $this->artisan('t:clear')->assertSuccessful();

    expect(Cache::has('laravel-t.en'))->toBeFalse();
    expect(Cache::has('laravel-t.fr'))->toBeTrue();
});
