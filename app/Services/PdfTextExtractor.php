<?php

namespace App\Services;

use App\Exceptions\FileEmbeddingException;
use Illuminate\Support\Facades\Process;

class PdfTextExtractor
{
    /** @return array<int, array{page: int, content: string}> */
    public function extract(string $pdfPath): array
    {
        $info = Process::timeout((int) config('ai.file_embeddings.pdfinfo_timeout', 10))->run([
            (string) config('ai.file_embeddings.pdfinfo_binary', 'pdfinfo'),
            $pdfPath,
        ]);

        if (! $info->successful() || ! preg_match('/^Pages:\s+(\d+)$/m', $info->output(), $matches)) {
            throw new FileEmbeddingException('pdf_extraction_failed', false, 'PDF page metadata could not be read.');
        }

        $pageCount = (int) $matches[1];

        if ($pageCount < 1 || $pageCount > (int) config('ai.file_embeddings.pdf_page_limit', 50)) {
            throw new FileEmbeddingException('pdf_page_limit_exceeded', false, 'PDF page count is outside the supported range.');
        }

        $result = Process::timeout((int) config('ai.file_embeddings.pdftotext_timeout', 20))->run([
            (string) config('ai.file_embeddings.pdftotext_binary', 'pdftotext'),
            '-layout',
            '-f',
            '1',
            '-l',
            (string) $pageCount,
            $pdfPath,
            '-',
        ]);

        if (! $result->successful()) {
            throw new FileEmbeddingException('pdf_extraction_failed', false, 'PDF text could not be extracted.');
        }

        $pages = preg_split('/\f/u', $result->output()) ?: [];

        return collect($pages)
            ->take($pageCount)
            ->map(fn (string $content, int $index): array => [
                'page' => $index + 1,
                'content' => trim($content),
            ])
            ->filter(fn (array $page): bool => $page['content'] !== '')
            ->values()
            ->all();
    }
}
