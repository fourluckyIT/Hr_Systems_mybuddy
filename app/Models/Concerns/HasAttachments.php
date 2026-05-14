<?php

namespace App\Models\Concerns;

use App\Models\DocumentAttachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable')->latest();
    }

    public function addAttachment(UploadedFile $file, ?int $uploadedBy = null, ?string $note = null): DocumentAttachment
    {
        // Store on the PRIVATE (`local`) disk so files aren't reachable via /storage/* —
        // access is gated through AttachmentController which checks the user can see the owning doc.
        $folder = 'attachments/' . str_replace('\\', '_', static::class);
        $path = $file->store($folder, 'local');

        return $this->attachments()->create([
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
            'note' => $note,
        ]);
    }

    public function deleteAttachment(DocumentAttachment $attachment): void
    {
        if ($attachment->attachable_type === static::class && $attachment->attachable_id === $this->id) {
            Storage::disk('local')->delete($attachment->file_path);
            $attachment->delete();
        }
    }
}
