<?php

namespace App\Services;

use App\Events\StageChanged;
use App\Models\CaseFile;
use App\Models\PipelineStage;
use App\Models\StageTransition;
use App\Models\User;

class StageChangeService
{
    public function change(CaseFile $case, PipelineStage $to, ?User $user = null, ?string $reason = null, int $chainDepth = 0): void
    {
        $from = $case->stage;

        if ($from?->id === $to->id) {
            return;
        }

        $case->update([
            'pipeline_stage_id' => $to->id,
            'closed_at' => $to->is_closed ? now() : null,
        ]);
        $case->touchActivity();

        StageTransition::query()->create([
            'case_id' => $case->id,
            'from_stage_id' => $from?->id,
            'to_stage_id' => $to->id,
            'user_id' => $user?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        event(new StageChanged($case->fresh('stage'), null, [
            'from_stage' => $from?->key,
            'to_stage' => $to->key,
            'chain_depth' => $chainDepth,
        ]));
    }
}
