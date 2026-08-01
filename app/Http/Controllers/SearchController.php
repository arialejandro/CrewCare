<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LitePatient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SearchController — CrewCare's unified, secure, role-aware crew search engine.
 *
 * STRANGLER replacement for the legacy AdminController@search* methods (legacy left
 * in place until the owner verifies each one live).
 *
 * Design — ONE preset-driven engine, not N copy-pasted methods:
 *  - runSearch(array $preset, Request, $valor) holds ALL the shared secure logic:
 *      Eloquent User (so $hidden applies; the password hash never serializes),
 *      an EXPLICIT ->select([...]) (never SELECT *), a grouped-OR closure for the
 *      LIKE filter (fixes the OR-precedence bug), the "vacio" empty sentinel,
 *      paginate(50), department scope (own-dept only unless crew.view.all-departments,
 *      err-restrictive), and field-visibility flags handed to the view.
 *  - Each public action (users / idcards) just declares a preset and delegates.
 *
 * Hardening over the legacy DB::table('users')->...->Orwhere(...) methods:
 *  - Eloquent + explicit projection — no SELECT *, no password hash in the payload.
 *  - OR terms grouped in a closure so the term filter can't leak past sibling/scope
 *    filters.
 *  - Permission-gated (crew.view) + role-aware field visibility + department scope.
 *
 * The AJAX contract is preserved per endpoint: same URI /{endpoint}/{valor}/?page=1,
 * same "vacio" empty-sentinel, same paginate(50), returns an HTML partial that the
 * front injects into $('#usertable').html(...).
 */
class SearchController extends Controller
{
    /**
     * Preset for the users directory search (/searchusers).
     *
     * Columns the componentes.search-results partial actually renders. EXPLICIT
     * projection — never SELECT *. Identity/action columns the dropdown needs
     * (id, admin, daytest, encuestadiaria, activo) are included so the
     * legacy action menu still works. Contact (phone/email) and personal
     * (borndate/sex) are selected here but only RENDERED when the searcher holds
     * the matching permission (see partial). No COVID columns are selected.
     */
    private const PRESET_USERS = [
        'view'       => 'componentes.search-results',
        'columns'    => [
            'id',
            'name',
            'lname',
            'lname2',
            'puestodepartamento',
            'zone',
            'imgperfil',
            'borndate',   // personal  (rendered @if($canPersonal))
            'sex',        // personal  (rendered @if($canPersonal))
            'phone',      // contact   (rendered @if($canContact))
            'email',      // contact   (rendered @if($canContact))
            'admin',
            'daytest',
            'encuestadiaria',
            'activo',
        ],
        // Human columns searched with LIKE — same set the legacy searchusers used.
        'searchable' => ['name', 'lname', 'lname2', 'email'],
    ];

    /**
     * Preset for the idcard / gafetes search (/searchidcard).
     *
     * Faithful to the currently-rendered componentes.searchidcard columns:
     * Zone, Photo (imgperfil), F.Name (name), L.Name (lname), L.Name2 (lname2),
     * View (idcard link by id), Printed (checkgft/uncheckgft by id; estado vía badge_prints).
     * EXPLICIT projection — never SELECT *, never the password hash.
     *
     * COVID decision: the legacy query also did Orwhere('labn', LIKE), but the idcard
     * partial NEVER renders labn — it is a COVID leftover only present in the filter.
     * Per the COVID-decommission policy we DROP it from both the projection and the
     * searchable set (do not resurrect/expand it). The visible name columns
     * (name/lname/lname2) remain searchable so the gafetes CRUD search still finds
     * crew by name exactly as before.
     */
    private const PRESET_IDCARD = [
        'view'       => 'componentes.search-results-idcard',
        'columns'    => [
            'id',
            'name',
            'lname',
            'lname2',
            'ncreditos', // nombre en créditos → subtítulo en la fila de gafete
            'zone',
            'imgperfil',
        ],
        'with_count' => ['badgePrint'], // → badge_print_count (0/1 = gafete impreso)
        'searchable' => ['name', 'lname', 'lname2'],
    ];

    /**
     * (2026-07-24 · PASO 3/3, items 5+7) Preset de la lista MÉDICA (/searchmedico).
     *
     * POR QUÉ UN PRESET PROPIO y no reusar PRESET_USERS: la lista médica y el directorio de crew
     * NO muestran lo mismo. El directorio pinta teléfono y email cuando el que busca tiene
     * `crew.view.contact` — y el médico LO TIENE. Como el buscador AJAX reemplazaba la tabla con
     * el parcial COMPARTIDO del directorio, escribir en el buscador exponía en la lista médica
     * contacto que la propia lista había decidido no mostrar (allí sólo hay edad, sin teléfono).
     * Con preset propio la proyección es EXPLÍCITA: nunca se selecciona phone/email, así que no
     * hay nada que filtrar en la vista. El alcance por departamento sigue siendo el mismo helper
     * (applyDepartmentScope, dentro de runSearch) → un HOD sólo encuentra a su gente.
     *
     * `intake_pending` NO se selecciona: se resuelve en el parcial contra `formularios` con UNA
     * consulta por página (ver componentes._crew-medical-cards).
     */
    private const PRESET_MEDICAL = [
        'view'       => 'componentes.search-results-medical',
        'columns'    => [
            'id',
            'name',
            'lname',
            'lname2',
            'puestodepartamento', // puesto
            'zone',               // departamento
            'imgperfil',
            'borndate',           // → EDAD (nunca la fecha completa; la calcula la vista)
            'sex',
        ],
        'searchable' => ['name', 'lname', 'lname2'],
    ];

    /**
     * GET /searchmedico/{valor} — buscador de la lista de Consulta Médica (cards).
     * Gateado por permission:medical.view en la ruta; runSearch además exige crew.view.
     */
    public function medical(Request $request, $valor)
    {
        // CREW: motor seguro (gate crew.view + alcance por depto + proyección sin contacto).
        $usuarios = $this->runSearchQuery(self::PRESET_MEDICAL, $request, $valor);

        // NO-CREW (lite): mismo término, supervivientes. La puerta médica es UNA sola (/medicocrud):
        // el buscador encuentra crew Y personas sin cuenta. Así el médico que busca a un integrante
        // NO lo re-registra como paciente lite (identidad partida, historial dividido). Silencioso
        // si el módulo lite no está disponible.
        $litePatients = $this->liteMedicalResults($valor);
        $liteCounts   = LitePatient::consultCountsFor($litePatients->pluck('id')->all());

        return view('componentes.search-results-medical', compact('usuarios', 'litePatients', 'liteCounts'));
    }

    /**
     * Pacientes SIN CUENTA que casan el término (o los recientes si el término es "vacio"), sólo
     * supervivientes. Búsqueda aproximada por palabra en nombre/teléfono — MISMO criterio que el
     * registro (LitePatientController). Colección vacía si el módulo lite no está disponible.
     * (2026-07-31)
     */
    protected function liteMedicalResults($valor)
    {
        if (! LitePatient::supported()) {
            return collect();
        }
        $base = LitePatient::whereNull('merged_into_id');
        if ($valor !== 'vacio' && trim((string) $valor) !== '') {
            foreach (preg_split('/\s+/', trim((string) $valor)) as $palabra) {
                if ($palabra === '') {
                    continue;
                }
                $like = '%' . $palabra . '%';
                $base->where(function ($sub) use ($like) {
                    $sub->where('full_name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            }
        }
        return $base->orderByDesc('id')->limit(50)->get();
    }

    /**
     * GET /searchusers/{valor} — directory search. Verified live; output unchanged.
     */
    public function users(Request $request, $valor)
    {
        return $this->runSearch(self::PRESET_USERS, $request, $valor);
    }

    /**
     * GET /searchidcard/{valor} — gafetes / ID-card search.
     */
    public function idcards(Request $request, $valor)
    {
        return $this->runSearch(self::PRESET_IDCARD, $request, $valor);
    }

    /**
     * Shared secure search engine. All presets flow through here.
     *
     * @param array   $preset  ['view' => string, 'columns' => string[], 'searchable' => string[]]
     */
    protected function runSearch(array $preset, Request $request, $valor)
    {
        $user = $request->user();

        $usuarios = $this->runSearchQuery($preset, $request, $valor);

        // ---- Field-visibility flags passed to the view. ----
        $canContact  = $user->can('crew.view.contact');    // phone / email
        $canPersonal = $user->can('crew.view.personal');   // birthdate / sex
        $canMedical  = $user->can('medical.view');         // clinical (none rendered yet)

        return view($preset['view'], compact(
            'usuarios', 'canContact', 'canPersonal', 'canMedical'
        ));
    }

    /**
     * Motor de consulta CREW: gate crew.view + alcance por depto + proyección explícita (nunca
     * SELECT *) + filtro OR agrupado + centinela "vacio" + paginate(50). Devuelve el PAGINADOR
     * (no la vista) para que el buscador médico pueda FUNDIR crew con lite antes de renderizar.
     * runSearch() y SearchController@medical() lo comparten. (extraído 2026-07-31)
     */
    protected function runSearchQuery(array $preset, Request $request, $valor)
    {
        $user = $request->user();

        // ---- Authorize: crew.view is the gate to search at all. ----
        abort_unless($user->can('crew.view'), 403);

        $query = User::query()->select($preset['columns']);

        // ---- Department scope (UNA fuente de verdad: User::applyDepartmentScope). ----
        // Restringe a los departamentos del buscador salvo que tenga crew.view.all-departments;
        // err-restrictivo si su depto no se puede determinar (devuelve vacío, no fuga). El
        // mismo helper lo usa AdminController@usuarioscrud (la Crew List) → comportamiento idéntico.
        $query = User::applyDepartmentScope($query, $user);

        // ---- Conteos de relación opcionales (p.ej. badge_print_count para la lista de gafetes). ----
        if (! empty($preset['with_count'])) {
            $query->withCount($preset['with_count']);
        }

        // ---- Search term filter (grouped OR closure) or "vacio" sentinel. ----
        if ($valor !== 'vacio') {
            $query->where(function ($q) use ($valor, $preset) {
                foreach ($preset['searchable'] as $i => $col) {
                    $like = '%' . $valor . '%';
                    $i === 0
                        ? $q->where($col, 'LIKE', $like)
                        : $q->orWhere($col, 'LIKE', $like);
                }
            });
        }

        return $query->orderBy('id', 'desc')->paginate(50);
    }
}
