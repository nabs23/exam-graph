<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class PdfPageRenderer
{
    /** @return array<int, array{page: int, path: string}> */
    public function render(string $pdfPath, string $outputDirectory): array
    {
        File::ensureDirectoryExists($outputDirectory);

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

        $prefix = $outputDirectory.'/page';
        $result = Process::timeout(20)->run([
            (string) config('ai.file_embeddings.pdftoppm_binary', 'pdftoppm'),
            '-png',
            '-scale-to',
            '1600',
            '-f',
            '1',
            '-l',
            (string) $pageCount,
            $pdfPath,
            $prefix,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('PDF pages could not be rendered.');
        }

        $paths = glob($prefix.'-*.png') ?: [];
        usort($paths, fn (string $first, string $second): int => $this->pageNumber($first) <=> $this->pageNumber($second));

        if (count($paths) !== $pageCount) {
            throw new RuntimeException('Not all PDF pages could be rendered.');
        }

        return array_map(
            fn (string $path, int $index): array => ['page' => $index + 1, 'path' => $path],
            $paths,
            array_keys($paths),
        );
    }

    private function pageNumber(string $path): int
    {
        preg_match('/-(\d+)\.png$/', $path, $matches);

        return isset($matches[1]) ? (int) $matches[1] : 0;
    }
}
