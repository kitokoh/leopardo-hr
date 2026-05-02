<?php

namespace App\Services;

use App\Mail\CabinetShareMail;
use App\Models\CabinetDocument;
use App\Models\CabinetFolder;
use App\Models\CabinetShare;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CabinetService
{
    // ── Folders ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFolder(Employee $owner, array $data): CabinetFolder
    {
        return CabinetFolder::create([
            'company_id' => $owner->company_id,
            'employee_id' => $owner->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFolder(CabinetFolder $folder, array $data): CabinetFolder
    {
        $fields = [];

        if (array_key_exists('name', $data)) {
            $fields['name'] = $data['name'];
        }
        if (array_key_exists('parent_id', $data)) {
            $fields['parent_id'] = $data['parent_id'];
        }
        if (array_key_exists('color', $data)) {
            $fields['color'] = $data['color'];
        }
        if (array_key_exists('icon', $data)) {
            $fields['icon'] = $data['icon'];
        }

        $folder->update($fields);
        $folder->refresh();

        return $folder;
    }

    public function deleteFolder(CabinetFolder $folder): void
    {
        $this->deleteFolderDocumentFiles($folder);
        $folder->delete();
    }

    // ── Documents ────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function uploadDocument(Employee $owner, UploadedFile $file, array $data): CabinetDocument
    {
        $storagePath = sprintf('cabinet/%d/%d', $owner->company_id, $owner->id);
        $path = $file->store($storagePath, 'local');

        return CabinetDocument::create([
            'company_id' => $owner->company_id,
            'employee_id' => $owner->id,
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $data['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'disk' => 'local',
            'path' => $path,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDocument(CabinetDocument $document, array $data): CabinetDocument
    {
        $fields = [];

        if (array_key_exists('name', $data)) {
            $fields['name'] = $data['name'];
        }
        if (array_key_exists('folder_id', $data)) {
            $fields['folder_id'] = $data['folder_id'];
        }
        if (array_key_exists('notes', $data)) {
            $fields['notes'] = $data['notes'];
        }

        $document->update($fields);
        $document->refresh();

        return $document;
    }

    public function deleteDocument(CabinetDocument $document): void
    {
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
    }

    public function moveDocument(CabinetDocument $document, ?int $folderId): CabinetDocument
    {
        $document->update(['folder_id' => $folderId]);
        $document->refresh();

        return $document;
    }

    // ── Sharing ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function share(Employee $owner, string $shareableType, int $shareableId, array $data): CabinetShare
    {
        $share = CabinetShare::create([
            'company_id' => $owner->company_id,
            'employee_id' => $owner->id,
            'shareable_type' => $shareableType,
            'shareable_id' => $shareableId,
            'share_token' => Str::random(64),
            'shared_via' => $data['shared_via'],
            'shared_with_email' => $data['shared_with_email'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        if ($data['shared_via'] === 'email' && ! empty($data['shared_with_email'])) {
            $this->sendShareEmail($share);
        }

        return $share;
    }

    public function revokeShare(CabinetShare $share): void
    {
        $share->delete();
    }

    // ── Storage stats ────────────────────────────────────────────────────────

    /**
     * @return array<string, int>
     */
    public function storageStats(Employee $owner): array
    {
        $documents = CabinetDocument::where('employee_id', $owner->id);

        return [
            'total_documents' => $documents->count(),
            'total_size' => (int) $documents->sum('size'),
            'total_folders' => CabinetFolder::where('employee_id', $owner->id)->count(),
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function deleteFolderDocumentFiles(CabinetFolder $folder): void
    {
        $folder->documents->each(function (CabinetDocument $doc): void {
            Storage::disk($doc->disk)->delete($doc->path);
        });

        $folder->children->each(function (CabinetFolder $child): void {
            $this->deleteFolderDocumentFiles($child);
        });
    }

    private function sendShareEmail(CabinetShare $share): void
    {
        $share->load(['employee', 'shareable']);

        Mail::to($share->shared_with_email)
            ->send(new CabinetShareMail($share));
    }
}
