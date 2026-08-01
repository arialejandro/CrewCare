<?php

namespace App\Http\Controllers;

use App\Models\IssuedPermit;
use App\Models\Permit;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EMISIÓN DE PERMISOS DE TRABAJO (ciclo completo, delta #44): emitir → verificar en
 * sitio → cerrar. Un permiso que se emite y nunca se cierra no autoriza nada verificable.
 *
 * Gate único: permission:permits.issue (rutas). El verificador PÚBLICO del permiso NO
 * usa este controlador (vive en SealVerificationController, sin sesión).
 *
 * DOCTRINA: el catálogo (delta #41) UNE; el permiso emitido CONGELA lo que citó. La app
 * NO verifica la autorización externa: la DECLARA bajo responsabilidad de quien la captura.
 */
class PermitController extends Controller
{
    /* ============================ ÍNDICE ============================ */

    /**
     * Responde "¿hay permiso vigente para esta actividad aquí y hoy?": lista los vigentes
     * de la jornada, los PENDIENTES DE CIERRE (jornada anterior sin cerrar) y las 15
     * plantillas para emitir. Puede llegar con una puerta desde la inspección (permit+sitio).
     */
    public function index(Request $request)
    {
        $shootDay = $this->currentShootDay();

        $open = IssuedPermit::active()
            ->whereNull('closed_at')->whereNull('suspended_at')
            ->orderBy('shoot_day', 'desc')->orderBy('id', 'desc')
            ->get();

        $vigentesHoy  = $open->filter(fn ($p) => $shootDay !== null && $p->shoot_day === $shootDay)->values();
        $pendingClose = $open->filter(fn ($p) => $p->isPendingClose($shootDay))->values();

        $templates = Permit::active()->with('points')->orderBy('sort_order')->orderBy('code')->get()
            ->groupBy(fn ($p) => $p->family ?: '—');

        // Puerta (Paso 6): la inspección manda permit + sitio + actividad para responder aquí.
        $launch = $this->launchParams($request);
        $launchAnswer = null;
        if (! empty($launch['permit_id'])) {
            $permit = Permit::find($launch['permit_id']);
            if ($permit) {
                $vig = IssuedPermit::vigenteFor($permit->code, $shootDay, $launch['site'] ?? null, $permit->site_scope);
                $launchAnswer = [
                    'permit'  => $permit,
                    'vigente' => ($vig && $vig->coversSite($launch['site'] ?? null)) ? $vig : null,
                    'otroSitio' => ($vig && ! $vig->coversSite($launch['site'] ?? null)) ? $vig : null,
                ];
            }
        }

        return view('permits.index', compact(
            'vigentesHoy', 'pendingClose', 'templates', 'shootDay', 'launch', 'launchAnswer'
        ));
    }

    /** Parámetros de puerta (permiso + sitio + actividad + herramienta) que sobreviven a la emisión. */
    private function launchParams(Request $request): array
    {
        $out = [];
        if ($request->query('permit_id')) {
            $out['permit_id'] = (int) $request->query('permit_id');
        }
        foreach (['site', 'activity', 'tool_id'] as $k) {
            if ($request->query($k) !== null && $request->query($k) !== '') {
                $out[$k] = $request->query($k);
            }
        }
        return $out;
    }

    /* ============================ EMISIÓN (Paso 1-2) ============================ */

    public function create(Request $request, Permit $permit)
    {
        $permit->load(['points' => function ($q) {
            $q->where('is_active', 1)->orderBy('sort_order')->orderBy('code');
        }, 'standards']);

        $shootDay = $this->currentShootDay();

        // ¿Ya hay un permiso VIGENTE de este tipo hoy? (no impide emitir, informa).
        $site = $request->query('site');
        $vigente = IssuedPermit::vigenteFor($permit->code, $shootDay, $site, $permit->site_scope);

        $prefill = [
            'site'     => $site,
            'activity' => $request->query('activity'),
            'tool_id'  => $request->query('tool_id'),
        ];
        $tool = ! empty($prefill['tool_id']) ? \App\Models\Tool::find($prefill['tool_id']) : null;

        $requiresFireWatch = $this->deriveFireWatch($permit);
        $supersedes = $request->query('supersedes'); // uuid del permiso que ésta sustituye (opcional)

        return view('permits.create', compact('permit', 'shootDay', 'vigente', 'prefill', 'tool', 'requiresFireWatch', 'supersedes'));
    }

    public function store(Request $request, Permit $permit)
    {
        $permit->load(['points' => function ($q) {
            $q->where('is_active', 1)->orderBy('sort_order')->orderBy('code');
        }, 'standards']);

        $rules = [
            'activity_description' => 'required|string|max:2000',
            'site_label'           => 'required|string|max:255',
            'tool_id'              => 'nullable|integer|exists:tools,id',
            'acceptor_name'        => 'required|string|max:255',
            'acceptor_role'        => 'nullable|string|max:100',
            'acceptor_id_label'    => 'nullable|string|max:60',
            'acceptor_id_value'    => 'nullable|string|max:120',
            'answers'              => 'required|array|min:1',
            'answers.*'            => 'in:cumple,no_cumple',
            // Autorización externa (declaración). Obligatoria se valida abajo según el permiso.
            'ext_auth_authority'   => 'nullable|string|max:120',
            'ext_auth_folio'       => 'nullable|string|max:120',
            'ext_auth_valid_until' => 'nullable|date',
            'ext_auth_declared_by' => 'nullable|string|max:255',
            'ext_auth_note'        => 'nullable|string|max:2000',
            // Cuando esta emisión SUSTITUYE a otra (cambio de sitio / re-montaje): el uuid del viejo.
            'supersedes'           => 'nullable|string|exists:issued_permits,uuid',
        ];
        $data = $request->validate($rules);

        // Normaliza los campos de autorización externa: espacios-en-blanco = AUSENTE. Sin esto,
        // `empty("   ")` es false y la compuerta de obligatoria se escaparía con una declaración vacía.
        foreach (['ext_auth_authority', 'ext_auth_folio', 'ext_auth_declared_by', 'ext_auth_note'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = trim($data[$k]);
                if ($data[$k] === '') { $data[$k] = null; }
            }
        }

        // AUTORIZACIÓN EXTERNA OBLIGATORIA (Paso 2): sin folio/autoridad/vigencia/declarante NO se emite.
        // La app NO verifica el documento externo: registra una DECLARACIÓN bajo responsabilidad.
        // Se compara contra null (ya normalizado) para NO rechazar un folio literal "0" ni aceptar espacios.
        if ($permit->requiresExternalAuthorization()) {
            $missing = [];
            if (($data['ext_auth_authority']   ?? null) === null) { $missing[] = 'la autoridad'; }
            if (($data['ext_auth_folio']       ?? null) === null) { $missing[] = 'el folio'; }
            if (($data['ext_auth_valid_until'] ?? null) === null) { $missing[] = 'la vigencia'; }
            if (($data['ext_auth_declared_by'] ?? null) === null) { $missing[] = 'quién lo declara'; }
            if ($missing) {
                return back()->withInput()->with('error',
                    'Este permiso exige autorización externa: falta '.implode(', ', $missing).
                    '. Es una DECLARACIÓN bajo responsabilidad de quien la captura; sin ella no se emite.');
            }
        }

        // COMPUERTA: los 103 puntos son compuerta. Si UNO no se cumple, NO se emite. Los puntos
        // autoritativos salen del servidor (no se confía en el cliente para gate/orden/texto).
        $points = $permit->points;
        if ($points->isEmpty()) {
            return back()->withInput()->with('error', 'Este permiso no tiene puntos que verificar.');
        }
        $answers  = $data['answers'];
        $snapshot = [];
        foreach ($points as $p) {
            $ans = $answers[$p->code] ?? null;
            if ($ans === null) {
                return back()->withInput()->with('error',
                    "Falta responder el punto {$p->code}. No hay permiso a medias.");
            }
            if ($ans === 'no_cumple') {
                // NO EMITIDO, con el punto que lo impidió a la vista.
                return back()->withInput()->with('error',
                    "NO EMITIDO — no se cumple el punto {$p->code}: “".trim((string) $p->text_es)."”. ".
                    'Todo punto es compuerta: si uno no se cumple, no se emite.');
            }
            $snapshot[] = [
                'code'             => $p->code,
                'text'             => $p->text_es,
                'executor'         => $p->executor,
                'is_gate'          => (bool) $p->is_gate,
                'site_sensitive'   => (bool) $p->site_sensitive,
                'requires_contact' => (bool) $p->requires_contact,
                'answer'           => 'cumple',
            ];
        }

        // Snapshots de identidad (doctrina de congelamiento): quien emite (safety) + quien acepta.
        $author = auth()->user();
        $cred = ($author && \App\Models\MedicCredential::supportsCredentials()) ? $author->medicCredential : null;
        $standards = $permit->standards->pluck('regulation_code')->filter()->values()->all();

        $payload = [
            'production_id'        => CurrentProduction::id(),
            'shoot_day'            => $this->currentShootDay(),
            'permit_id'            => $permit->id,
            'permit_code'          => $permit->code,
            'permit_key'           => $permit->permit_key,
            'permit_family'        => $permit->family,
            'permit_name'          => $permit->name,
            'permit_definition'    => $permit->definition,
            'permit_site_scope'    => $permit->site_scope,
            'points_snapshot'      => $snapshot,
            'standards_snapshot'   => $standards,
            'activity_description' => trim($data['activity_description']),
            'site_label'           => trim($data['site_label']),
            'tool_id'              => $data['tool_id'] ?? null,
            'tool_code'            => null,
            'tool_name'            => null,
            'ext_auth_mandatory'   => $permit->requiresExternalAuthorization(),
            'ext_auth_authority'   => $data['ext_auth_authority'] ?? null,
            'ext_auth_folio'       => $data['ext_auth_folio'] ?? null,
            'ext_auth_valid_until' => $data['ext_auth_valid_until'] ?? null,
            'ext_auth_declared_by' => $data['ext_auth_declared_by'] ?? null,
            'ext_auth_note'        => $data['ext_auth_note'] ?? null,
            'issuer_user_id'       => $author ? $author->id : null,
            'issuer_name'          => $author ? $author->fullName() : null,
            'issuer_role'          => $author ? optional($author->getRoleNames())->first() : null,
            'issuer_cedula'        => $cred ? $cred->cedula : null,
            'acceptor_user_id'     => null,
            'acceptor_name'        => trim($data['acceptor_name']),
            'acceptor_role'        => $data['acceptor_role'] ?? null,
            'acceptor_id_label'    => $data['acceptor_id_label'] ?? null,
            'acceptor_id_value'    => $data['acceptor_id_value'] ?? null,
            'accepted_at'          => now(),
            'requires_fire_watch'  => $this->deriveFireWatch($permit),
            'is_active'            => 1,
        ];

        if (! empty($payload['tool_id'])) {
            $tool = \App\Models\Tool::find($payload['tool_id']);
            if ($tool) { $payload['tool_code'] = $tool->code; $payload['tool_name'] = $tool->name; }
        }

        // Crear y SELLAR de forma ATÓMICA (una sola integridad sobre las dos firmas congeladas).
        $issued = DB::transaction(function () use ($payload, $author, $request) {
            $p = IssuedPermit::create($payload);
            $p->refresh();
            $p->signDocument($author, $request);
            return $p;
        });

        // Si esta emisión SUSTITUYE a otra (cambio de sitio / re-montaje de rig), enlaza vieja → nueva.
        // El enlace vive en la columna hash-excluida `superseded_by_id` del permiso viejo: NO re-sella
        // (cambia su estado, no su integridad). El verificador la mostrará "sustituido por PERM-####".
        if (! empty($data['supersedes'])) {
            $old = IssuedPermit::where('uuid', $data['supersedes'])->first();
            if ($old && $old->id !== $issued->id && $old->superseded_by_id === null) {
                $old->superseded_by_id = $issued->id;
                $old->save();
            }
        }

        // ADVERTENCIA (no bloquea): si la autorización externa caduca antes que la actividad.
        $warning = null;
        if ($issued->ext_auth_valid_until && $issued->ext_auth_valid_until->lt(now()->startOfDay())) {
            $warning = 'ATENCIÓN: la vigencia declarada de la autorización externa ('.
                $issued->ext_auth_valid_until->format('d/m/Y').') ya venció. Verifícala antes de operar.';
        }

        $redirect = redirect()->route('permits.show', $issued->uuid)
            ->with('success', 'Permiso emitido y sellado ('.$issued->folio().').');
        return $warning ? $redirect->with('warning', $warning) : $redirect;
    }

    /* ============================ EL DOCUMENTO (interno, sellado) ============================ */

    public function show(IssuedPermit $issued)
    {
        $issued->load('supersededBy', 'permit');
        $shootDay = $this->currentShootDay();
        $actionItem = Schema::hasTable('action_items')
            ? $issued->actionItems()->where('source_field', 'permit_close_pending')->latest('id')->first()
            : null;
        // Puntos sensibles al sitio (para el bloque de reverificación).
        $siteSensitive = collect($issued->points_snapshot ?? [])->filter(fn ($p) => ! empty($p['site_sensitive']))->values();

        return view('permits.show', compact('issued', 'shootDay', 'actionItem', 'siteSensitive'));
    }

    /* ============================ REVERIFICACIÓN (Paso 3) ============================ */

    /**
     * Reverificar en OTRO sitio: se re-corren SOLO los puntos sensibles al sitio, no el permiso
     * completo. Queda registrado en el MISMO permiso (autor y hora), SIN re-sellar la emisión
     * original (columnas hash-excluidas). Solo aplica a `reverificacion`; `ligado_al_sitio` exige
     * emisión nueva y `indiferente` no lo necesita. Re-montar un rig NO es mover: es emisión nueva.
     */
    public function reverify(Request $request, IssuedPermit $issued)
    {
        if ($issued->permit_site_scope !== IssuedPermit::SCOPE_REVERIFY) {
            return back()->with('error', 'Este permiso no se reverifica: '.
                ($issued->permit_site_scope === IssuedPermit::SCOPE_SITEBOUND
                    ? 'está ligado al sitio y cambiar de sitio exige emisión nueva.'
                    : 'vale por la jornada sin importar el sitio.'));
        }
        if (! $issued->isOpen()) {
            return back()->with('error', 'Un permiso cerrado o suspendido no se reverifica.');
        }

        $siteSensitive = collect($issued->points_snapshot ?? [])->filter(fn ($p) => ! empty($p['site_sensitive']))->values();
        if ($siteSensitive->isEmpty()) {
            return back()->with('error', 'Este permiso no tiene puntos sensibles al sitio que reverificar.');
        }

        $data = $request->validate([
            'new_site_label' => 'required|string|max:255',
            'answers'        => 'required|array|min:1',
            'answers.*'      => 'in:cumple,no_cumple',
        ]);

        $result = 'ok';
        $codes  = [];
        foreach ($siteSensitive as $p) {
            $codes[] = $p['code'];
            $ans = $data['answers'][$p['code']] ?? null;
            if ($ans === null) {
                return back()->withInput()->with('error', "Falta reverificar el punto {$p['code']}.");
            }
            if ($ans === 'no_cumple') { $result = 'fail'; }
        }

        $author = auth()->user();
        $log = $issued->reverifications ?? [];
        $log[] = [
            'at'         => now()->format('Y-m-d H:i:s'),
            'by_id'      => $author ? $author->id : null,
            'by_name'    => $author ? $author->fullName() : null,
            'site_label' => trim($data['new_site_label']),
            'points'     => $codes,
            'result'     => $result,
        ];
        // Columna hash-excluida → sin re-sellado (reverificar no toca la integridad de la emisión).
        $issued->reverifications = $log;
        $issued->save();

        if ($result === 'fail') {
            return redirect()->route('permits.show', $issued->uuid)->with('error',
                'La reverificación FALLÓ en el nuevo sitio: no procede aquí. Suspende el permiso o emite uno nuevo.');
        }
        return redirect()->route('permits.show', $issued->uuid)->with('success',
            'Reverificación registrada para “'.trim($data['new_site_label']).'”. El permiso sigue vigente en este sitio.');
    }

    /* ============================ CIERRE (Paso 4) ============================ */

    /**
     * Cerrar el permiso (acto con autor y hora). TRABAJO EN CALIENTE: exige declarar cumplida la
     * vigilancia posterior (fire watch). Condiciones que quedan pendientes → ACTION ITEM (PDCA
     * existente), no seguimiento paralelo.
     */
    public function close(Request $request, IssuedPermit $issued)
    {
        if ($issued->isClosed()) {
            return back()->with('error', 'Este permiso ya está cerrado.');
        }
        if ($issued->isSuspended()) {
            return back()->with('error', 'Este permiso está suspendido. Reanúdalo antes de cerrarlo, o emite uno nuevo.');
        }

        $data = $request->validate([
            'close_notes'          => 'nullable|string|max:2000',
            'fire_watch_confirmed' => 'nullable|boolean',
            'pending_conditions'   => 'nullable|string|max:2000',
        ]);

        // Trabajo en caliente: la vigilancia posterior es PARTE del permiso, no un extra.
        if ($issued->requires_fire_watch && empty($data['fire_watch_confirmed'])) {
            return back()->withInput()->with('error',
                'TRABAJO EN CALIENTE: para cerrar hay que declarar cumplida la vigilancia posterior a la actividad.');
        }

        $author = auth()->user();
        $issued->fire_watch_confirmed = $issued->requires_fire_watch ? true : (bool) ($data['fire_watch_confirmed'] ?? false);
        $issued->closed_at      = now();
        $issued->closed_by_id   = $author ? $author->id : null;
        $issued->closed_by_name = $author ? $author->fullName() : null;
        $issued->close_notes    = $data['close_notes'] ?? null;
        $issued->save();

        // Condiciones pendientes → action item PDCA (idempotente por source_field).
        if (! empty($data['pending_conditions']) && Schema::hasTable('action_items')) {
            $text = 'Permiso '.$issued->folio().' cerrado con condiciones pendientes: '.trim($data['pending_conditions']);
            $issued->syncAutoActionItem($text, 'permit_close_pending', $author ? $author->id : null, now()->endOfDay());
        }

        return redirect()->route('permits.show', $issued->uuid)
            ->with('success', 'Permiso cerrado. El sello sigue siendo válido; solo cambió el estado a cerrado.');
    }

    /* ============================ SUSPENSIÓN (Paso 4) ============================ */

    /**
     * Suspender antes de cerrar (cambió el clima, entró personal a la zona). Suspender NO es
     * cerrar y NO es alterar: columnas hash-excluidas, sin re-sellado (patrón de retiro de #42).
     */
    public function suspend(Request $request, IssuedPermit $issued)
    {
        if (! $issued->isOpen()) {
            return back()->with('error', 'Solo un permiso abierto se suspende.');
        }
        $data = $request->validate([
            'suspended_reason' => 'required|string|max:255',
        ]);
        $author = auth()->user();
        $issued->suspended_at     = now();
        $issued->suspended_by_id  = $author ? $author->id : null;
        $issued->suspended_reason = trim($data['suspended_reason']);
        $issued->save();

        return redirect()->route('permits.show', $issued->uuid)
            ->with('success', 'Permiso suspendido. No está cerrado ni alterado: el sello sigue válido, cambió el estado.');
    }

    /* ============================ Helpers ============================ */

    /**
     * ¿El permiso implica TRABAJO EN CALIENTE (vigilancia posterior obligatoria)? Se deriva de la
     * familia/clave/nombre del catálogo y se CONGELA al emitir. Generoso a propósito: pedir de más
     * la declaración de vigilancia es seguro; omitirla no.
     */
    private function deriveFireWatch(Permit $permit): bool
    {
        $hay = mb_strtolower(trim(($permit->family ?? '').' '.($permit->permit_key ?? '').' '.($permit->name ?? '').' '.($permit->definition ?? '')));
        foreach (['calor', 'fuego', 'flama', 'caliente', 'soldad', 'pirotec', 'chispa', 'corte y soldadura'] as $needle) {
            if (mb_strpos($hay, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private function currentShootDay()
    {
        try {
            return ProductionCalendar::shootDayFor(now()->toDateString());
        } catch (\Throwable $e) {
            return null;
        }
    }
}
