<?php

declare(strict_types=1);

namespace JoelStein\LaravelT\Commands;

use Illuminate\Console\Command;
use JoelStein\LaravelT\Translator;

class ClearCommand extends Command
{
    protected $signature = 't:clear';

    protected $description = 'Clear the translation cache';

    public function handle(Translator $translator): int
    {
        $translator->clearCache();

        $this->info('Translation cache cleared.');

        return self::SUCCESS;
    }
}
