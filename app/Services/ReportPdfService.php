<?php

namespace App\Services;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReportPdfService
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $summary
     * @param  array<int, string>  $warnings
     */
    public function download(
        Project $project,
        array $payload,
        array $summary,
        array $warnings = [],
    ): Response {
        $scopeName = $this->scopeName($payload);
        $generatedAt = now();
        $pdf = Pdf::loadView('reports.pdf', [
            'payload' => $payload,
            'summary' => $summary,
            'warnings' => $warnings,
            'scopeName' => $scopeName,
            'generatedAt' => $generatedAt,
        ])
            ->setPaper('a4')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ]);

        $this->addPageNumbers($pdf);

        $filename = sprintf(
            'testify-%s-%s-%s.pdf',
            Str::slug($project->project_name) ?: 'project',
            Str::slug($scopeName) ?: 'all-plans',
            $generatedAt->toDateString(),
        );

        return $pdf->download($filename);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function scopeName(array $payload): string
    {
        $scope = (string) $payload['filters']['scope'];

        if ($scope === 'all') {
            return 'All Plans';
        }

        foreach ($payload['options']['testPlans'] as $testPlan) {
            if ((string) $testPlan['id'] === $scope) {
                return (string) $testPlan['name'];
            }
        }

        return 'Selected Plan';
    }

    private function addPageNumbers(DomPdfDocument $pdf): void
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (
            int $pageNumber,
            int $pageCount,
            Canvas $pageCanvas,
            FontMetrics $fontMetrics,
        ): void {
            $font = $fontMetrics->getFont('DejaVu Sans');
            $text = sprintf('Page %d of %d', $pageNumber, $pageCount);
            $textWidth = $fontMetrics->getTextWidth($text, $font, 8);

            $pageCanvas->text(
                $pageCanvas->get_width() - 42 - $textWidth,
                $pageCanvas->get_height() - 30,
                $text,
                $font,
                8,
                [0.39, 0.46, 0.55],
            );
        });
    }
}
