<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\StoreEmployeeDocumentRequest;
use App\Http\Requests\StoreEmployeeDocumentVersionRequest;
use App\Http\Requests\UpdateEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Filing, versioning and serving employee documents (EMP-DOC).
 *
 * Files sit on the private disk and are reachable only through download(), which runs the
 * document policy on every request — there is no URL that serves one directly.
 */
class EmployeeDocumentController extends Controller
{
    private const DISK = 'local';

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('view', $employee);

        $document = DB::transaction(function () use ($request, $employee): EmployeeDocument {
            $document = $employee->documents()->create([
                'company_id' => $employee->company_id,
                'category' => $request->validated('category'),
                'title' => $request->validated('title'),
                'is_restricted' => $this->mayRestrict($request->boolean('is_restricted')),
                'expires_on' => $request->validated('expires_on'),
            ]);

            $this->addVersion($document, $request->file('file'), null);

            return $document;
        });

        $this->flash("Dokumen \"{$document->title}\" ditambahkan.");

        return back();
    }

    public function update(UpdateEmployeeDocumentRequest $request, EmployeeDocument $document): RedirectResponse
    {
        $document->update([
            'category' => $request->validated('category'),
            'title' => $request->validated('title'),
            'is_restricted' => $this->mayRestrict($request->boolean('is_restricted'), $document->is_restricted),
            'expires_on' => $request->validated('expires_on'),
        ]);

        $this->flash('Dokumen diperbarui.');

        return back();
    }

    /**
     * File a new version. The previous file stays, so a replaced document can still be read.
     */
    public function storeVersion(StoreEmployeeDocumentVersionRequest $request, EmployeeDocument $document): RedirectResponse
    {
        DB::transaction(function () use ($request, $document): void {
            $this->addVersion($document, $request->file('file'), $request->validated('note'));

            if ($request->filled('expires_on')) {
                $document->update(['expires_on' => $request->validated('expires_on')]);
            }
        });

        $this->flash('Versi baru ditambahkan.');

        return back();
    }

    public function destroy(EmployeeDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->delete();

        $this->flash('Dokumen dihapus.');

        return back();
    }

    public function download(EmployeeDocument $document, EmployeeDocumentVersion $version): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk(self::DISK)->exists($version->path), 404);

        // Reading a restricted document is itself worth being able to account for.
        if ($document->is_restricted) {
            activity('documents')
                ->performedOn($document)
                ->causedBy(auth()->user())
                ->withProperties(['version' => $version->version_no])
                ->log('Restricted document downloaded');
        }

        return Storage::disk(self::DISK)->download($version->path, $version->original_name);
    }

    private function addVersion(EmployeeDocument $document, UploadedFile $file, ?string $note): EmployeeDocumentVersion
    {
        $next = ((int) $document->versions()->max('version_no')) + 1;

        return $document->versions()->create([
            'version_no' => $next,
            'path' => $file->store("employee-documents/{$document->employee_id}", self::DISK),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by_user_id' => auth()->id(),
            'note' => $note,
        ]);
    }

    /**
     * Whether the restricted flag may be set the way the form asked.
     *
     * Marking a document restricted needs the sensitive-data permission, otherwise the person
     * filing it could lock themselves out of their own upload; without it the flag stays as it
     * was, and an unrestricted document stays unrestricted.
     */
    private function mayRestrict(bool $requested, bool $current = false): bool
    {
        return auth()->user()?->can(Permission::ViewSensitiveData->value) === true
            ? $requested
            : $current;
    }

    private function flash(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
