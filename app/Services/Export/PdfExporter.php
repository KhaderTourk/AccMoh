<?php

namespace App\Services\Export;

use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PdfExporter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function download(string $view, array $data, string $filename): Response
    {
        $html = view($view, $data)->render();
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $title = (string) ($data['title'] ?? 'تقرير');
        $exportedAt = htmlspecialchars((string) ($data['exportedAt'] ?? format_date(now(), true)), ENT_QUOTES, 'UTF-8');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'directionality' => 'rtl',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetDirectionality('rtl');
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor('حسابات براند');
        // A view can bring its own footer (the client statement does); the rest share the default one.
        if (empty($data['pdfOwnHeader'])) {
            $mpdf->SetHTMLHeader(
                '<div style="font-family:dejavusans;font-size:9px;color:#0B2537;border-bottom:1.5px solid #FB005B;padding-bottom:4px;text-align:right;">حسابات براند المالية</div>'
            );
        }
        $mpdf->SetHTMLFooter($data['pdfFooter'] ??
            '<table width="100%" style="font-family:dejavusans;font-size:8px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:4px;">
                <tr>
                    <td width="33%">'.$exportedAt.'</td>
                    <td width="34%" style="text-align:center;">صفحة {PAGENO} من {nbpg}</td>
                    <td width="33%" style="text-align:left;">حسابات براند</td>
                </tr>
            </table>'
        );
        $mpdf->WriteHTML($html);

        $name = str_ends_with(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';

        return response($mpdf->Output($name, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
