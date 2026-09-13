<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfService
{
    /**
     * Render a blade view to a PDF response.
     */
    public static function stream(
        string $view,
        array $data,
        string $filename,
        string $paper = 'a4',
        string $orientation = 'portrait',
    ): Response {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($paper, $orientation);

        return $pdf->download($filename);
    }

    /**
     * Thermal receipt-style PDF (80mm / 58mm width, no margins bleed).
     */
    public static function receipt(
        string $view,
        array $data,
        string $filename,
        string $paperSize = '80mm',
    ): Response {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper([0, 0, $paperSize === '58mm' ? 220 : 320, 1000]);

        return $pdf->download($filename);
    }
}
