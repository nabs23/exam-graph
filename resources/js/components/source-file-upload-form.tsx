import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import programFiles from '@/routes/program-files';
import programUploads from '@/routes/programs/files';

const fileTypes = [
    'reviewer_ebook',
    'reviewer_notes',
    'lecture_material',
    'official_reference',
    'practice_material',
    'other',
];

function responseErrorMessage(
    response: { message?: string; errors?: Record<string, string[]> },
    fallback: string,
): string {
    const validationMessages = Object.values(response.errors ?? {}).flat();

    if (validationMessages.length > 0) {
        return validationMessages.join(' ');
    }

    return response.message ?? fallback;
}

export function SourceFileUploadForm({
    ownerId,
    subjectId,
    subjects = [],
    defaultSubjectId,
    onUploadComplete,
}: {
    ownerId: number;
    subjectId?: number;
    subjects?: { id: number; name: string }[];
    defaultSubjectId?: number | null;
    onUploadComplete?: (subjectId: string) => void;
}) {
    const defaultUploadSubjectId = defaultSubjectId?.toString() ?? '';
    const [selectedUploadSubjectId, setSelectedUploadSubjectId] = useState(
        defaultUploadSubjectId,
    );
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [busy, setBusy] = useState(false);
    const [uploadPhase, setUploadPhase] = useState<
        'preparing' | 'uploading' | 'verifying'
    >('preparing');

    useEffect(() => {
        setSelectedUploadSubjectId(defaultUploadSubjectId);
    }, [defaultUploadSubjectId]);

    async function upload(event: React.ChangeEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');
        setSuccess('');
        const form = event.currentTarget;
        const input = form.elements.namedItem('file') as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;
        const uploadedSubjectId =
            subjectId?.toString() ?? selectedUploadSubjectId;
        if (
            !/\.(pdf|docx|epub)$/i.test(file.name) ||
            file.size > 100 * 1024 * 1024
        ) {
            setError('Choose a PDF, DOCX, or EPUB file up to 100 MiB.');
            return;
        }
        setProgress(0);
        setBusy(true);
        setUploadPhase('preparing');
        try {
            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
            const mime =
                extension === 'pdf'
                    ? 'application/pdf'
                    : extension === 'epub'
                      ? 'application/epub+zip'
                      : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            const response = await fetch(
                programUploads.uploadUrl(ownerId).url,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content ?? '',
                    },
                    body: JSON.stringify({
                        title: (
                            form.elements.namedItem('title') as HTMLInputElement
                        ).value,
                        subject_id:
                            subjectId ?? (selectedUploadSubjectId || null),
                        file_type: (
                            form.elements.namedItem(
                                'file_type',
                            ) as HTMLSelectElement
                        ).value,
                        original_filename: file.name,
                        mime_type: mime,
                        extension,
                        file_size: file.size,
                    }),
                },
            );
            const responseBody = await response.text();
            let result: {
                file_id: number;
                upload_url: string;
                required_headers: Record<string, string>;
                message?: string;
                errors?: Record<string, string[]>;
            };
            try {
                result = JSON.parse(responseBody);
            } catch {
                throw new Error(
                    `Could not prepare the upload (HTTP ${response.status}).`,
                );
            }
            if (!response.ok) {
                throw new Error(
                    responseErrorMessage(
                        result,
                        `Could not prepare the upload (HTTP ${response.status}). Check the form details and try again.`,
                    ),
                );
            }
            setUploadPhase('uploading');
            await new Promise<void>((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = (progressEvent) => {
                    if (progressEvent.lengthComputable) {
                        setProgress(
                            Math.round(
                                (progressEvent.loaded / progressEvent.total) *
                                    100,
                            ),
                        );
                    }
                };
                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        setUploadPhase('verifying');
                        resolve();

                        return;
                    }

                    reject(
                        new Error(
                            `File storage rejected the upload (HTTP ${xhr.status}). Retry to request a fresh upload, and contact an administrator if the error continues.`,
                        ),
                    );
                };
                xhr.onerror = () =>
                    reject(
                        new Error(
                            'S3 upload failed. Check your connection and retry.',
                        ),
                    );
                xhr.ontimeout = () =>
                    reject(
                        new Error(
                            'The upload took too long and timed out. Retry to request a fresh upload.',
                        ),
                    );
                xhr.open('PUT', result.upload_url);
                xhr.timeout = 30 * 60 * 1000;
                Object.entries(
                    result.required_headers as Record<string, string>,
                ).forEach(([name, value]) => xhr.setRequestHeader(name, value));
                xhr.send(file);
            });
            const completed = await fetch(
                programFiles.store(result.file_id).url,
                {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content ?? '',
                        Accept: 'application/json',
                    },
                },
            );
            if (!completed.ok) {
                let verificationMessage: string | undefined;

                try {
                    const responseBody = (await completed.json()) as {
                        message?: string;
                        errors?: Record<string, string[]>;
                    };
                    verificationMessage = responseErrorMessage(
                        responseBody,
                        `Upload verification failed (HTTP ${completed.status}). Check the document and retry.`,
                    );
                } catch {
                    verificationMessage = undefined;
                }

                throw new Error(
                    verificationMessage ??
                        `Upload verification failed (HTTP ${completed.status}). Check the document and retry.`,
                );
            }
            form.reset();
            setProgress(0);
            setUploadPhase('preparing');
            setSelectedUploadSubjectId(defaultUploadSubjectId);
            if (onUploadComplete) {
                onUploadComplete(uploadedSubjectId);
            } else {
                setSuccess(
                    'Upload complete. The file will appear below with its current text processing status.',
                );
                router.reload();
            }
        } catch (uploadError) {
            setError(
                uploadError instanceof TypeError
                    ? 'Could not connect to the upload service. Check your connection and try again.'
                    : uploadError instanceof Error
                      ? uploadError.message
                      : 'Upload failed. Check your connection and try again.',
            );
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <form
                onSubmit={upload}
                aria-label={
                    subjectId === undefined
                        ? 'Upload program source material'
                        : 'Upload subject source material'
                }
                className={`grid gap-4 rounded-xl border border-dashed bg-muted/30 p-4 sm:grid-cols-2 ${subjectId === undefined ? 'xl:grid-cols-4' : 'xl:grid-cols-3'}`}
            >
                <label
                    htmlFor="source-file-title"
                    className="grid content-start gap-1.5 text-sm font-medium"
                >
                    <span>Document title</span>
                    <input
                        id="source-file-title"
                        name="title"
                        required
                        maxLength={255}
                        disabled={busy}
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-destructive"
                        placeholder="e.g. Official syllabus"
                    />
                </label>
                {subjectId === undefined && (
                    <label
                        htmlFor="source-file-subject"
                        className="grid content-start gap-1.5 text-sm font-medium"
                    >
                        <span>Curriculum scope</span>
                        <select
                            id="source-file-subject"
                            name="subject_id"
                            value={selectedUploadSubjectId}
                            onChange={(event) =>
                                setSelectedUploadSubjectId(event.target.value)
                            }
                            disabled={busy}
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <option value="">Shared across program</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name}
                                </option>
                            ))}
                        </select>
                        <span className="text-xs font-normal text-muted-foreground">
                            Choose a subject or leave shared for program-wide
                            material.
                        </span>
                    </label>
                )}
                <label
                    htmlFor="source-file-category"
                    className="grid content-start gap-1.5 text-sm font-medium"
                >
                    <span>Source category</span>
                    <select
                        id="source-file-category"
                        name="file_type"
                        disabled={busy}
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {fileTypes.map((type) => (
                            <option key={type} value={type}>
                                {type
                                    .replaceAll('_', ' ')
                                    .replace(/\b\w/g, (character) =>
                                        character.toUpperCase(),
                                    )}
                            </option>
                        ))}
                    </select>
                    <span className="text-xs font-normal text-muted-foreground">
                        Classifies the source; it does not approve or endorse
                        generated content.
                    </span>
                </label>
                <label
                    htmlFor="source-file-document"
                    className="grid content-start gap-1.5 text-sm font-medium"
                >
                    <span>Document</span>
                    <input
                        id="source-file-document"
                        name="file"
                        type="file"
                        accept=".pdf,.docx,.epub"
                        required
                        disabled={busy}
                        aria-describedby="source-file-format-hint"
                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-60"
                    />
                    <span
                        id="source-file-format-hint"
                        className="text-xs font-normal text-muted-foreground"
                    >
                        PDF, DOCX, or EPUB · up to 100 MiB
                    </span>
                </label>
                <div
                    className={`flex justify-end sm:col-span-2 ${subjectId === undefined ? 'xl:col-span-4' : 'xl:col-span-3'}`}
                >
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Uploading…' : 'Upload source file'}
                    </Button>
                </div>
            </form>

            {busy && (
                <div className="space-y-2">
                    <div className="flex justify-between text-sm text-muted-foreground">
                        <span role="status" aria-live="polite">
                            {uploadPhase === 'preparing'
                                ? 'Preparing file upload…'
                                : uploadPhase === 'uploading'
                                  ? 'Uploading file…'
                                  : 'Verifying uploaded file…'}
                        </span>
                        <span aria-hidden="true">{progress}%</span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label="Source file upload progress"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={progress}
                        className="h-2 w-full overflow-hidden rounded-full bg-secondary"
                    >
                        <div
                            className="h-full bg-primary transition-all duration-300 ease-in-out"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>
            )}

            {error && (
                <div
                    role="alert"
                    aria-live="assertive"
                    className="rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-sm font-medium text-destructive"
                >
                    {error}
                </div>
            )}

            {success && (
                <p
                    role="status"
                    aria-live="polite"
                    className="rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm font-medium"
                >
                    {success}
                </p>
            )}
        </>
    );
}
