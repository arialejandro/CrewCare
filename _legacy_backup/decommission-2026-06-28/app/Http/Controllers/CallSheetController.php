<?php

namespace App\Http\Controllers;

use App\Models\CallSheet;
use App\Models\DailyReport;
use App\Models\Production;
use App\Models\SafetyStandard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * CALL SHEET ("el llamado") — módulo nuevo, sin conflicto con features existentes.
 *
 * La pieza clave es el AUTO-ATTACH de boletines de seguridad: al crear un llamado,
 * por cada categoría de riesgo seleccionada resolvemos su norma en el catálogo
 * SafetyStandard y CONGELAMOS un snapshot {category, badge, code, url} en
 * call_sheets.identified_risks. Ese snapshot es lo que la vista y el PDF imprimen.
 */
class CallSheetController extends Controller
{
    /**
     * 1. LISTADO — los llamados más recientes primero, paginados.
     */
    public function index()
    {
        $callSheets = CallSheet::latest()->paginate(15);

        return view('admin.callsheets.index', compact('callSheets'));
    }

    /**
     * 2. FORMULARIO DE CREACIÓN — catálogos para los selects.
     */
    public function create()
    {
        $productions = Production::where('active', 1)->orderBy('name')->get();
        $standards = SafetyStandard::orderBy('category_name')->get();
        $dailyReports = DailyReport::latest()->limit(50)->get();

        return view('admin.callsheets.create', compact('productions', 'standards', 'dailyReports'));
    }

    /**
     * 3. GUARDAR — valida, arma el snapshot de riesgos (auto-attach) y crea el llamado.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'production_id' => 'nullable|integer|exists:productions,id',
            'daily_report_id' => 'nullable|integer|exists:daily_reports,id',
            'title' => 'nullable|string|max:150',
            'sheet_date' => 'required|date',
            'shoot_day' => 'nullable|string|max:30',
            'general_call' => 'nullable',
            'location_name' => 'nullable|string|max:255',
            'location_address' => 'nullable|string|max:500',
            'set_setting' => 'nullable|string|max:30',
            'day_part' => 'nullable|string|max:30',
            'weather_note' => 'nullable|string|max:255',
            'sunrise' => 'nullable',
            'sunset' => 'nullable',
            'nearest_hospital' => 'nullable|string|max:255',
            'hospital_address' => 'nullable|string|max:500',
            'ambulance_company' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:50',
            'assembly_point' => 'nullable|string|max:255',
            'safety_notes' => 'nullable|string',
            // Las categorías de riesgo llegan como array de strings category_name[].
            'category_name' => 'nullable|array',
            'category_name.*' => 'string',
        ]);

        // --- AUTO-ATTACH: snapshot de boletines por categoría seleccionada ---
        $risks = $this->risksFromCategories($request->input('category_name', []));

        // Si además se eligió un daily report, fusionamos SUS riesgos (logs) al snapshot.
        if (! empty($data['daily_report_id'])) {
            $report = DailyReport::with('logs')->find($data['daily_report_id']);
            if ($report) {
                $risks = $this->mergeRisks($risks, $this->risksFromDailyReport($report));
            }
        }

        $callSheet = CallSheet::create([
            'production_id' => $data['production_id'] ?? null,
            'daily_report_id' => $data['daily_report_id'] ?? null,
            'title' => $data['title'] ?? null,
            'sheet_date' => $data['sheet_date'],
            'shoot_day' => $data['shoot_day'] ?? null,
            'general_call' => $data['general_call'] ?? null,
            'location_name' => $data['location_name'] ?? null,
            'location_address' => $data['location_address'] ?? null,
            'set_setting' => $data['set_setting'] ?? null,
            'day_part' => $data['day_part'] ?? null,
            'weather_note' => $data['weather_note'] ?? null,
            'sunrise' => $data['sunrise'] ?? null,
            'sunset' => $data['sunset'] ?? null,
            'nearest_hospital' => $data['nearest_hospital'] ?? null,
            'hospital_address' => $data['hospital_address'] ?? null,
            'ambulance_company' => $data['ambulance_company'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,
            'assembly_point' => $data['assembly_point'] ?? null,
            'safety_notes' => $data['safety_notes'] ?? null,
            'identified_risks' => $risks,
            'status' => 'draft',
            'created_by' => auth()->user()->name,
        ]);

        return redirect()->route('call_sheets.show', $callSheet->id)
                         ->with('success', 'Llamado creado. Boletines de seguridad adjuntados automáticamente.');
    }

    /**
     * 4. VISTA PREVIA (estilo magazine — homologada al Daily Report).
     */
    public function show($id)
    {
        $callSheet = CallSheet::with(['production', 'dailyReport'])->findOrFail($id);

        // Defensa: re-resolver URLs de boletín que pudieran faltar en el snapshot
        // (por ejemplo, snapshots viejos creados antes de existir reference_url).
        $callSheet->identified_risks = $this->backfillRiskUrls($callSheet->identified_risks);

        return view('admin.callsheets.show', compact('callSheet'));
    }

    /**
     * 5. ENVIAR — marca el llamado como enviado al crew. (POST)
     */
    public function send($id)
    {
        $callSheet = CallSheet::findOrFail($id);
        $callSheet->status = 'sent';
        $callSheet->sent_at = now();
        $callSheet->save();

        return redirect()->back()->with('success', 'Llamado enviado al crew.');
    }

    /**
     * 6. PDF — render server-side con DomPDF (barryvdh v2), tamaño carta.
     */
    public function pdf($id)
    {
        $callSheet = CallSheet::with(['production', 'dailyReport'])->findOrFail($id);
        $callSheet->identified_risks = $this->backfillRiskUrls($callSheet->identified_risks);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.callsheets.pdf', compact('callSheet'))
            ->setPaper('letter')
            ->download('llamado-'.$callSheet->id.'.pdf');
    }

    // =====================================================================
    // Helpers privados
    // =====================================================================

    /**
     * Resuelve un arreglo de category_name al snapshot de boletines.
     * Cada item: {category, badge, code, url}. url solo si la columna existe.
     */
    private function risksFromCategories(array $categories): array
    {
        $categories = array_values(array_unique(array_filter($categories)));
        if (empty($categories)) {
            return [];
        }

        $hasUrl = Schema::hasColumn('safety_standards', 'reference_url');

        $standards = SafetyStandard::whereIn('category_name', $categories)->get();

        $risks = [];
        foreach ($standards as $std) {
            $risks[] = [
                'category' => $std->category_name,
                'badge' => $std->regulation_badge,
                'code' => $std->regulation_code,
                'url' => $hasUrl ? ($std->reference_url ?? null) : null,
            ];
        }

        return $risks;
    }

    /**
     * Extrae los riesgos DISTINTOS (badge, code) de los logs de un daily report y
     * los convierte al snapshot, resolviendo la URL desde el catálogo cuando exista.
     */
    private function risksFromDailyReport(DailyReport $r): array
    {
        $hasUrl = Schema::hasColumn('safety_standards', 'reference_url');

        // Indexamos catálogo por regulation_code para recuperar category_name + url.
        $catalog = SafetyStandard::all()->keyBy('regulation_code');

        $seen = [];
        $risks = [];
        foreach ($r->logs as $log) {
            $badge = $log->regulation_badge;
            $code = $log->regulation_code;

            // Saltamos logs sin norma (NA / vacío) y duplicados (badge|code).
            if (empty($code) || $badge === 'NA') {
                continue;
            }
            $key = $badge.'|'.$code;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $std = $catalog->get($code);
            $risks[] = [
                'category' => $std ? $std->category_name : $code,
                'badge' => $badge,
                'code' => $code,
                'url' => ($hasUrl && $std) ? ($std->reference_url ?? null) : null,
            ];
        }

        return $risks;
    }

    /**
     * Fusiona dos snapshots de riesgos evitando duplicados por (badge|code).
     */
    private function mergeRisks(array $a, array $b): array
    {
        $merged = [];
        $seen = [];
        foreach (array_merge($a, $b) as $risk) {
            $key = ($risk['badge'] ?? '').'|'.($risk['code'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $risk;
        }

        return $merged;
    }

    /**
     * Rellena de forma defensiva las URLs faltantes del snapshot, resolviéndolas
     * desde el catálogo por regulation_code (si la columna reference_url existe).
     */
    private function backfillRiskUrls($risks): array
    {
        if (empty($risks) || ! is_array($risks)) {
            return [];
        }

        if (! Schema::hasColumn('safety_standards', 'reference_url')) {
            return $risks;
        }

        $needs = false;
        foreach ($risks as $risk) {
            if (empty($risk['url'])) {
                $needs = true;
                break;
            }
        }
        if (! $needs) {
            return $risks;
        }

        $catalog = SafetyStandard::all()->keyBy('regulation_code');

        foreach ($risks as $i => $risk) {
            if (empty($risk['url']) && ! empty($risk['code'])) {
                $std = $catalog->get($risk['code']);
                $risks[$i]['url'] = ($std && isset($std->reference_url)) ? $std->reference_url : ($risk['url'] ?? null);
            }
        }

        return $risks;
    }
}
