<?php

namespace App\Services;

use App\Models\TestPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TestPlanPdfService
{
    public function download(TestPlan $testPlan): Response
    {
        $testPlan->load([
            'project:project_id,project_name,user_id',
            'testCases.module:id,name',
            'testCases.steps:id,test_case_id,position,action,expected_result',
        ]);

        $generatedAt = now();
        $testCasesByModule = $testPlan->testCases
            ->groupBy(fn ($testCase): string => $testCase->module->name ?? 'Unassigned Module');

        $pdf = Pdf::loadView('test-plans.pdf', [
            'testPlan' => $testPlan,
            'testCasesByModule' => $testCasesByModule,
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
            Str::slug($testPlan->project->project_name ?? '') ?: 'project',
            Str::slug($testPlan->name) ?: 'test-plan',
            $generatedAt->toDateString(),
        );

        return $pdf->download($filename);
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
                $pageCanvas->get_height() - 28,
                $text,
                $font,
                8,
                [0.39, 0.46, 0.55],
            );
        });
    }
}
