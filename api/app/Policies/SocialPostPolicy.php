<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Marketing\Domain\Models\SocialPost;

/**
 * Acces restreint aux managers `principal` ou `marketing`, suivant le
 * pattern TrainingPolicy (hasManagerRole).
 */
class SocialPostPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function view(Employee $actor, SocialPost $post): bool
    {
        return $actor->company_id === $post->company_id
            && $actor->hasManagerRole('principal', 'marketing');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function update(Employee $actor, SocialPost $post): bool
    {
        return $actor->company_id === $post->company_id
            && $actor->hasManagerRole('principal', 'marketing')
            && in_array($post->status, [SocialPost::STATUS_DRAFT, SocialPost::STATUS_SCHEDULED], true);
    }

    public function delete(Employee $actor, SocialPost $post): bool
    {
        return $actor->company_id === $post->company_id
            && $actor->hasManagerRole('principal', 'marketing')
            && in_array($post->status, [SocialPost::STATUS_DRAFT, SocialPost::STATUS_SCHEDULED], true);
    }

    public function publish(Employee $actor, SocialPost $post): bool
    {
        return $actor->company_id === $post->company_id
            && $actor->hasManagerRole('principal', 'marketing')
            && $post->status !== SocialPost::STATUS_PUBLISHED;
    }
}
