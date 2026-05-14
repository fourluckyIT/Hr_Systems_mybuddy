<?php

namespace App\Http\Controllers;

use App\Models\DocumentAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a stored attachment ONLY to users who can access its owning
 * document. Files live on the private (`local`) disk so they cannot be
 * served from /storage/* directly.
 *
 * Authorization rules (mirror PortalController::authorizeAccess):
 *  - admin: always allowed
 *  - otherwise: the attachment's owner document must belong to the user's
 *    own employee record
 */
class AttachmentController extends Controller
{
    public function show(DocumentAttachment $attachment): BinaryFileResponse
    {
        $this->authorize($attachment);

        $disk = Storage::disk('local');
        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'ไม่พบไฟล์แนบ');
        }

        $abs = $disk->path($attachment->file_path);
        return response()->file($abs, [
            'Content-Type'        => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($attachment->original_filename ?: basename($attachment->file_path)) . '"',
        ]);
    }

    protected function authorize(DocumentAttachment $attachment): void
    {
        $user = Auth::user();
        abort_unless($user, 401);
        if ($user->hasRole('admin')) return;

        // Load the owner doc (leave/ot/swap/expense/carryover/encash); employees see only their own.
        $owner = $attachment->attachable;
        $myEmployeeId = $user->employee?->id;

        $ownerEmployeeId = $owner?->employee_id ?? null;
        abort_unless($myEmployeeId && $ownerEmployeeId && $myEmployeeId === $ownerEmployeeId, 403, 'ไม่มีสิทธิ์เข้าถึงไฟล์นี้');
    }
}
