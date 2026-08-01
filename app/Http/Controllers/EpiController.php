<?php

namespace App\Http\Controllers;

use App\Models\IndicatorTerm;
use App\Models\OutbreakStudy;
use App\Support\CurrentProduction;
use App\Support\EpiSurveillance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EpiController — VIGILANCIA EPIDEMIOLÓGICA: panel silencioso + estudio de brote (delta #45).
 *
 * SILENCIOSO: este controlador NO envía correos, NO notifica, NO dispara nada, NO declara brotes.
 * Solo LEE consultas selladas y devuelve conteos AGREGADOS (nunca nombres). Gate: permission:epi.view
 * (solo safety y médico). El estudio de brote lo EMITE el médico cuando él lo decide.
 */
class EpiController extends Controller
{
    /* ============================ PANEL (Paso 3) ============================ */

    public function index(Request $request)
    {
        $data = EpiSurveillance::build([
            'location'   => $request->query('location'),
            'department' => $request->query('department'),
        ]);

        // Estudios de brote ya emitidos (para enlazarlos; NO se sugiere emitir ninguno).
        // Defensa en profundidad: se cargan SOLO las columnas que el panel imprime (folio/título/
        // fecha/uuid). Así el cuerpo nominal del estudio (person_description, medic_name, case_*)
        // ni siquiera entra al scope de la vista del panel agregado.
        $studies = Schema::hasTable('outbreak_studies')
            ? OutbreakStudy::active()->orderBy('id', 'desc')->limit(20)
                ->get(['id', 'uuid', 'title', 'created_at', 'is_active'])
            : collect();

        $canEmitStudy = optional(auth()->user())->isClinician() === true;

        return view('epi.index', [
            'epi'          => $data,
            'studies'      => $studies,
            'canEmitStudy' => $canEmitStudy,
            'filters'      => $data['filters'],
        ]);
    }

    /* ==================== ESTUDIO DE BROTE (Paso 4, a demanda) ==================== */

    /** El formulario del estudio. Solo el CLÍNICO lo abre: es un documento clínico firmado. */
    public function outbreakCreate(Request $request)
    {
        $this->assertClinician();

        $groupKey = $request->query('group');
        if (! array_key_exists((string) $groupKey, IndicatorTerm::GROUPS)) {
            $groupKey = null;
        }

        // Conteos que el sistema APORTA (contexto); el médico decide si hay brote y con qué criterio.
        $epi = EpiSurveillance::build([]);

        return view('epi.outbreak_create', [
            'epi'      => $epi,
            'groupKey' => $groupKey,
            'groups'   => IndicatorTerm::GROUPS,
        ]);
    }

    public function outbreakStore(Request $request)
    {
        $this->assertClinician();

        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'group_key'             => 'nullable|string|max:40',
            'period_from'           => 'nullable|date',
            'period_to'             => 'nullable|date',
            'case_definition'       => 'required|string|max:4000',
            'time_description'      => 'nullable|string|max:4000',
            'place_description'     => 'nullable|string|max:4000',
            'person_description'    => 'nullable|string|max:4000',
            'attack_rate_cases'     => 'nullable|integer|min:0',
            'attack_rate_population'=> 'nullable|integer|min:0',
            'attack_rate_note'      => 'nullable|string|max:255',
            'hypothesis'            => 'nullable|string|max:4000',
            'control_measures'      => 'nullable|string|max:4000',
        ]);

        $groupKey = array_key_exists((string) ($data['group_key'] ?? ''), IndicatorTerm::GROUPS)
            ? $data['group_key'] : null;

        // CONTEOS que aporta el sistema, CONGELADOS al emitir (evidencia del estudio).
        $epi = EpiSurveillance::build([]);
        $countsSnapshot = $this->freezeCounts($epi, $groupKey);

        // Snapshot de la cédula del médico (misma doctrina que la consulta).
        $author = auth()->user();
        $cred = ($author && class_exists(\App\Models\MedicCredential::class) && \App\Models\MedicCredential::supportsCredentials())
            ? $author->medicCredential : null;

        $payload = [
            'production_id'          => CurrentProduction::id(),
            'created_by_id'          => $author ? $author->id : null,
            'medic_name'             => $author ? $author->fullName() : null,
            'medic_cedula'           => $cred ? $cred->cedula : null,
            'medic_cedula_verified'  => $cred ? ($cred->isVerified() ? 1 : 0) : null,
            'title'                  => trim($data['title']),
            'group_key'              => $groupKey,
            'period_from'            => $data['period_from'] ?? null,
            'period_to'              => $data['period_to'] ?? null,
            'case_definition'        => $data['case_definition'],
            'time_description'       => $data['time_description'] ?? null,
            'place_description'      => $data['place_description'] ?? null,
            'person_description'     => $data['person_description'] ?? null,
            'attack_rate_cases'      => $data['attack_rate_cases'] ?? null,
            'attack_rate_population' => $data['attack_rate_population'] ?? null,
            'attack_rate_note'       => $data['attack_rate_note'] ?? null,
            'hypothesis'             => $data['hypothesis'] ?? null,
            'control_measures'       => $data['control_measures'] ?? null,
            'counts_snapshot'        => $countsSnapshot,
            'is_active'              => 1,
        ];

        // Crear y SELLAR de forma atómica (el documento nace sellado, como los demás).
        $study = DB::transaction(function () use ($payload, $author, $request) {
            $s = OutbreakStudy::create($payload);
            $s->refresh();
            $s->signDocument($author, $request);
            return $s;
        });

        return redirect()->route('epi.outbreak.show', $study->uuid)
            ->with('success', 'Estudio de brote emitido y sellado ('.$study->folio().').');
    }

    public function outbreakShow(OutbreakStudy $study)
    {
        return view('epi.outbreak_show', ['study' => $study]);
    }

    /* ============================ Helpers ============================ */

    /** El estudio es un documento CLÍNICO: solo el médico (clínico) lo emite. */
    private function assertClinician(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->isClinician(), 403,
            'El estudio de brote es un documento clínico: solo el médico puede emitirlo.');
    }

    /** Congela los conteos que aporta el sistema para el grupo elegido (o el total). */
    private function freezeCounts(array $epi, ?string $groupKey): array
    {
        $rows = [];
        foreach ($epi['days'] as $i => $day) {
            $count = $groupKey !== null
                ? ($epi['series'][$groupKey][$i] ?? 0)
                : ($epi['total'][$i] ?? 0);
            $rows[] = [
                'shoot_day'   => $day['shoot_day'],
                'date'        => $day['date'],
                'location'    => $day['location'],
                'count'       => $count,
                'denominator' => $day['crew_count'],
            ];
        }
        return [
            'group_key'          => $groupKey,
            'group_label'        => $groupKey !== null ? IndicatorTerm::groupLabel($groupKey) : 'Todos los grupos',
            'rows'               => $rows,
            'total_consults'     => $epi['total_consults'],
            'unclassified_total' => $epi['unclassified_total'],
            'note'               => 'Conteos aportados por el sistema al emitir; el criterio clínico lo pone el médico.',
        ];
    }
}
