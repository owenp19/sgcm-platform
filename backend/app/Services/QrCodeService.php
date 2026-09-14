<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    /**
     * Genera un código QR como data URI para incrustar en HTML/PDF.
     * Usa PNG (GD) si está disponible; si no, SVG (PHP puro).
     */
    public function dataUri(string $data, string $dark = '#1d72b5', string $light = '#ffffff'): string
    {
        $gdAvailable = extension_loaded('gd');

        // Mapa color por tipo de módulo según si es oscuro o claro por defecto
        $moduleValues = [];
        foreach (QROutputInterface::DEFAULT_MODULE_VALUES as $moduleType => $isDark) {
            $moduleValues[$moduleType] = $isDark ? $dark : $light;
        }

        $options = new QROptions([
            'eccLevel' => EccLevel::M,
            'scale' => 8,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'outputInterface' => $gdAvailable ? QRGdImagePNG::class : QRMarkupSVG::class,
            'outputBase64' => false,
            'imageTransparent' => false,
            'moduleValues' => $moduleValues,
        ]);

        $output = (new QRCode($options))->render($data);

        if ($gdAvailable) {
            return 'data:image/png;base64,'.base64_encode($output);
        }

        return 'data:image/svg+xml;base64,'.base64_encode($output);
    }
}
