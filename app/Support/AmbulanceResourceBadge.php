<?php

namespace App\Support;

use App\Models\AmbulanceDayResource;
use Illuminate\Support\Facades\Schema;

/**
 * AmbulanceResourceBadge — SNAPSHOT (solo lectura) del recurso de traslado del día para
 * embeber en un documento de emisión (hoy el PAE, delta #52/Parte D 2026-08-08).
 *
 * Devuelve un DTO CONGELABLE con forma ESTABLE (mismas claves en los 4 estados) para que la
 * vista sea trivial. Reglas del motor de documentos:
 *   · NO INVENTAR: sin tabla o sin declaración → estado 'none' (hueco visible, no un dato falso).
 *   · NO SOBRECLAMAR: el cotejo de la tripulación (CONOCER) HOY es siempre "documentos revisados",
 *     nunca "verificado contra registro" (esa capacidad no existe todavía). Ver
 *     {@see \App\Models\ExternalAuthorization::wasCheckedAgainstRegistry()}.
 *
 * El DTO se congela dentro del payload del PAE: si mañana cambia el recurso del día, el PAE
 * emitido sigue diciendo lo que dijo (y su sello lo prueba).
 */
class AmbulanceResourceBadge
{
    /** Forma estable del DTO: estas claves SIEMPRE están presentes. */
    private static function base(): array
    {
        return [
            'state'      => 'none',
            'title'      => '',
            'detail'     => '',
            'sub'        => '',
            'verdict'    => '',
            'method'     => '',
            'tone'       => 'neutral',   // ok | warn | neutral
            'folio'      => '',
            'verify_url' => '',
        ];
    }

    /** Recurso del día para (producción, día de rodaje). Nunca revienta: cae a 'none'. */
    public static function forDay($productionId, $shootDay): array
    {
        if (! $productionId || $shootDay === null || ! Schema::hasTable('ambulance_day_resources')) {
            return self::none();
        }

        $r = AmbulanceDayResource::active()
            ->where('production_id', $productionId)
            ->where('shoot_day', $shootDay)
            ->with(['provider', 'inspection'])
            ->latest('id')->first();

        return self::snapshot($r);
    }

    public static function snapshot(?AmbulanceDayResource $r): array
    {
        if (! $r) {
            return self::none();
        }
        if ($r->hasAmbulance()) {
            return self::ambulance($r);
        }
        if ($r->hasDeclaredMedium()) {
            return array_merge(self::base(), [
                'state'  => 'declared_medium',
                'title'  => 'Medio de traslado declarado',
                'detail' => trim((string) $r->transport_means),
                'sub'    => trim((string) $r->response_time) !== '' ? ('Llega en ' . trim((string) $r->response_time)) : '',
                'tone'   => 'neutral',
            ]);
        }
        return self::none();
    }

    /** Estado 1: ambulancia en sitio. El detalle sale del acta sellada si la hay. */
    private static function ambulance(AmbulanceDayResource $r): array
    {
        $insp     = $r->inspection;
        $provider = $r->provider ? $r->provider->name : ($insp ? $insp->provider_name : '');

        if (! $insp) {
            // Declarada pero SIN acta de verificación: se dice tal cual (no se afirma que esté apta).
            return array_merge(self::base(), [
                'state'  => 'ambulance_on_site',
                'title'  => 'Ambulancia en sitio',
                'detail' => trim((string) $provider),
                'method' => 'Sin acta de verificación',
                'tone'   => 'warn',
            ]);
        }

        // Veredicto del acta → etiqueta + tono.
        $verdictMap = [
            'apta'                    => ['Apta', 'ok'],
            'paro'                    => ['Paro inmediato', 'warn'],
            'actividad_no_ejecutable' => ['Actividad no ejecutable', 'warn'],
        ];
        [$verdLabel, $tone] = $verdictMap[$insp->verdict] ?? ['—', 'neutral'];

        // Cotejo de tripulación: cuántos TAMP quedaron cotejados. HOY el método es siempre
        // "documentos revisados" (nunca "contra registro"): no se sobreclama.
        $crew          = is_array($insp->crew_snapshot) ? $insp->crew_snapshot : [];
        $verifiedCount = 0;
        foreach ($crew as $m) {
            if (! empty($m['verified'])) {
                $verifiedCount++;
            }
        }
        $method = $verifiedCount > 0 ? 'Tripulación cotejada (documentos revisados)' : 'Tripulación sin cotejar';

        return array_merge(self::base(), [
            'state'      => 'ambulance_on_site',
            'title'      => 'Ambulancia en sitio',
            'detail'     => trim($insp->type_name . ($provider !== '' ? ' · ' . $provider : '')),
            'verdict'    => $verdLabel,
            'method'     => $method,
            'tone'       => $tone,
            'folio'      => $insp->folio(),
            'verify_url' => (string) (SealVerifier::urlFor($insp) ?: ''),
        ]);
    }

    private static function none(): array
    {
        return array_merge(self::base(), [
            'state' => 'none',
            'title' => 'Sin recurso de traslado declarado',
            'tone'  => 'warn',
        ]);
    }
}
