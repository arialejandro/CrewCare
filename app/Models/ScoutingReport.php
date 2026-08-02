<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasStandards;
use App\Traits\TracksCorrectiveActions;
use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;

/**
 * Modelo del nuevo módulo LEAN de Scouting / Location Risk Assessment.
 *
 * Reemplaza (vía strangler pattern) al formulario legado de 161 campos
 * (locationreport). NO toca el feature viejo: esta es una tabla nueva
 * (scouting_reports) pensada para ser ligera apoyándose en columnas JSON
 * (risk_assessment, viability_checklist, agreements, additional_images_paths).
 */
class ScoutingReport extends Model
{
    // (2026-07-09) Vínculo N:M a normas (standardables) y relación de acciones
    // correctivas (PDCA) para el bloqueo de finalización. El Scouting mantiene su
    // motor de riesgo POR FILA (ScoutingReportController@riskRating, matriz Amazon
    // MGM) — por eso NO usa CalculatesRiskMatrix (que es de valor único).
    // (2026-07-12) Cimientos módulos 6-14: firma digital/no-repudio, uuid público.
    use HasFactory, HasStandards, TracksCorrectiveActions, HasDigitalSignatures, GeneratesUuidKey;

    protected $table = 'scouting_reports';

    /**
     * $fillable explícito: solo estas columnas son asignables en masa.
     * Las columnas JSON se asignan como ARRAY de PHP; el cast 'array' las
     * serializa una sola vez (NO usar json_encode al guardar).
     */
    protected $fillable = [
        'production_id',
        'production_name',
        'production_type',   // Amazon MGM: Production Type (TV/Film/Game Show)
        'manager_name',      // Amazon MGM: Production Manager
        'safety_rep_name',   // Amazon MGM: Production Safety Rep
        'location_name',
        'location_address',
        'latitude',
        'longitude',
        'scene',
        'date_prep',
        'date_shoot',
        'date_wrap',
        'loc_setting',
        'shoot_time',
        'complexity',

        // Capa (1) Encabezado de emergencia (quick-read tipo DSR)
        'nearest_hospital',
        'hospital_address',
        'hospital_eta',
        'hospital_distance_km',   // (2026-07-31) distancia al hospital en km (ruta OSRM); franja del póster MEDEVAC
        'hospital_map',           // (2026-08-01) data-URI del mapa de la ruta al hospital; persiste entre emisiones MEDEVAC
        'emergency_access',
        'assembly_point',
        'ambulance_company',
        'emergency_phone',

        // Capa (2) Evaluación de riesgos H&S
        'risk_assessment',
        'requires_specific_ra',
        // (2026-07-09) Desglose SB-132 (activity_type, scene_number, certified_personnel_required)
        // exigido cuando requires_specific_ra = true.
        'sb132_details',

        // Capa (3) Operativa (LEAN vía JSON)
        'exec_summary',
        'viability_checklist',
        'agreements',
        'operational_notes',

        // (2026-07-12) EPP requerido, aforo máximo, inventario de equipo de emergencia,
        // instalaciones logísticas (JSON) y uuid público.
        'required_ppe',
        'max_headcount',
        'emergency_equipment_inventory',
        'logistics_facilities',
        'uuid',

        // Imágenes
        'main_image_path',
        'additional_images_paths',

        // Firma / control (AUTOFIRMA — make_by/created_by_id/make_date se fijan server-side)
        'make_by',
        'created_by_id',
        'make_date',
        'status',
    ];

    protected $casts = [
        'date_prep'                => 'date',
        'date_shoot'              => 'date',
        'date_wrap'               => 'date',
        'make_date'               => 'date',
        'risk_assessment'         => 'array',
        'viability_checklist'     => 'array',
        'agreements'              => 'array',
        'additional_images_paths' => 'array',
        'requires_specific_ra'    => 'boolean',
        'sb132_details'           => 'array',
        // (2026-07-12) Nuevos JSON de cimientos módulos 6-14.
        'required_ppe'                  => 'array',
        'emergency_equipment_inventory' => 'array',
        'logistics_facilities'          => 'array',
    ];

    /**
     * Imágenes adicionales normalizadas como lista de ['path' => , 'caption' => ].
     *
     * Compatibilidad hacia atrás: acepta el formato NUEVO (objetos {path, caption})
     * y el VIEJO (strings de URL sueltos, que se leen con pie de foto vacío). Todas
     * las vistas y el controlador deben pasar por aquí en vez de leer la columna cruda.
     *
     * @return array<int, array{path: string, caption: string}>
     */
    public function additionalImagesList(): array
    {
        $raw = $this->additional_images_paths;
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $path = $item['path'] ?? ($item['url'] ?? null);
                if (is_string($path) && $path !== '') {
                    // risk_map: la imagen forma parte del "Mapeo de riesgos" (foto ya
                    // marcada por el usuario). Opcional; ausente = false. No dobla la subida.
                    $out[] = [
                        'path'     => $path,
                        'caption'  => (string) ($item['caption'] ?? ''),
                        'risk_map' => !empty($item['risk_map']),
                    ];
                }
            } elseif (is_string($item) && $item !== '') {
                $out[] = ['path' => $item, 'caption' => '', 'risk_map' => false];
            }
        }

        return $out;
    }

    // (2026-08-01) La relación canvases() (mapeo por pines, delta #48) se RETIRÓ junto con
    // ScoutingCanvas/CanvasPin. El mapeo de riesgos ahora es un flag por imagen (ver
    // additionalImagesList) y las imágenes marcadas se agrupan en el reporte.
}
