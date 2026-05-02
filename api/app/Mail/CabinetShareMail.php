<?php

namespace App\Mail;

use App\Models\CabinetDocument;
use App\Models\CabinetFolder;
use App\Models\CabinetShare;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CabinetShareMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly string $shareUrl;

    public readonly string $shareableName;

    public readonly string $shareableType;

    public readonly string $ownerName;

    public function __construct(public readonly CabinetShare $share)
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $this->shareUrl = $frontendUrl.'/cabinet/shared/'.$share->share_token;
        $this->shareableName = $share->shareable?->name ?? '';
        $this->ownerName = trim(($share->employee?->first_name ?? '').' '.($share->employee?->last_name ?? ''));

        $this->shareableType = match (true) {
            $share->shareable instanceof CabinetFolder => 'folder',
            $share->shareable instanceof CabinetDocument => 'document',
            default => 'item',
        };
    }

    public function build(): self
    {
        return $this
            ->subject(__('cabinet.share_email_subject', [
                'name' => $this->ownerName,
                'item' => $this->shareableName,
            ]))
            ->view('emails.cabinet-share');
    }
}
