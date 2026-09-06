<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;

class DailyReport extends Model
{
    // (2026-07-12) Cimientos módulos 6-14: firma digital/no-repudio (DSR firmable),
    // uuid público, EPP transversal y clima extendido.
    use HasFactory, HasDigitalSignatures, GeneratesUuidKey;

    // SEGURIDAD (2026-06-28): antes `$guarded = []` (todo asignable). El controlador YA inserta
    // solo campos validados (store/update usan $request->validate), así que esto es endurecimiento
    // defensivo, no un fix de vuln activa: blinda contra un futuro create($request->all()) y deja
    // explícito el contrato de columnas escribibles. `hero_image_path` se setea desde el upload.
    protected $fillable = [
        'report_date', 'shoot_day', 'location_name', 'slug_setting', 'slug_time', 'call_time',
        'weather_condition', 'weather_min_temp', 'weather_max_temp',
        'safety_meeting_time', 'safety_meeting_topics',
        'nearest_hospital', 'ambulance_company', 'medic_name',
        'crew_count', 'executive_summary', 'author_name', 'hero_image_path',
        'created_by_id', // AUTOFIRMA: id del usuario logueado (trazabilidad inmutable)
        // (2026-07-12) EPP requerido (JSON), clima extendido (humedad/viento/índice de calor),
        // factores de riesgo del día (JSON) y uuid público. medic_name YA existe arriba.
        'required_ppe', 'humidity', 'wind_speed', 'heat_index', 'day_risk_factors', 'uuid',
        // (2026-07-21) Safety meeting declarado: realizada / no realizada / sin declarar,
        // + foto en gran angular del crew reunido.
        'safety_meeting_held', 'safety_meeting_photo_path',
        // (2026-07-24) Vínculo con la producción. La columna existía desde los cimientos RBAC
        // pero NO estaba en fillable, así que create() la descartaba en silencio y los 8 DSR de
        // esta base quedaron con production_id NULL — sin llave para acotar el reporte de wrap.
        'production_id',
        // (2026-07-25) Vínculo con el SCOUTING del que el DSR hereda su hospital designado /
        // ambulancia / locación. Fuente de origen del hospital (belongsTo scouting()); reemplaza
        // el arrastre del DSR anterior, que heredaba el hospital de OTRA locación.
        'scouting_report_id',
    ];

    /**
     * (2026-07-25) Columnas nuevas excluidas del hash de la firma SÓLO cuando son null.
     *
     * scouting_report_id se añadió después de que se sellaran los 17 DSR de esta base (todos con
     * la columna en null): incluirla siempre marcaría "ALTERADO" un histórico intacto. Excluyéndola
     * cuando es null, esos sellos conservan su hash; un DSR nuevo que SÍ apunta a un scouting la
     * hashea → el vínculo queda atado al sello. Mismo patrón que los gemelos Acto/Condición.
     */
    // (2026-09-05 · Unidades P1) `unit_id` se suma a la exclusión-en-null: los DSR sellados antes de
    // sembrar la columna la traen en null → fuera del hash → su sello no cambia; con valor (2ª unidad)
    // SÍ se sella. NO se cablea ningún filtro por unidad aquí (eso es Paso 2).
    const NULLABLE_HASH_EXCLUDES = ['scouting_report_id', 'unit_id'];

    // (2026-07-12) Casts de los JSON nuevos de cimientos módulos 6-14.
    protected $casts = [
        'required_ppe'     => 'array',
        'day_risk_factors' => 'array',
        // (2026-07-21) TRES estados, y el cast es lo que los mantiene distinguibles:
        // null = no declarado (histórico anterior a la columna), false = declarado NO
        // realizado, true = declarado realizado. Sin el cast, MySQL puede devolver "0"
        // como cadena y `=== false` fallaría, colapsando "no realizado" con "sin declarar".
        'safety_meeting_held' => 'boolean',
    ];

    // Relación: Un reporte tiene muchos logs (observaciones)
    public function logs()
    {
        return $this->hasMany(DailyLog::class);
    }

    /**
     * (2026-07-25) Locación scouteada de la que este DSR heredó su hospital designado.
     * belongsTo nullable: un DSR en arranque en frío (sin scouting) la tiene en null.
     */
    public function scouting()
    {
        return $this->belongsTo(\App\Models\ScoutingReport::class, 'scouting_report_id');
    }

    /**
     * (2026-07-25) Override del payload canónico del hash: mismo comportamiento que el trait
     * (quita volátiles + ksort recursivo) pero además excluye las columnas de NULLABLE_HASH_EXCLUDES
     * cuando su valor es null, para no marcar "ALTERADO" a los DSR sellados antes de esas columnas.
     *
     * @return array
     */
    public function canonicalSignaturePayload(): array
    {
        $payload = $this->attributesToArray();
        foreach (['created_at', 'updated_at', 'uuid'] as $k) {
            unset($payload[$k]);
        }
        foreach (self::NULLABLE_HASH_EXCLUDES as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] === null) {
                unset($payload[$k]);
            }
        }
        $this->ksortRecursive($payload);
        return $payload;
    }

    /**
     * ¿El día ya está cerrado? (>= 24 h desde que se abrió).
     *
     * Centraliza la regla que vivía repetida en 4 puntos (show/storeLog/update + la vista
     * del listado). Es un MÉTODO y no un accessor a propósito: un accessor en $appends
     * entraría en attributesToArray() y por lo tanto en el hash de la firma, invalidando
     * todo lo ya sellado.
     *
     * @return bool
     */
    public function isSealed()
    {
        return $this->created_at !== null && $this->created_at->diffInHours(now()) >= 24;
    }

    /**
     * AUTO-SELLADO DE CIERRE (2026-07-22).
     *
     * El sello que de verdad importa en un DSR no es el del alta —a las 6 de la mañana el
     * documento está casi vacío— sino el del CIERRE: el resumen ejecutivo y la portada son
     * síntesis editorial del final de la jornada. Por eso, cuando el día queda cerrado, el
     * documento tiene que quedar sellado sobre su contenido DEFINITIVO.
     *
     * Sella sólo si hace falta: si nunca se selló, o si el último sello ya no casa con el
     * contenido actual (por ejemplo un DSR creado por DsrHub, que nace sin firma). Si el
     * sello vigente ya es correcto, NO añade una fila redundante.
     *
     * Se sella como SISTEMA porque a las 24 h no actúa ninguna persona: lo dispara el reloj.
     *
     * @return \App\Models\DigitalSignature|null  la firma nueva, o null si no hacía falta
     */
    public function sealIfDue()
    {
        if (!$this->isSealed()) {
            return null; // el día sigue abierto: aún puede cambiar
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('digital_signatures')) {
                return null;
            }

            $ultima = $this->signatures()->latest('id')->first();
            if ($ultima !== null && hash_equals($ultima->document_hash, $this->computeDocumentHash())) {
                return null; // ya está sellado sobre su contenido final
            }

            return $this->signDocumentAsSystem('sistema:cierre-24h');
        } catch (\Throwable $e) {
            // Nunca debe tumbar la vista de un reporte por no poder sellarlo.
            \Illuminate\Support\Facades\Log::warning('No se pudo auto-sellar el DSR #' . $this->id . ': ' . $e->getMessage());
            return null;
        }
    }
}