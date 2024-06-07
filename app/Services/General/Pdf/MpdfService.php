<?php

namespace App\Services\General\Pdf;

use Illuminate\View\View;
use Mpdf\Mpdf;

class MpdfService
{
    public Mpdf $pdf;

    public $watermark_url = null;

    public $watermark_opacity = null;

    public function __construct(array $config = null)
    {
        $this->pdf = new Mpdf(array_merge([
            'mode' => '',
            'format' => 'A4',
            'default_font_size' => 0,
            'default_font' => '',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 6,
            'margin_bottom' => 9,
            'margin_header' => 9,
            'margin_footer' => 9,
            'orientation' => 'P',
            'tempDir' => '/tmp',
        ], $config ?? []));
    }

    public function setWatermarkImage(?string $url, ?float $opacity = null)
    {
        $this->watermark_url = $url;
        $this->watermark_opacity = $opacity ?? 0.2;

        return $this;
    }

    public function generate(View $view)
    {
        if (! empty($this->watermark_url)) {
            $this->pdf->SetWatermarkImage($this->watermark_url, $this->watermark_opacity);
            $this->pdf->showWatermarkImage = true;
        }

        // $this->pdf->AddPage('p','','','','',10,10,15,100,10,10);

        $this->pdf->WriteHTML($view->render());

        return $this;
    }

    public function output($name = null, $dest = null)
    {
        return $this->pdf->Output($name, $dest);
    }
}
