<?php

namespace App\Http\Controllers;

use App\FileUploadStatus;
use App\Http\Requests\StoreProgramFileUploadRequest;
use App\Http\Requests\UpdateProgramFileRequest;
use App\Jobs\DeleteProgramFile;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Services\SourceFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProgramFileController extends Controller
{
    public function index(Program $program): Response
    {
        return Inertia::render('admin/program-files/index', ['program' => $program, 'files' => $program->files()->with('uploader:id,name')->latest()->get()]);
    }

    public function uploadUrl(StoreProgramFileUploadRequest $request, Program $program, SourceFileStorage $storage): JsonResponse
    {
        abort_if($program->files()->where('uploaded_by', $request->user()->id)->where('upload_status', FileUploadStatus::PendingUpload)->count() >= 5, 429);
        $data = $request->validated();
        $key = $storage->createKey('program-files', (string) $program->id, $data['extension']);
        $file = $program->files()->create([
            'file_type' => $data['file_type'], 'title' => $data['title'], 'original_filename' => basename($data['original_filename']),
            'storage_key' => $key, 'mime_type' => $data['mime_type'], 'file_size' => $data['file_size'], 'metadata' => $data['metadata'] ?? [],
            'uploaded_by' => $request->user()->id,
        ]);

        try {
            $presigned = $storage->presign($key, $data['mime_type']);
        } catch (\Throwable $exception) {
            $file->delete();
            throw $exception;
        }

        return response()->json(['file_id' => $file->id, 'upload_url' => $presigned['url'], 'required_headers' => $presigned['headers'], 'expires_at' => now()->addMinutes(5)->toISOString()]);
    }

    public function store(ProgramFile $programFile): JsonResponse
    {
        abort_unless($programFile->upload_status === FileUploadStatus::PendingUpload, 409);
        abort_unless(str_starts_with($programFile->storage_key, 'program-files/'.$programFile->program_id.'/'), 404);
        $disk = Storage::disk($programFile->storage_disk);
        $size = $disk->size($programFile->storage_key);
        $mime = $disk->mimeType($programFile->storage_key);

        if ($size === false || $size < 1 || $size > 104857600 || $size !== $programFile->file_size || $mime !== $programFile->mime_type) {
            $disk->delete($programFile->storage_key);
            app(SourceFileStorage::class)->markFailed($programFile);
            abort(422, 'The uploaded object did not match its declared size and content type.');
        }

        $programFile->forceFill(['upload_status' => FileUploadStatus::Uploaded, 'file_size' => $size, 'mime_type' => $mime, 'uploaded_at' => now()])->save();

        return response()->json($programFile->load('uploader:id,name')->makeHidden(['storage_key', 'storage_disk']));
    }

    public function show(ProgramFile $programFile): Response
    {
        return Inertia::render('admin/program-files/show', ['file' => $programFile->load('uploader:id,name', 'program:id,name,code')->makeHidden(['storage_key', 'storage_disk'])]);
    }

    public function update(UpdateProgramFileRequest $request, ProgramFile $programFile): RedirectResponse
    {
        $programFile->update($request->validated());

        return back();
    }

    public function download(ProgramFile $programFile): JsonResponse
    {
        abort_unless($programFile->upload_status === FileUploadStatus::Uploaded, 409);
        $filename = Str::slug(pathinfo($programFile->original_filename, PATHINFO_FILENAME)).'.'.Str::lower(pathinfo($programFile->original_filename, PATHINFO_EXTENSION));
        $url = Storage::disk($programFile->storage_disk)->temporaryUrl($programFile->storage_key, now()->addMinutes(5), ['ResponseContentDisposition' => 'attachment; filename="'.$filename.'"', 'ResponseContentType' => $programFile->mime_type]);

        return response()->json(['url' => $url]);
    }

    public function destroy(ProgramFile $programFile): RedirectResponse
    {
        abort_unless(in_array($programFile->upload_status, [FileUploadStatus::Uploaded, FileUploadStatus::Failed, FileUploadStatus::DeletePending], true), 409);
        $programFile->forceFill(['upload_status' => FileUploadStatus::DeletePending])->save();
        DeleteProgramFile::dispatch($programFile->id);

        return back();
    }
}
