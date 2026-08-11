<?php

declare(strict_types=1);

namespace JoelStein\LaravelT;

use Illuminate\Support\Facades\Blade;
use JoelStein\LaravelT\Commands\CacheCommand;
use JoelStein\LaravelT\Commands\ClearCommand;
use JoelStein\LaravelT\Commands\ExtractCommand;
use JoelStein\LaravelT\Commands\LintCommand;
use JoelStein\LaravelT\Commands\UntranslatedCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('t')
            ->hasConfigFile()
            ->hasCommands([
                CacheCommand::class,
                ClearCommand::class,
                ExtractCommand::class,
                LintCommand::class,
                UntranslatedCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Translator::class);
    }

    public function packageBooted(): void
    {
        Blade::directive('t', function (string $expression) {
            return "<?php echo t({$expression}); ?>";
        });
    }
}
