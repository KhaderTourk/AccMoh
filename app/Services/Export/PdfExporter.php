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
        $exportedAt = htmlspecialchars((string) ($data['exportedAt'] ?? now()->format('Y-m-d H:i')), ENT_QUOTES, 'UTF-8');

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
        $mpdf->SetAuthor('AccMa');
        $mpdf->SetHTMLHeader(
            '<div style="font-family:dejavusans;font-size:9px;color:#069660;border-bottom:1px solid #a7f3d0;padding-bottom:4px;text-align:right;">AccMa — إدارة مالية</div>'
        );
        $mpdf->SetHTMLFooter(
            '<table width="100%" style="font-family:dejavusans;font-size:8px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:4px;">
                <tr>
                    <td width="33%">'.$exportedAt.'</td>
                    <td width="34%" style="text-align:center;">صفحة {PAGENO} من {nbpg}</td>
                    <td width="33%" style="text-align:left;">AccMa</td>
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
