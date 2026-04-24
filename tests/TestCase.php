<?php

declare(strict_types=1);

namespace JoelStein\LaravelT\Tests;

use JoelStein\LaravelT\LaravelTServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function getPackageProviders($app): array
    {
        return [
            LaravelTServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('t.path', __DIR__.'/fixtures/lang');
        $app['config']->set('t.source_locale', 'en');
        $app['config']->set('t.locales', ['en', 'es']);
        $app['config']->set('t.cache', false);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->deleteDirectory($dir);
        }
        $this->tempDirs = [];

        parent::tearDown();
    }

    /**
     * Create a throwaway directory, auto-removed in tearDown.
     */
    protected function makeTempDir(string $prefix = 'laravel-t-'): string
    {
        $dir = sys_get_temp_dir().'/'.uniqid($prefix, true);
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
