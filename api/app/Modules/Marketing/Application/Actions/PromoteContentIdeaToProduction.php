<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Actions;

use App\Modules\Marketing\Domain\Enums\ContentIdeaStatus;
use App\Modules\Marketing\Domain\Enums\MarketingContentFormat;
use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingCampaign;
use App\Modules\Marketing\Domain\Models\MarketingProductionTask;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — transformer une idée en tâche de production (brief §B).
 *
 * L'opération est **idempotente** : une idée déjà reliée à une tâche n'en crée
 * pas une seconde. Et **non destructive** : le titre, le brief, l'objectif, la
 * verticale et les dates restent sur l'idée ; la tâche ne fait que les reprendre
 * pour l'équipe de production. C'est l'exigence explicite du brief —
 * « transformer une idée en tâche de production sans perdre les informations
 * déjà saisies ».
 *
 * La tâche appartient au module Marketing (cf. {@see MarketingProductionTask}:
 * `tasks.created_by` exige un employé, un acteur plateforme n'en a pas).
 */
class PromoteContentIdeaToProduction
{
    public function execute(ContentIdea $idea, ?int $actorId = null): ContentIdea
    {
        // Idempotent : une idée déjà reliée à une tâche n'en crée pas une seconde.
        if ($idea->production_task_id === null) {
            $campaign = $idea->campaign_id !== null
                ? MarketingCampaign::query()->find($idea->campaign_id)
                : null;

            $due = $idea->production_due_at ?? Carbon::now()->addWeek();

            $task = new MarketingProductionTask;
            $task->company_id = $idea->company_id;
            $task->content_idea_id = $idea->id;
            $task->title = $this->taskTitle($idea, $campaign);
            $task->description = $this->taskDescription($idea, $campaign);
            $task->due_date = $due->toDateString();
            $task->priority = $this->priorityFor($idea);
            $task->category = 'marketing';
            $task->status = 'todo';
            $task->created_by = $actorId;
            $task->save();

            $idea->production_task_id = $task->id;
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

        return mb_substr($title, 0, 200);
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

        if ($idea->sector !== null) {
            $lines[] = 'Verticale : '.$idea->sector;
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

        return $format instanceof MarketingContentFormat
            ? $format->label()
            : 'Non précisé';
    }

    private function priorityFor(ContentIdea $idea): string
    {
        if ($idea->production_due_at !== null && $idea->production_due_at->isPast()) {
            return 'high';
        }

        return 'normal';
    }
}
