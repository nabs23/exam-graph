<?php

namespace App\Http\Controllers;

use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Http\Requests\StoreSubjectFileUploadRequest;
use App\Http\Requests\UpdateSubjectFileRequest;
use App\Jobs\DeleteSubjectFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Services\FileEmbeddingService;
use App\Services\SourceFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SubjectFileController extends Controller
{
    public function index(Subject $subject): Response
    {
        return Inertia::render('admin/subject-files/index', ['subject' => $subject, 'files' => $subject->files()->with('uploader:id,name')->latest()->get()]);
    }

    public function uploadUrl(StoreSubjectFileUploadRequest $request, Subject $subject, SourceFileStorage $storage): JsonResponse
    {
        abort_if($subject->files()->where('uploaded_by', $request->user()->id)->where('upload_status', FileUploadStatus::PendingUpload)->count() >= 5, 429);
        $data = $request->validated();
        $key = $storage->createKey('subject-files', (string) $subject->id, $data['extension']);
        $file = $subject->files()->create([
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

    public function store(SubjectFile $subjectFile): JsonResponse
    {
        abort_unless($subjectFile->upload_status === FileUploadStatus::PendingUpload, 409);
        abort_unless(str_starts_with($subjectFile->storage_key, 'subject-files/'.$subjectFile->subject_id.'/'), 404);
        $disk = Storage::disk($subjectFile->storage_disk);
        $size = $disk->size($subjectFile->storage_key);
        $mime = $disk->mimeType($subjectFile->storage_key);

        if ($size === false || $size < 1 || $size > 104857600 || $size !== $subjectFile->file_size || $mime !== $subjectFile->mime_type) {
            $disk->delete($subjectFile->storage_key);
            app(SourceFileStorage::class)->markFailed($subjectFile);
            abort(422, 'The uploaded object did not match its declared size and content type.');
        }

        $embeddingStatus = FileEmbeddingStatus::forMimeType($mime);

        $subjectFile->forceFill([
            'upload_status' => FileUploadStatus::Uploaded,
            'file_size' => $size,
            'mime_type' => $mime,
            'uploaded_at' => now(),
            'embedding_status' => $embeddingStatus,
            'embedding_error_code' => $embeddingStatus === FileEmbeddingStatus::Unsupported ? 'unsupported_file_type' : null,
        ])->save();

        return response()->json($subjectFile->load('uploader:id,name')->makeHidden(['storage_key', 'storage_disk']));
    }

    public function show(SubjectFile $subjectFile, FileEmbeddingService $fileEmbeddingService): Response
    {
        $embeddingPreviews = collect();
        $embeddingDataBytes = null;
        $embeddingPageCount = null;
        $embeddingsAvailable = $fileEmbeddingService->isAvailable();

        if ($embeddingsAvailable
            && $subjectFile->embedding_status === FileEmbeddingStatus::Complete
            && Schema::hasTable('file_page_embeddings')) {
            $embeddingPreviews = $subjectFile->pageEmbeddings()
                ->orderBy('page_number')
                ->limit(5)
                ->get(['page_number', 'model', 'dimensions', 'content', 'embedding'])
                ->map(fn ($embedding): array => [
                    'page_number' => $embedding->page_number,
                    'model' => $embedding->model,
                    'dimensions' => $embedding->dimensions,
                    'excerpt' => str($embedding->content ?? '')->squish()->limit(180)->toString(),
                    'values' => array_slice($embedding->embedding ?? [], 0, 8),
                ]);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $embeddingSummary = $subjectFile->pageEmbeddings()
                    ->selectRaw('count(*) as embedding_page_count, coalesce(sum(pg_column_size(embedding)), 0) as embedding_data_bytes')
                    ->first();
                $embeddingDataBytes = (int) $embeddingSummary->embedding_data_bytes;
                $embeddingPageCount = (int) $embeddingSummary->embedding_page_count;
            }
        }

        return Inertia::render('admin/subject-files/show', [
            'file' => $subjectFile->load('uploader:id,name', 'subject:id,program_id,name,code')->makeHidden(['storage_key', 'storage_disk']),
            'fileEmbeddingsEnabled' => $embeddingsAvailable,
            'embeddingPreviews' => $embeddingPreviews,
            'embeddingDataBytes' => $embeddingDataBytes,
            'embeddingPageCount' => $embeddingPageCount,
        ]);
    }

    public function update(UpdateSubjectFileRequest $request, SubjectFile $subjectFile): RedirectResponse
    {
        $subjectFile->update($request->validated());

        return back();
    }

    public function download(SubjectFile $subjectFile): JsonResponse
    {
        abort_unless($subjectFile->upload_status === FileUploadStatus::Uploaded, 409);
        $filename = Str::slug(pathinfo($subjectFile->original_filename, PATHINFO_FILENAME)).'.'.Str::lower(pathinfo($subjectFile->original_filename, PATHINFO_EXTENSION));
        $url = Storage::disk($subjectFile->storage_disk)->temporaryUrl($subjectFile->storage_key, now()->addMinutes(5), ['ResponseContentDisposition' => 'attachment; filename="'.$filename.'"', 'ResponseContentType' => $subjectFile->mime_type]);

        return response()->json(['url' => $url]);
    }

    public function embed(SubjectFile $subjectFile, FileEmbeddingService $fileEmbeddingService): RedirectResponse
    {
        abort_unless($fileEmbeddingService->isAvailable(), 404);
        abort_unless($subjectFile->upload_status === FileUploadStatus::Uploaded, 409);
        $fileEmbeddingService->queue($subjectFile);

        return back();
    }

    public function destroy(SubjectFile $subjectFile): RedirectResponse
    {
        abort_unless(in_array($subjectFile->upload_status, [FileUploadStatus::Uploaded, FileUploadStatus::Failed, FileUploadStatus::DeletePending], true), 409);
        $subjectFile->forceFill(['upload_status' => FileUploadStatus::DeletePending])->save();
        DeleteSubjectFile::dispatch($subjectFile->id);

        return back();
    }
}
