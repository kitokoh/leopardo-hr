<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Actions;

use App\Modules\Marketing\Domain\Enums\ContentIdeaStatus;
use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingCampaign;
use App\Shared\Contracts\Marketing\ContentProductionTaskSpawner;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — transformer une idée en tâche de production (brief §B).
 *
 * L'idée est PASSÉE en production et reliée à une tâche Planning via le
 * contrat partagé {@see ContentProductionTaskSpawner} : aucun import
 * croisé de module, et aucune information déjà saisie n'est perdue (le
 * titre, le brief, l'objectif et la date restent sur l'idée ; la tâche les
 * reprend).
 */
class PromoteContentIdeaToProduction
{
    public function __construct(private readonly ContentProductionTaskSpawner $tasks) {}

    public function execute(ContentIdea $idea, ?int $actorId = null): ContentIdea
    {
        // Idempotent : une idée déjà reliée à une tâche n'en crée pas une seconde.
        if ($idea->production_task_id === null) {
            $campaign = $idea->campaign_id !== null
                ? MarketingCampaign::query()->find($idea->campaign_id)
                : null;

            $due = $idea->production_due_at ?? Carbon::now()->addWeek();

            $taskId = $this->tasks->spawn([
                'title' => $this->taskTitle($idea, $campaign),
                'description' => $this->taskDescription($idea, $campaign),
                'due_date' => $due->toDateString(),
                'priority' => $this->priorityFor($idea),
                'category' => 'marketing',
                'created_by' => $actorId,
            ]);

            if ($taskId !== null) {
                $idea->production_task_id = $taskId;
            }
        }

        if ($idea->status === ContentIdeaStatus::Idea || $idea->status === ContentIdeaStatus::Briefing) {
            $idea->status = ContentIdeaStatus::InProduction;
        }

        $idea->save();

        return $idea;
    }

    private function taskTitle(ContentIdea $idea, ?MarketingCampaign $campaign): string
    {
        $title = 'Production marketing : '.$idea->title;

        if ($campaign !== null) {
            $title .= ' ['.$campaign->name.']';
        }

        return mb_substr($title, 0, 240);
    }

    private function taskDescription(ContentIdea $idea, ?MarketingCampaign $campaign): string
    {
        $lines = [
            'Objectif : '.($idea->objective ?? 'non précisé'),
            'Format : '.$this->formatLabel($idea),
            'Langue : '.$idea->locale,
        ];

        if ($campaign !== null) {
            $lines[] = 'Campagne : '.$campaign->name;
        }

        if ($idea->product !== null) {
            $lines[] = 'Produit : '.$idea->product;
        }

        if ($idea->audience !== null) {
            $lines[] = 'Public visé : '.$idea->audience;
        }

        if ($idea->key_message !== null) {
            $lines[] = 'Message principal : '.$idea->key_message;
        }

        if ($idea->description !== null) {
            $lines[] = '';
            $lines[] = $idea->description;
        }

        if ($idea->publish_due_at !== null) {
            $lines[] = '';
            $lines[] = 'Publication souhaitée le '.$idea->publish_due_at->toDateString().'.';
        }

        return implode("\n", $lines);
    }

    private function formatLabel(ContentIdea $idea): string
    {
        $format = $idea->format;

        return $format instanceof \App\Modules\Marketing\Domain\Enums\MarketingContentFormat
            ? $format->label()
            : 'Non précisé';
    }

    private function priorityFor(ContentIdea $idea): string
    {
        if ($idea->production_due_at !== null && $idea->production_due_at->isPast()) {
            return 'high';
        }

        return 'medium';
    }
}
