<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;

/**
 * FPDI + rotación de texto (snippet clásico de FPDF). FPDI extiende FPDF (setasign/fpdf 1.8), cuyo
 * núcleo NO trae Rotate/SetAlpha. Este subtipo agrega SOLO la rotación por transformación `cm` del
 * content-stream (q/Q), suficiente para una marca de agua DIAGONAL. La opacidad se finge con un gris
 * claro (no se toca ExtGState → cero riesgo de corromper el PDF, importante porque esto corre en un
 * cron desatendido). Importa las páginas con FPDI → CONSERVA el texto del documento base.
 *
 * @internal Lo usa {@see PdfWatermarker}; no está pensado para uso directo.
 */
class WatermarkFpdi extends Fpdi
{
    /** Ángulo activo (grados). 0 = sin rotación. */
    protected float $wmAngle = 0.0;

    /**
     * Rota el sistema de coordenadas $angle grados alrededor de ($x,$y) en unidades del documento.
     * Llamar con $angle=0 cierra la rotación abierta. Basado en el snippet oficial de FPDF.
     */
    public function rotate(float $angle, float $x = -1, float $y = -1): void
    {
        if ($x == -1) { $x = $this->x; }
        if ($y == -1) { $y = $this->y; }

        if ($this->wmAngle != 0.0) {
            $this->_out('Q');   // cierra la rotación previa
        }
        $this->wmAngle = $angle;

        if ($angle != 0.0) {
            $rad = $angle * M_PI / 180;
            $c = cos($rad); $s = sin($rad);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy
            ));
        }
    }

    /** Al cerrar la página, asegura que no quede una rotación abierta (q sin su Q). */
    protected function _endpage(): void
    {
        if ($this->wmAngle != 0.0) {
            $this->wmAngle = 0.0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}
