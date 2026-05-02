<?php

namespace App\Mail;

use App\Models\CabinetDocument;
use App\Models\CabinetFolder;
use App\Models\CabinetShare;
use App\Models\Employee;
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
        $frontendUrl = rtrim((string) config('app.frontend_url', (string) config('app.url')), '/');
        $this->shareUrl = $frontendUrl.'/cabinet/shared/'.$share->share_token;

        $shareable = $share->shareable;
        $this->shareableName = ($shareable instanceof CabinetFolder || $shareable instanceof CabinetDocument)
            ? $shareable->name
            : '';

        $this->shareableType = match (true) {
            $shareable instanceof CabinetFolder => 'folder',
            $shareable instanceof CabinetDocument => 'document',
            default => 'item',
        };

        $employee = $share->employee;
        $firstName = $employee instanceof Employee ? ($employee->first_name ?? '') : '';
        $lastName = $employee instanceof Employee ? ($employee->last_name ?? '') : '';
        $this->ownerName = trim($firstName.' '.$lastName);
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
