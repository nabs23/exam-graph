<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PdfTextExtractor
{
    /** @return array<int, array{page: int, content: string}> */
    public function extract(string $pdfPath): array
    {
        $info = Process::timeout(10)->run([
            (string) config('ai.file_embeddings.pdfinfo_binary', 'pdfinfo'),
            $pdfPath,
        ]);

        if (! $info->successful() || ! preg_match('/^Pages:\s+(\d+)$/m', $info->output(), $matches)) {
            throw new RuntimeException('PDF page metadata could not be read.');
        }

        $pageCount = (int) $matches[1];

        if ($pageCount < 1 || $pageCount > (int) config('ai.file_embeddings.pdf_page_limit', 50)) {
            throw new RuntimeException('PDF page count is outside the supported range.');
        }

        $result = Process::timeout(20)->run([
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
            throw new RuntimeException('PDF text could not be extracted.');
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
