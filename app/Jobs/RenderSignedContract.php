<?php

namespace App\Jobs;

use App\Models\ContractEnvelope;
use App\Support\ContractSignedRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * EL CONTRATO · PASO C · FASE 3 — congela el CONTRATO FIRMADO (PDF con las autógrafas) al completarse
 * el sobre. Va en cola (flag `contracts_queue_render`) para no bloquear la última firma con el render
 * de Chrome; sin worker corre inline igual. Recibe el ID (no el modelo) para serializar barato.
 *
 * DEFENSIVO: solo actúa sobre sobres COMPLETADOS; el fallo del render (Chrome ausente, plantilla rara)
 * se traga aquí y se registra — el contrato ya está firmado y su evidencia (bitácora + sellos por
 * firmante) NO depende de este PDF. El firmado es un artefacto de ENTREGA, no la prueba legal.
 */
class RenderSignedContract implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $envelopeId)
    {
    }

    public function handle(): void
    {
        $envelope = ContractEnvelope::find($this->envelopeId);
        if (! $envelope || ! $envelope->isCompleted()) {
            return;
        }

        try {
            ContractSignedRenderer::store($envelope);
        } catch (\Throwable $e) {
            Log::warning('RenderSignedContract: no se pudo congelar el contrato firmado del sobre '
                . $this->envelopeId . ' — ' . $e->getMessage());
        }
    }
}
