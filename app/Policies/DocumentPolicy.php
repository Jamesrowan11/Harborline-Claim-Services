<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($user->isClient()) {
            return $this->clientOwns($user, $document) && $document->client_visible && $document->isSafeToServe();
        }

        return $user->can('documents.view');
    }

    public function download(User $user, Document $document): bool
    {
        if (! $document->isSafeToServe()) {
            return false;
        }
        if ($user->isClient()) {
            return $this->clientOwns($user, $document) && $document->client_visible && $document->status === 'approved';
        }

        return $user->can('documents.download');
    }

    public function review(User $user, Document $document): bool
    {
        return $user->isStaff() && $user->can('documents.approve');
    }

    private function clientOwns(User $user, Document $document): bool
    {
        return $user->claimant_id !== null
            && $document->case !== null
            && $document->case->claimants()->whereKey($user->claimant_id)->exists();
    }
}
