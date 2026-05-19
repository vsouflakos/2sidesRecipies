<?php

namespace App\Console\Commands;

use App\Models\RecipeDraft;
use App\Support\Recipes\RecipeDraftManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recipes:recache-draft-metrics')]
#[Description('Recompute and persist cached metrics for every recipe draft')]
class RecacheDraftMetrics extends Command
{
    /**
     * Backfill recipe_drafts.cached_* columns so list cards reflect live metrics.
     *
     * Run once after deploying the draft-metrics cache; safe to re-run at any time.
     */
    public function handle(RecipeDraftManager $draftManager): int
    {
        $count = 0;

        RecipeDraft::query()->chunkById(100, function ($drafts) use ($draftManager, &$count): void {
            foreach ($drafts as $draft) {
                $draftManager->refreshMetricsCache($draft);
                $count++;
            }
        });

        $this->info("Recached metrics for {$count} recipe draft(s).");

        return self::SUCCESS;
    }
}
