<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\HealthEvaluator;
use Illuminate\Console\Command;

class EvaluateSiteHealth extends Command
{
    protected $signature = 'cronshim:evaluate-health';

    protected $description = 'Open incidents for sites that have missed their expected run window';

    public function handle(HealthEvaluator $evaluator): int
    {
        $evaluated = 0;

        Site::query()
            ->where('is_active', true)
            ->chunkById(100, function ($sites) use ($evaluator, &$evaluated): void {
                foreach ($sites as $site) {
                    $evaluator->evaluateSite($site);
                    $evaluated++;
                }
            });

        $this->info("Evaluated {$evaluated} active site(s).");

        return self::SUCCESS;
    }
}
