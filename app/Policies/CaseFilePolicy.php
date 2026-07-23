<?php

namespace App\Policies;

use App\Models\CaseFile;
use App\Models\User;

class CaseFilePolicy
{
    public function view(User $user, CaseFile $case): bool
    {
        if ($user->isClient()) {
            // Clients may only ever see cases where their claimant record is attached.
            return $user->claimant_id !== null
                && $case->claimants()->whereKey($user->claimant_id)->exists();
        }

        return $user->can('cases.view');
    }

    public function update(User $user, CaseFile $case): bool
    {
        return $user->isStaff() && $user->can('cases.update');
    }

    public function changeStage(User $user, CaseFile $case): bool
    {
        return $user->isStaff() && $user->can('cases.change_stage');
    }

    public function delete(User $user, CaseFile $case): bool
    {
        return $user->isStaff() && $user->can('cases.delete') && ! $case->legal_hold;
    }
}
