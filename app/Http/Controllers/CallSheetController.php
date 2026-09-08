<?php

namespace App\Http\Controllers;

use App\Models\CallDay;
use App\Models\CallDayMeal;
use App\Models\CallDeptOffset;
use App\Models\CallPackage;
use App\Models\CallPersonSchedule;
use App\Models\CallPlace;
use App\Models\Department;
use App\Models\FileDelivery;
use App\Models\FileDeliveryRecipient;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\CallPackageAssembler;
use App\Support\CallSheetEngine;
use App\Support\CallSheetFormats;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use App\Support\DayRosterBuilder;
use App\Support\Features;
use App\Support\FileDeliveryDispatcher;
use App\Support\ProductionCalendar;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * CallSheetController — PARTES E/F. Las 3 pantallas para armar el BACK del llamado + la exportación.
 *
 * Gate `callsheet.manage` (super-admin/line-producer/coordinator): es una herramienta de OFICINA DE
 * PRODUCCIÓN. El HOD usa el roster de solo-lectura; medic/safety/auditor tienen all-departments pero
 * NO arman llamados. El motor ({@see CallSheetEngine}) guarda todo como OFFSET del general.
 */
class CallSheetController extends Controller
{
    /** /llamado → hoy. */
    public function landing()
    {
        return redirect()->route('callsheet.config', ['date' => Carbon::today()->toDateString()]);
    }

    // ============================ PANTALLA 1 · CONFIG DEL LLAMADO ============================

    public function config($date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404, 'No hay producción vigente.');
        $day     = $this->parseDate($date);
        $callDay = $this->resolveCallDay($day, $pid);

        // Notas globales de la producción (se editan aquí mismo, antes de la firma).
        $prod = CurrentProduction::get();
        $s = ($prod && is_array($prod->settings)) ? $prod->settings : [];

        return view('admin.callsheet.config', [
            'day'           => $day,
            'dayLabel'      => ProductionCalendar::dayLabelWithTotal($day),
            'callDay'       => $callDay,
            'meals'         => $callDay->meals,
            'places'        => $this->activePlaces($pid),
            'notes'         => $s['callsheet_notes'] ?? '',
            'safetyBar'     => $s['callsheet_safety_bar'] ?? '',
            'hotelsNote'    => $s['callsheet_hotels'] ?? '',
            'emergNote'     => $s['callsheet_emergency'] ?? '',
            'nav'           => $this->nav($day),
        ]);
    }

    public function saveConfig(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day     = $this->parseDate($date);
        $callDay = $this->resolveCallDay($day, $pid);

        $data = $request->validate([
            'general_call'    => ['nullable', 'date_format:H:i'],
            'wrap_time'       => ['nullable', 'date_format:H:i'],
            'cast_count'      => ['nullable', 'integer', 'min:0', 'max:100000'],
            'bg_count'        => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sign_enabled'    => ['nullable', 'boolean'],
            'meals'           => ['nullable', 'array'],
            'meals.*.id'      => ['nullable', 'integer'],
            'meals.*.label'   => ['nullable', 'string', 'max:80'],
            'meals.*.enabled' => ['nullable', 'boolean'],
            'meals.*.time'    => ['nullable', 'date_format:H:i'],
            'meals.*.place_id'=> ['nullable', 'integer'],
            'notes'           => ['nullable', 'string', 'max:10000'],
            'safety_bar'      => ['nullable', 'string', 'max:500'],
            'hotels_note'     => ['nullable', 'string', 'max:2000'],
            'emergency_note'  => ['nullable', 'string', 'max:2000'],
        ]);

        // Notas globales (compartidas por todos los backs): se editan en la misma pantalla. El FORMATO
        // del back NO se toca aquí — vive en su propia pantalla ({@see format()}), se elige una vez.
        $prod = CurrentProduction::get();
        if ($prod) {
            $ps = is_array($prod->settings) ? $prod->settings : [];
            $ps['callsheet_notes']      = ($data['notes'] ?? '') !== '' ? $data['notes'] : null;
            $ps['callsheet_safety_bar'] = ($data['safety_bar'] ?? '') !== '' ? $data['safety_bar'] : null;
            $ps['callsheet_hotels']     = ($data['hotels_note'] ?? '') !== '' ? $data['hotels_note'] : null;
            $ps['callsheet_emergency']  = ($data['emergency_note'] ?? '') !== '' ? $data['emergency_note'] : null;
            $prod->settings = $ps;
            $prod->save();
            CurrentProduction::forget();
        }

        // Wrap estimado: SÓLO si el flag está encendido; vive en footer_extra (sin esquema nuevo).
        $footer = is_array($callDay->footer_extra) ? $callDay->footer_extra : [];
        if (Features::enabled('callsheet_wrap_estimate') && ! empty($data['wrap_time'])) {
            $footer['wrap_time'] = $data['wrap_time'];
        } else {
            unset($footer['wrap_time']);
        }

        $callDay->fill([
            'footer_extra' => $footer ?: null,
            'general_call' => $data['general_call'] ?? null,
            'cast_count'   => $data['cast_count'] ?? null,
            'bg_count'     => $data['bg_count'] ?? null,
            'sign_enabled' => (bool) ($data['sign_enabled'] ?? false),
        ])->save();

        // Comidas: la hora se guarda como OFFSET del general (se mueve con él). Card vacía = borrar.
        $general = $callDay->generalHHMM();
        $sort = 0;
        foreach ($data['meals'] ?? [] as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                if (! empty($row['id'])) {
                    CallDayMeal::where('call_day_id', $callDay->id)->where('id', $row['id'])->delete();
                }
                continue;
            }
            $offset = null; $explicit = null;
            if (! empty($row['time'])) {
                if ($general) {
                    $offset = CallSheetEngine::minutesFrom($general, $row['time']);
                } else {
                    $explicit = $row['time'];
                }
            }
            $attrs = [
                'sort_order'     => $sort++,
                'label'          => $label,
                'enabled'        => (bool) ($row['enabled'] ?? false),
                'offset_minutes' => $offset,
                'explicit_time'  => $explicit,
                'place_id'       => ! empty($row['place_id']) ? (int) $row['place_id'] : null,
            ];
            if (! empty($row['id'])) {
                CallDayMeal::where('call_day_id', $callDay->id)->where('id', $row['id'])->update($attrs);
            } else {
                $callDay->meals()->create($attrs);
            }
        }

        // Sin comidas y con general → auto-establecer el set según la hora (Café/Craft siempre).
        if ($general && $callDay->meals()->count() === 0) {
            $this->establishMeals($callDay, $general);
        }

        return redirect()->route('callsheet.config', ['date' => $day->toDateString()])
            ->with('success', 'Configuración del llamado guardada.');
    }

    /** Re-establece las comidas automáticas por la hora del general (botón "restablecer comidas"). */
    public function regenMeals(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day     = $this->parseDate($date);
        $callDay = $this->resolveCallDay($day, $pid);

        // Usa el general que el usuario tiene en pantalla (aunque no lo haya guardado) y lo persiste.
        $request->validate(['general_call' => ['nullable', 'date_format:H:i']]);
        $general = $request->input('general_call') ?: $callDay->generalHHMM();
        if (! $general) {
            return back()->with('warning', 'Primero define el llamado general.');
        }
        if ($request->filled('general_call') && $request->input('general_call') !== $callDay->generalHHMM()) {
            $callDay->general_call = $request->input('general_call');
            $callDay->save();
        }
        $this->establishMeals($callDay, $general);

        return redirect()->route('callsheet.config', ['date' => $day->toDateString()])
            ->with('success', 'Comidas restablecidas según el general.');
    }

    // ============================ NOTAS GLOBALES (por producción, persisten) ============================

    public function notes()
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);
        $s = is_array($prod->settings) ? $prod->settings : [];

        return view('admin.callsheet.notes', [
            'notes'     => $s['callsheet_notes'] ?? '',
            'safetyBar' => $s['callsheet_safety_bar'] ?? '',
        ]);
    }

    public function saveNotes(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);
        $data = $request->validate([
            'notes'      => ['nullable', 'string', 'max:10000'],
            'safety_bar' => ['nullable', 'string', 'max:500'],
        ]);
        $s = is_array($prod->settings) ? $prod->settings : [];
        $s['callsheet_notes']      = $data['notes'] ?: null;
        $s['callsheet_safety_bar'] = $data['safety_bar'] ?: null;
        $prod->settings = $s;
        $prod->save();
        CurrentProduction::forget();

        return redirect()->route('callsheet.notes')->with('success', 'Notas del llamado guardadas.');
    }

    // ============================ FORMATO DEL BACK (preset, se elige UNA vez) ============================

    /** Pantalla aparte: tarjetas visuales (mini-back) para elegir la plantilla del back. */
    public function format()
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);
        $s = is_array($prod->settings) ? $prod->settings : [];

        $cards = [];
        foreach (array_keys(CallSheetFormats::PRESETS) as $key) {
            $p = CallSheetFormats::resolve($key);
            $cards[$key] = ['preset' => $p, 'cols' => CallSheetFormats::tableColumns($p)];
        }

        return view('admin.callsheet.format', [
            'cards'   => $cards,
            'current' => $s['callsheet_preset'] ?? CallSheetFormats::DEFAULT,
            'paper'   => $s['callsheet_paper'] ?? 'legal',
            'bands'   => $s['callsheet_bands'] ?? 'gray',
            // Figuras que aprueban el back: etiqueta de fábrica + a quién se fijó (o auto por puesto).
            'signerRoles' => self::SIGNER_ROLES,
            'signerPicks' => $this->signerPicks(),
            'signerAuto'  => $this->autoSignerNames(),
            'crew'        => $this->productionPeople(),
        ]);
    }

    public function saveFormat(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);
        $data = $request->validate([
            'preset' => ['required', 'string', 'in:' . implode(',', array_keys(CallSheetFormats::PRESETS))],
            'paper'  => ['nullable', 'string', 'in:' . implode(',', array_keys(CallSheetFormats::PAPERS))],
            'bands'  => ['nullable', 'string', 'in:' . implode(',', array_keys(CallSheetFormats::BANDS))],
            'signers'   => ['nullable', 'array'],
            'signers.*' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $s = is_array($prod->settings) ? $prod->settings : [];
        $s['callsheet_preset'] = $data['preset'];
        $s['callsheet_paper']  = $data['paper'] ?? 'legal';
        $s['callsheet_bands']  = $data['bands'] ?? 'gray';

        // Figuras de aprobación: solo las 3 keys conocidas; vacío = AUTO (se resuelve por puesto).
        $picks = [];
        foreach (array_keys(self::SIGNER_ROLES) as $key) {
            $uid = $data['signers'][$key] ?? null;
            if ($uid) {
                $picks[$key] = (int) $uid;
            }
        }
        $s['callsheet_signers'] = $picks;

        $prod->settings = $s;
        $prod->save();
        CurrentProduction::forget();

        return redirect()->route('callsheet.format')->with('success', 'Formato del back guardado.');
    }

    // ============================ PAQUETE DEL LLAMADO (front + back → firma → envío) ============================

    /** Pantalla del paquete del día: subir front, unir con el back, estado y firmas. */
    public function package(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);

        // Si ya estaba aprobado y el horario cambió → estado "con cambios" (se pintan en azul en el back).
        $changedCount = 0;
        if ($pkg->status === CallPackage::APPROVED && $pkg->frozen_schedule) {
            $changedCount = count($this->changedKeys($this->scheduleSnapshot($request->user(), $day), $pkg->frozen_schedule));
            if ($changedCount > 0) {
                $pkg->status = CallPackage::CHANGED;
                $pkg->save();
            }
        } elseif ($pkg->status === CallPackage::CHANGED && $pkg->frozen_schedule) {
            $changedCount = count($this->changedKeys($this->scheduleSnapshot($request->user(), $day), $pkg->frozen_schedule));
        }

        $delivery = $this->latestDelivery($pkg);

        return view('admin.callsheet.package', [
            'day'          => $day,
            'dayLabel'     => ProductionCalendar::dayLabelWithTotal($day),
            'pkg'          => $pkg,
            'signers'      => $pkg->signers ?? [],
            'sigByKey'     => $pkg->signatures->keyBy('signer_key'),
            'changedCount' => $changedCount,
            'extraEnabled' => Features::enabled('callsheet_extra_docs') && Schema::hasColumn('call_packages', 'extra_docs'),
            'extraDocs'    => $pkg->extra_docs ?? [],
            'delivery'     => $delivery,
            'deliveryCounts' => $delivery ? $delivery->counts() : null,
            'crewCount'    => $pkg->status === CallPackage::APPROVED ? count($this->calledCrewRecipients($request->user(), $day)) : null,
            'nav'          => $this->nav($day),
        ]);
    }

    /**
     * El CallPackage del día (lo crea con firmantes + posición de firmas RECORDADA la 1ª vez).
     *
     * Mientras el paquete siga en BORRADOR los firmantes se RE-RESUELVEN en cada visita: así se
     * refleja a quien fijaste en Formato del back o a quien entró al roster después de crear el
     * paquete. Al mandarlo a aprobación (pending/changed/approved) quedan CONGELADOS — ya hay
     * filas de firma colgando de cada key.
     */
    private function resolvePackage(User $user, Carbon $day, int $pid): CallPackage
    {
        $pkg = CallPackage::firstOrNew(['production_id' => $pid, 'call_date' => $day->toDateString()]);
        if (! $pkg->exists) {
            $engine = CallSheetEngine::forDay($user, $day, true);
            $pkg->signers = $this->packageSigners($engine, false);
            $prod = CurrentProduction::get();
            $ps   = ($prod && is_array($prod->settings)) ? $prod->settings : [];
            $pkg->sign_field_map = $ps['callsheet_sign_layout'] ?? null;   // recuerda la posición de la producción
            $pkg->status = CallPackage::DRAFT;
            $pkg->save();
        } elseif ($pkg->status === CallPackage::DRAFT) {
            $fresh = $this->packageSigners(CallSheetEngine::forDay($user, $day, true), false);
            if ($fresh !== ($pkg->signers ?? [])) {
                $pkg->signers = $fresh;
                $pkg->save();
            }
        }
        $pkg->load('signatures');

        return $pkg;
    }

    /** Sirve el PDF del front (para pdf.js en la pantalla de colocar firmas). */
    public function frontFile(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        abort_unless($pkg->front_path && Storage::disk('local')->exists($pkg->front_path), 404);

        return $this->pdfResponse(Storage::disk('local')->get($pkg->front_path), 'front-' . $day->toDateString() . '.pdf');
    }

    /** Pantalla para COLOCAR las 3 firmas sobre el front (arrastrar; se recuerda por producción). */
    public function signLayout(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        abort_unless($pkg->front_path, 404);

        return view('admin.callsheet.package-layout', [
            'day'      => $day,
            'dayLabel' => ProductionCalendar::dayLabelWithTotal($day),
            'pkg'      => $pkg,
            'signers'  => $pkg->signers ?? [],
            'nav'      => $this->nav($day),
        ]);
    }

    /** Guarda la posición de las firmas en el paquete + la RECUERDA a nivel producción. */
    public function saveSignLayout(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);

        $data = $request->validate(['field_map' => ['nullable', 'string', 'max:20000']]);
        $map  = json_decode($data['field_map'] ?? '[]', true) ?: [];

        $clean = [];
        foreach ($map as $f) {
            if (($f['type'] ?? '') !== 'sign') { continue; }
            $clean[] = [
                'page'  => max(1, (int) ($f['page'] ?? 1)),
                'x_pct' => (float) ($f['x_pct'] ?? 0),
                'y_pct' => (float) ($f['y_pct'] ?? 0),
                'w_pct' => (float) ($f['w_pct'] ?? 24),
                'type'  => 'sign',
                'key'   => (string) ($f['key'] ?? ''),
            ];
        }

        $pkg->sign_field_map = $clean;
        $pkg->save();

        $prod = CurrentProduction::get();
        if ($prod) {
            $ps = is_array($prod->settings) ? $prod->settings : [];
            $ps['callsheet_sign_layout'] = $clean;
            $prod->settings = $ps;
            $prod->save();
            CurrentProduction::forget();
        }

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])
            ->with('success', 'Posición de las firmas guardada.');
    }

    /** Sube el FRONT (PDF externo del 2nd AD). Reemplaza el anterior si había; vuelve a borrador. */
    public function uploadFront(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $request->validate(['front' => ['required', 'file', 'mimetypes:application/pdf', 'max:30720']]);   // 30 MB

        $pkg  = $this->resolvePackage($request->user(), $day, $pid);
        $path = $request->file('front')->storeAs('call-packages/' . $pid, $day->toDateString() . '-front.pdf', 'local');

        $pages = CallPackageAssembler::pageCount(Storage::disk('local')->get($path));

        $pkg->front_path  = $path;
        $pkg->front_pages = $pages ?: null;
        $pkg->status      = CallPackage::DRAFT;   // front nuevo → hay que re-armar/re-aprobar
        $pkg->save();

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])->with('success', 'Front subido.');
    }

    /** Quita el front. */
    public function removeFront(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        if ($pkg->front_path) {
            Storage::disk('local')->delete($pkg->front_path);
            $pkg->front_path = null;
            $pkg->front_pages = null;
            $pkg->status = CallPackage::DRAFT;
            $pkg->save();
        }

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])->with('success', 'Front quitado.');
    }

    /** Sube un PDF ADICIONAL (se une después del back). Detrás del flag `callsheet_extra_docs`. */
    public function uploadExtra(Request $request, $date)
    {
        abort_unless(Features::enabled('callsheet_extra_docs'), 404);
        abort_unless(Schema::hasColumn('call_packages', 'extra_docs'), 404);   // sin la columna, no se ofrece
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $request->validate(['extra' => ['required', 'file', 'mimetypes:application/pdf', 'max:30720']]);   // 30 MB

        $pkg  = $this->resolvePackage($request->user(), $day, $pid);
        $docs = $pkg->extra_docs ?? [];
        $n    = count($docs) + 1;
        $path = $request->file('extra')->storeAs('call-packages/' . $pid, $day->toDateString() . '-extra-' . $n . '.pdf', 'local');

        $docs[] = [
            'path'  => $path,
            'name'  => $request->file('extra')->getClientOriginalName() ?: ('Adicional ' . $n),
            'pages' => CallPackageAssembler::pageCount(Storage::disk('local')->get($path)) ?: null,
        ];
        $pkg->extra_docs = array_values($docs);
        $pkg->status     = CallPackage::DRAFT;   // paquete nuevo → re-armar/re-aprobar
        $pkg->save();

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])->with('success', 'Documento adicional agregado.');
    }

    /** Quita un PDF adicional por índice. */
    public function removeExtra(Request $request, $date)
    {
        abort_unless(Features::enabled('callsheet_extra_docs'), 404);
        abort_unless(Schema::hasColumn('call_packages', 'extra_docs'), 404);
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);

        $i    = (int) $request->input('i', -1);
        $docs = $pkg->extra_docs ?? [];
        if (isset($docs[$i])) {
            if (! empty($docs[$i]['path'])) {
                Storage::disk('local')->delete($docs[$i]['path']);
            }
            unset($docs[$i]);
            $pkg->extra_docs = array_values($docs);
            $pkg->status     = CallPackage::DRAFT;
            $pkg->save();
        }

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])->with('success', 'Documento adicional quitado.');
    }

    /** Previsualiza el PAQUETE unido (front + back). Si ya está aprobado, sirve el congelado. */
    public function packageMerged(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);

        if ($pkg->status === CallPackage::APPROVED && $pkg->frozen_path && Storage::disk('local')->exists($pkg->frozen_path)) {
            return $this->pdfResponse(Storage::disk('local')->get($pkg->frozen_path), 'paquete-' . $day->toDateString() . '.pdf');
        }

        return $this->pdfResponse($this->buildMergedPdf($request->user(), $day, $pkg), 'paquete-' . $day->toDateString() . '.pdf');
    }

    /** Descarga SOLO el back (modo alterno: armar aquí, ensamblar en Scenechronize). */
    public function downloadBack(Request $request, $date)
    {
        $day = $this->parseDate($date);

        return $this->pdfResponse($this->renderBackPdf($request->user(), $day, []), 'back-' . $day->toDateString() . '.pdf', 'attachment');
    }

    /** Une [front (estampado si hay firmas) → back → adicionales] en un PDF. Sin más partes → solo el back. */
    private function buildMergedPdf(User $user, Carbon $day, CallPackage $pkg): string
    {
        $hasFront = $pkg->front_path && Storage::disk('local')->exists($pkg->front_path);
        $backPdf  = $this->renderBackPdf($user, $day, ['hideSign' => $hasFront]);   // firma va en el front

        $parts = [];
        if ($hasFront) {
            $frontBytes = Storage::disk('local')->get($pkg->front_path);
            $sigMap     = $this->signatureMapFor($pkg);
            if ($sigMap && $pkg->sign_field_map) {
                $frontBytes = CallPackageAssembler::stampFront($frontBytes, $pkg->sign_field_map, $sigMap);
            }
            $parts[] = $frontBytes;
        }

        $parts[] = $backPdf;

        // PDF(s) adicional(es) DESPUÉS del back (detrás del flag). Cada uno se une tal cual.
        if (Features::enabled('callsheet_extra_docs')) {
            foreach ($this->extraDocPaths($pkg) as $path) {
                $parts[] = Storage::disk('local')->get($path);
            }
        }

        return count($parts) === 1 ? $parts[0] : CallPackageAssembler::merge($parts);
    }

    /** Rutas existentes en disco de los PDF adicionales del paquete (en orden). */
    private function extraDocPaths(CallPackage $pkg): array
    {
        $out = [];
        foreach (($pkg->extra_docs ?? []) as $doc) {
            $path = $doc['path'] ?? null;
            if ($path && Storage::disk('local')->exists($path)) {
                $out[] = $path;
            }
        }

        return $out;
    }

    /** [signer_key => ['image'=>dataURI, 'signer'=>rol]] de las firmas ya puestas. */
    private function signatureMapFor(CallPackage $pkg): array
    {
        $out = [];
        foreach ($pkg->signatures as $sig) {
            if ($sig->signed_at && $sig->rubrica_image) {
                $out[$sig->signer_key] = ['image' => $sig->rubrica_image, 'signer' => $sig->role_label];
            }
        }

        return $out;
    }

    private function pdfResponse(string $bytes, string $filename, string $disp = 'inline')
    {
        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disp . '; filename="' . $filename . '"',
        ]);
    }

    // ---------------------------- F2 · Firma de aprobación ----------------------------

    /** Envía el paquete a aprobación: crea las filas de firma y pone estado "pendiente". */
    public function sendForApproval(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);

        if (! $pkg->front_path) {
            return back()->with('warning', 'Sube el front antes de mandar a aprobación.');
        }
        if (empty($pkg->sign_field_map)) {
            return back()->with('warning', 'Coloca dónde firma cada figura antes de mandar a aprobación.');
        }

        foreach (($pkg->signers ?? []) as $sg) {
            \App\Models\CallPackageSignature::firstOrCreate(
                ['call_package_id' => $pkg->id, 'signer_key' => $sg['key']],
                ['user_id' => $sg['user_id'] ?? null, 'role_label' => $sg['role_label'] ?? null]
            );
        }
        $pkg->status = CallPackage::PENDING;
        $pkg->save();

        $this->notifySigners($pkg, $day);   // aviso (best-effort)

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])
            ->with('success', 'Paquete enviado a aprobación. Las 3 figuras pueden firmar.');
    }

    /** Pantalla de firma para una figura (rúbrica sobre el paquete). */
    public function signScreen(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        abort_unless(in_array($pkg->status, [CallPackage::PENDING, CallPackage::CHANGED], true), 404);

        $signable = $this->signableKeys($request->user(), $pkg);

        return view('admin.callsheet.package-sign', [
            'day'      => $day,
            'dayLabel' => ProductionCalendar::dayLabelWithTotal($day),
            'pkg'      => $pkg,
            'signers'  => $pkg->signers ?? [],
            'sigByKey' => $pkg->signatures->keyBy('signer_key'),
            'signable' => $signable,
            'adopted'  => optional($request->user())->adopted_signature,
            'nav'      => $this->nav($day),
        ]);
    }

    /** Recibe la rúbrica de una figura; si ya firmaron todas → congela. */
    public function sign(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        abort_unless(in_array($pkg->status, [CallPackage::PENDING, CallPackage::CHANGED], true), 404);

        $data = $request->validate([
            'signer_key'      => ['required', 'string', 'max:40'],
            'signature_image' => ['required', 'string'],
            'save_signature'  => ['nullable', 'boolean'],
        ]);
        abort_unless(in_array($data['signer_key'], $this->signableKeys($request->user(), $pkg), true), 403);

        $sg  = collect($pkg->signers ?? [])->firstWhere('key', $data['signer_key']) ?? [];
        \App\Models\CallPackageSignature::updateOrCreate(
            ['call_package_id' => $pkg->id, 'signer_key' => $data['signer_key']],
            [
                'user_id'       => $request->user()->id,
                'role_label'    => $sg['role_label'] ?? null,
                'rubrica_image' => $data['signature_image'],
                'signed_at'     => now(),
            ]
        );

        // Guardar la firma para reúso (tipo DocuSign).
        if (! empty($data['save_signature']) && $request->user()) {
            $request->user()->adopted_signature = $data['signature_image'];
            $request->user()->save();
        }

        $pkg->load('signatures');
        if ($pkg->allSigned()) {
            $this->freezePackage($request->user(), $day, $pkg);
            return redirect()->route('callsheet.package', ['date' => $day->toDateString()])
                ->with('success', 'Firmaron las 3 figuras — paquete APROBADO y congelado.');
        }

        return redirect()->route('callsheet.package.sign', ['date' => $day->toDateString()])
            ->with('success', 'Firma registrada. Faltan las demás.');
    }

    /** Estado en vivo del paquete (para polling). */
    public function packageState(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        $byKey = $pkg->signatures->keyBy('signer_key');

        $signers = [];
        foreach (($pkg->signers ?? []) as $sg) {
            $sig = $byKey[$sg['key']] ?? null;
            $signers[] = [
                'key'    => $sg['key'],
                'role'   => $sg['role_label'] ?? '',
                'name'   => $sg['name'] ?? '',
                'signed' => (bool) ($sig && $sig->signed_at),
                'at'     => $sig && $sig->signed_at ? $sig->signed_at->isoFormat('HH:mm') : null,
            ];
        }

        return response()->json([
            'status'     => $pkg->status,
            'all_signed' => $pkg->allSigned(),
            'signers'    => $signers,
        ]);
    }

    /** Reabre el paquete a borrador (para corregir antes de re-enviar). */
    public function reopenPackage(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        $pkg->signatures()->update(['rubrica_image' => null, 'signed_at' => null]);
        $pkg->status = CallPackage::DRAFT;
        $pkg->approved_at = null;
        $pkg->save();

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])->with('success', 'Paquete reabierto para corregir.');
    }

    /** Congela: arma el paquete firmado, lo guarda y captura el horario (para detectar cambios). */
    private function freezePackage(User $user, Carbon $day, CallPackage $pkg): void
    {
        $merged = $this->buildMergedPdf($user, $day, $pkg);
        $path   = 'call-packages/' . $pkg->production_id . '/' . $day->toDateString() . '-aprobado.pdf';
        Storage::disk('local')->put($path, $merged);

        $pkg->frozen_path      = $path;
        $pkg->frozen_schedule  = $this->scheduleSnapshot($user, $day);
        $pkg->status           = CallPackage::APPROVED;
        $pkg->approved_at      = now();
        $pkg->save();
    }

    /** Foto del horario por persona (user_id => hora efectiva) para comparar cambios post-aprobación. */
    private function scheduleSnapshot(User $user, Carbon $day): array
    {
        return $this->snapshotFromEngine(CallSheetEngine::forDay($user, $day, true));
    }

    /** Misma foto pero desde un engine ya construido (evita reconstruirlo en el render del back). */
    private function snapshotFromEngine(array $engine): array
    {
        $snap = [];
        foreach ($engine['groups'] as $g) {
            foreach ($g['people'] as $p) {
                if ($p['state'] === PayeeContract::ROSTER_OUT) { continue; }
                $sched = $p['schedule'];
                $snap[(string) ($p['user_id'] ?? '')] = (string) ($sched['literal'] ?? $sched['time'] ?? '');
            }
        }

        return $snap;
    }

    /** [user_id => true] de quienes su hora cambió respecto al congelado (para pintar en azul). */
    private function changedKeys(array $now, array $frozen): array
    {
        $chg = [];
        foreach ($now as $uid => $time) {
            if ($uid !== '' && array_key_exists($uid, $frozen) && (string) $frozen[$uid] !== (string) $time) {
                $chg[$uid] = true;
            }
        }

        return $chg;
    }

    /** El paquete aprobado/congelado de ese día (para detectar cambios), o null. */
    private function frozenPackageFor(int $pid, Carbon $day): ?CallPackage
    {
        $pkg = CallPackage::where('production_id', $pid)->whereDate('call_date', $day->toDateString())->first();

        return ($pkg && $pkg->frozen_schedule && in_array($pkg->status, [CallPackage::APPROVED, CallPackage::CHANGED], true)) ? $pkg : null;
    }

    /** Qué firmas puede poner el usuario: la suya (por user_id) o cualquiera si tiene callsheet.manage. */
    private function signableKeys(User $user, CallPackage $pkg): array
    {
        $office = $user->can('callsheet.manage');
        $keys = [];
        foreach (($pkg->signers ?? []) as $sg) {
            if ($office || (isset($sg['user_id']) && (int) $sg['user_id'] === (int) $user->id)) {
                $keys[] = $sg['key'];
            }
        }

        return $keys;
    }

    // ---------------------------- F4 · Envío al crew ----------------------------

    /**
     * ENCOLA el paquete APROBADO al crew llamado y arranca una RÁFAGA inline (unos cuantos salen ya;
     * el resto lo drena el cron). NO manda síncrono → el request no se bloquea ni se pierde nada. Cada
     * copia lleva el nombre en créditos de quien la recibe (marca de agua). Reenviar = nuevo lote.
     */
    public function sendToCrew(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);

        if ($pkg->status !== CallPackage::APPROVED || ! $pkg->frozen_path || ! Storage::disk('local')->exists($pkg->frozen_path)) {
            return back()->with('warning', 'El paquete debe estar aprobado para enviarlo.');
        }
        if (! Schema::hasTable('file_deliveries') || ! Schema::hasTable('file_delivery_recipients')) {
            return back()->with('warning', 'El envío no está disponible en esta instancia — falta aplicar la actualización de base de datos (2026-08-24-file-deliveries.sql).');
        }

        $recips  = $this->calledCrewRecipients($request->user(), $day);
        $withMail = array_values(array_filter($recips, fn ($r) => ! empty($r['email'])));
        $noMail   = count($recips) - count($withMail);

        if (empty($withMail)) {
            return back()->with('warning', 'Nadie del crew llamado tiene correo registrado.');
        }

        $prodName = optional(CurrentProduction::get())->name;
        $delivery = FileDelivery::create([
            'production_id' => $pid,
            'created_by'    => $request->user()->id,
            'source_type'   => FileDelivery::SOURCE_CALL_PACKAGE,
            'source_id'     => $pkg->id,
            'title'         => trim(($prodName ? $prodName . ' · ' : '') . 'Llamado ' . $day->isoFormat('D MMM YYYY')),
            'body'          => 'Adjuntamos el llamado del ' . $day->isoFormat('dddd D [de] MMMM') . '. Es tu copia personal.',
            'base_path'     => $pkg->frozen_path,
            'base_name'     => 'llamado-' . $day->toDateString() . '.pdf',
            'watermark'     => true,
        ]);

        $this->enqueueRecipients($delivery, $withMail);

        // Ráfaga inline: unos cuantos salen al instante; el cron termina el resto (fluido, sin bloquear).
        $burst = FileDeliveryDispatcher::drain(20, 12.0);

        $msg = "Envío encolado a {$delivery->recipients()->count()} persona(s) del crew llamado";
        $msg .= $burst['sent'] > 0 ? " — {$burst['sent']} ya salieron; el resto sale solo en unos minutos." : ' — saldrán en unos minutos.';
        if ($noMail > 0) {
            $msg .= " ({$noMail} sin correo, no se les envió.)";
        }

        return redirect()->route('callsheet.package', ['date' => $day->toDateString()])->with('success', $msg);
    }

    /** Estado del último envío del paquete (para polling en la pantalla). */
    public function packageDeliveryState(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);
        $pkg = $this->resolvePackage($request->user(), $day, $pid);
        $delivery = $this->latestDelivery($pkg);

        return response()->json($delivery ? $delivery->counts() + [
            'exists'  => true,
            'sent_at' => optional($delivery->recipients()->whereNotNull('sent_at')->latest('sent_at')->first())->sent_at?->isoFormat('D MMM HH:mm'),
        ] : ['exists' => false]);
    }

    /** Crea las filas de destinatario (bulk) para un envío. */
    private function enqueueRecipients(FileDelivery $delivery, array $recips): void
    {
        $now  = now();
        $rows = [];
        foreach ($recips as $r) {
            $rows[] = [
                'file_delivery_id' => $delivery->id,
                'user_id'          => $r['user_id'] ?? null,
                'name'             => $r['name'] ?? null,
                'email'            => $r['email'],
                'watermark_text'   => $r['name'] ?? null,   // nombre en créditos
                'status'           => FileDeliveryRecipient::PENDING,
                'attempts'         => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            FileDeliveryRecipient::insert($chunk);
        }
    }

    /** El último envío (lote) de este paquete, o null. DEFENSIVO: sin la tabla, la pantalla no truena. */
    private function latestDelivery(CallPackage $pkg): ?FileDelivery
    {
        if (! Schema::hasTable('file_deliveries')) {
            return null;
        }

        return FileDelivery::where('source_type', FileDelivery::SOURCE_CALL_PACKAGE)
            ->where('source_id', $pkg->id)->latest('id')->first();
    }

    /** Crew LLAMADO (called/pendiente de firma) del día → [user_id, name(créditos), email]. */
    private function calledCrewRecipients(User $user, Carbon $day): array
    {
        $engine = CallSheetEngine::forDay($user, $day, true);
        $ids = [];
        foreach ($engine['groups'] as $g) {
            foreach ($g['people'] as $p) {
                if (in_array($p['state'], [PayeeContract::ROSTER_CALLED, PayeeContract::ROSTER_PENDING_SIGNATURE], true) && ! empty($p['user_id'])) {
                    $ids[] = $p['user_id'];
                }
            }
        }

        return User::whereIn('id', array_unique($ids))->get()->map(fn ($u) => [
            'user_id' => $u->id,
            'name'    => User::creditShortName($u),   // créditos o Primer nombre + Primer apellido
            'email'   => trim((string) $u->email),
        ])->all();
    }

    /** Aviso a las figuras pendientes (best-effort; no rompe el flujo si el correo falla). */
    private function notifySigners(CallPackage $pkg, Carbon $day): void
    {
        try {
            $url = route('callsheet.package.sign', ['date' => $day->toDateString()]);
            foreach (($pkg->signers ?? []) as $sg) {
                $email = $sg['user_id'] ? optional(User::find($sg['user_id']))->email : null;
                if ($email) {
                    \Illuminate\Support\Facades\Mail::raw(
                        "Hay un llamado por aprobar ({$day->toDateString()}). Firma aquí: {$url}",
                        fn ($m) => $m->to($email)->subject('Llamado por firmar')
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('notifySigners falló: ' . $e->getMessage());
        }
    }

    // ============================ PANTALLA 2 · DEPARTAMENTOS ============================

    public function departments(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day     = $this->parseDate($date);
        $callDay = CurrentUnit::applyTo(CallDay::where('production_id', $pid)->whereDate('call_date', $day->toDateString()))->first();
        $general = $callDay ? $callDay->generalHHMM() : null;

        // Conteo por depto (de la población del roster) + offset actual + canal de radio (global).
        $roster = DayRosterBuilder::build($request->user(), $day);
        $countByName = [];
        foreach ($roster['groups'] as $g) {
            $countByName[$g['label']] = count($g['people']);
        }
        $offsets = CallSheetEngine::deptOffsets($pid);

        // Sólo los departamentos CON gente ese día (compacto: no los 44 del catálogo).
        $depts = Department::whereIn('name', array_keys($countByName))
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'name_en', 'sort_order', 'radio_channel'])
            ->map(function ($d) use ($countByName, $offsets, $general) {
                $off = $offsets[$d->id] ?? null;
                $time = null; $literal = null;
                if ($off) {
                    $literal = $off->literal_value;
                    if ($off->offset_minutes !== null) {
                        $time = CallSheetEngine::addMinutes($general, $off->offset_minutes);
                    }
                }
                return (object) [
                    'id' => $d->id, 'name' => $d->name, 'count' => $countByName[$d->name] ?? 0,
                    'radio' => $d->radio_channel, 'time' => $time, 'literal' => $literal,
                ];
            });

        return view('admin.callsheet.departments', [
            'day' => $day, 'dayLabel' => ProductionCalendar::dayLabelWithTotal($day),
            'general' => $general, 'depts' => $depts, 'nav' => $this->nav($day),
        ]);
    }

    public function saveDepartments(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day     = $this->parseDate($date);
        $callDay = CurrentUnit::applyTo(CallDay::where('production_id', $pid)->whereDate('call_date', $day->toDateString()))->first();
        $general = $callDay ? $callDay->generalHHMM() : null;

        $data = $request->validate([
            'dept'           => ['nullable', 'array'],
            'dept.*.time'    => ['nullable', 'date_format:H:i'],
            'dept.*.literal' => ['nullable', 'string', 'max:40'],
            'dept.*.radio'   => ['nullable', 'string', 'max:80'],
        ]);

        foreach ($data['dept'] ?? [] as $deptId => $row) {
            $deptId  = (int) $deptId;
            $literal = trim((string) ($row['literal'] ?? ''));
            $time    = $row['time'] ?? null;

            // Canal de radio = atributo GLOBAL del departamento (no del día). Se guarda siempre.
            if (array_key_exists('radio', $row)) {
                $radio = trim((string) ($row['radio'] ?? ''));
                Department::whereKey($deptId)->update(['radio_channel' => $radio === '' ? null : $radio]);
            }

            if ($literal === '' && ($time === null || $time === '')) {
                CallDeptOffset::where('production_id', $pid)->where('department_id', $deptId)->delete();
                continue;   // sin offset ni literal = vuelve al general
            }
            $offset = ($literal === '' && $time && $general) ? CallSheetEngine::minutesFrom($general, $time) : null;
            CallDeptOffset::updateOrCreate(
                ['production_id' => $pid, 'department_id' => $deptId],
                ['offset_minutes' => $literal === '' ? $offset : null, 'literal_value' => $literal === '' ? null : $literal]
            );
        }

        return redirect()->route('callsheet.departments', ['date' => $day->toDateString()])
            ->with('success', 'Horarios por departamento guardados.');
    }

    // ============================ PANTALLA 3 · PERSONAS ============================

    public function people(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day = $this->parseDate($date);

        $prod = CurrentProduction::get();
        $ps   = ($prod && is_array($prod->settings)) ? $prod->settings : [];

        $data = CallSheetEngine::forDay($request->user(), $day, false);
        $rosterUids = collect($data['groups'] ?? [])
            ->flatMap(fn ($g) => collect($g['people'] ?? [])->pluck('user_id'))
            ->map(fn ($x) => (int) $x)->all();

        return view('admin.callsheet.people', [
            'day'        => $day,
            'dayLabel'   => ProductionCalendar::dayLabelWithTotal($day),
            'data'       => $data,
            'places'     => $this->activePlaces($pid),
            'hotelCodes' => $this->parseHotelCodes($ps['callsheet_hotels'] ?? ''),
            'dayPlayers' => $this->suggestedDayPlayers($pid, $day),
            'nav'        => $this->nav($day),
            // Fase 3 · marcado "lleva pick up hoy": base (piso), efectivo (pre-check) y grupos de van.
            'pickupBase'      => array_flip(\App\Support\TransportPreload::baseUserIds($pid)),
            'pickupEffective' => array_flip(\App\Support\TransportPreload::effectiveUserIds($pid)),
            'pickupGroups'    => \App\Support\TransportPreload::groupMap($pid, $rosterUids),
        ]);
    }

    /** Claves de hotel de la leyenda global ("FS = Four Seasons\n...") → ['FS','SH',...] para sugerir. */
    private function parseHotelCodes(?string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', (string) $text) as $line) {
            $code = trim(explode('=', $line, 2)[0] ?? '');
            if ($code !== '') { $out[] = $code; }
        }
        return array_values(array_unique($out));
    }

    public function savePeople(Request $request, $date)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);
        $day     = $this->parseDate($date);
        $callDay = CurrentUnit::applyTo(CallDay::where('production_id', $pid)->whereDate('call_date', $day->toDateString()))->first();
        $general = $callDay ? $callDay->generalHHMM() : null;

        $data = $request->validate([
            'person'                    => ['nullable', 'array'],
            'person.*.sched_time'       => ['nullable', 'date_format:H:i'],
            'person.*.sched_literal'    => ['nullable', 'string', 'max:40'],
            'person.*.pickup_time'      => ['nullable', 'date_format:H:i'],
            'person.*.pickup_literal'   => ['nullable', 'string', 'max:40'],
            'person.*.pickup_place_id'  => ['nullable', 'integer'],
            'person.*.hotel'            => ['nullable', 'string', 'max:24'],
            'person.*.meal'             => ['nullable', 'boolean'],
            'person.*.pickup_mark'      => ['nullable', 'boolean'],
        ]);

        // Fase 3 · la BASE (always_pickup) entra por default; guardamos SOLO las excepciones.
        $baseSet = array_flip(\App\Support\TransportPreload::baseUserIds($pid));

        foreach ($data['person'] ?? [] as $userId => $row) {
            $userId        = (int) $userId;

            // Marca de pick up (independiente del horario): guarda solo la EXCEPCIÓN al default.
            $this->savePickupMark($pid, $userId, (bool) ($row['pickup_mark'] ?? false), isset($baseSet[$userId]), $request->user()->id);

            $schedLiteral  = trim((string) ($row['sched_literal'] ?? ''));
            $schedTime     = $row['sched_time'] ?? null;
            $pickupLiteral = trim((string) ($row['pickup_literal'] ?? ''));
            $pickupTime    = $row['pickup_time'] ?? null;
            $placeId       = ! empty($row['pickup_place_id']) ? (int) $row['pickup_place_id'] : null;
            $hotel         = trim((string) ($row['hotel'] ?? ''));
            $meal          = (bool) ($row['meal'] ?? false);

            // Sin nada propio y come (default) → sin fila (mantiene los singletons dispersos).
            $hasOverride = $schedLiteral !== '' || $schedTime || $pickupLiteral !== '' || $pickupTime || $placeId || $hotel !== '' || ! $meal;
            if (! $hasOverride) {
                CallPersonSchedule::where('production_id', $pid)->where('user_id', $userId)->delete();
                continue;
            }

            $schedOffset  = ($schedLiteral === '' && $schedTime && $general) ? CallSheetEngine::minutesFrom($general, $schedTime) : null;
            $pickupOffset = ($pickupLiteral === '' && $pickupTime && $general) ? CallSheetEngine::minutesFrom($general, $pickupTime) : null;

            CallPersonSchedule::updateOrCreate(
                ['production_id' => $pid, 'user_id' => $userId],
                [
                    'schedule_offset_minutes' => $schedLiteral === '' ? $schedOffset : null,
                    'schedule_literal'        => $schedLiteral === '' ? null : $schedLiteral,
                    'pickup_offset_minutes'   => $pickupLiteral === '' ? $pickupOffset : null,
                    'pickup_literal'          => $pickupLiteral === '' ? null : $pickupLiteral,
                    'pickup_place_id'         => $placeId,
                    'hotel_code'              => $hotel === '' ? null : $hotel,
                    'meal_mark'               => $meal,
                ]
            );
        }

        return redirect()->route('callsheet.people', ['date' => $day->toDateString()])
            ->with('success', 'Horarios por persona guardados.');
    }

    /**
     * Guarda SOLO la excepción de la marca de pick up (Fase 3): la BASE entra por default, así que
     * una base marcada o un no-base desmarcado NO dejan fila; la base desmarcada guarda is_marked=0 y
     * el no-base marcado guarda is_marked=1. La tabla queda dispersa, como la de horarios.
     */
    private function savePickupMark(int $pid, int $userId, bool $checked, bool $isBase, ?int $by): void
    {
        if ($isBase === $checked) {                       // coincide con el default → sin fila
            \App\Models\TransportPickupMark::where('production_id', $pid)->where('user_id', $userId)->delete();

            return;
        }
        \App\Models\TransportPickupMark::updateOrCreate(
            ['production_id' => $pid, 'user_id' => $userId],
            ['is_marked' => $checked, 'marked_by_id' => $by]
        );
    }

    // ============================ PARTE F · EL BACK EXPORTABLE (PDF) ============================

    public function back(Request $request, $date)
    {
        $day = $this->parseDate($date);
        $pdf = $this->renderBackPdf($request->user(), $day, [
            'preset' => $request->query('preset'),
            'lang'   => $request->query('lang'),
            'paper'  => $request->query('paper'),
            'bands'  => $request->query('bands'),
        ]);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="llamado-' . $day->toDateString() . '.pdf"',
        ]);
    }

    /**
     * Genera los BYTES del PDF del back. Reusable: lo usa el export directo ({@see back()}) y el
     * ENSAMBLE del paquete (front + back). $opts: preset/lang/paper/bands (overrides) + hideSign
     * (oculta las líneas de firma cuando el back va dentro de un paquete que se firma en el front).
     */
    public function renderBackPdf(\App\Models\User $user, Carbon $day, array $opts = []): string
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);

        $engine  = CallSheetEngine::forDay($user, $day, true);   // TODOS los deptos
        $callDay = $engine['call_day'];
        $general = $engine['general'];

        // Preset (formato del back): opts (override) → settings → default. El preset fija idioma,
        // columnas de logística, estilo de comidas y bloques de notas — sobre el mismo esqueleto CASPER.
        $prod = CurrentProduction::get();
        $s = ($prod && is_array($prod->settings)) ? $prod->settings : [];
        $preset    = CallSheetFormats::resolve(($opts['preset'] ?? null) ?: ($s['callsheet_preset'] ?? null));
        $lang      = ($opts['lang'] ?? null) === 'en' ? 'en' : (($opts['lang'] ?? null) === 'es' ? 'es' : $preset['lang']);
        $tableCols = CallSheetFormats::tableColumns($preset);

        // Ajustes GLOBALES (papel/bandas) sobre el preset: eleccion una vez, o el default del preset.
        $paper           = (($opts['paper'] ?? null) ?: ($s['callsheet_paper'] ?? null)) ?: $preset['paper'];
        $preset['bands'] = (($opts['bands'] ?? null) ?: ($s['callsheet_bands'] ?? null)) ?: $preset['bands'];

        $deptEnByName = Department::pluck('name_en', 'name')->all();

        // Construye los bloques (depto → filas). Day players van a Crew Adicional; OUT se excluye.
        $blocks = [];
        $crewAdicional = [];
        foreach ($engine['groups'] as $g) {
            $rows = [];
            foreach ($g['people'] as $p) {
                if ($p['state'] === PayeeContract::ROSTER_OUT) {
                    continue;   // inactivo / day player vencido → no va al back
                }
                $row = $this->backRow($p);
                if (($p['frequency'] ?? null) === PayeeContract::FREQ_DAY_PLAYER) {
                    $crewAdicional[] = $row;
                } else {
                    $rows[] = $row;
                }
            }
            $label = $lang === 'en' ? ($g['label_en'] ?? $deptEnByName[$g['label']] ?? $g['label']) : $g['label'];
            $blocks[] = ['label' => $label, 'rows' => $rows];
        }

        // Bloque Crew Adicional: day players + filas vacías (configurable, default 6).
        $emptyRows = 6;
        if ($callDay && is_array($callDay->footer_extra) && isset($callDay->footer_extra['crew_adicional_rows'])) {
            $emptyRows = max(0, (int) $callDay->footer_extra['crew_adicional_rows']);
        }
        for ($i = 0; $i < $emptyRows; $i++) {
            $crewAdicional[] = $this->emptyRow();
        }
        $blocks[] = ['label' => $lang === 'en' ? 'Additional Crew' : 'Crew Adicional', 'rows' => $crewAdicional];

        $columns = $this->balanceColumns($blocks, 3);

        // Iguala la altura de las 3 columnas con renglones reservados (rejilla rectangular estilo
        // CASPER): la última columna lleva además el bloque de Alimentación, así que se compensa.
        $enabledMeals = collect($engine['meals'])->where('enabled', true)->count();
        $this->padColumns($columns, $enabledMeals);

        // Conteo de comidas: crew de las marcas #; cast/BG de la config.
        $mealCrew = 0;
        foreach ($engine['groups'] as $g) {
            foreach ($g['people'] as $p) {
                if ($p['state'] !== PayeeContract::ROSTER_OUT && ! empty($p['meal_mark'])) {
                    $mealCrew++;
                }
            }
        }

        // Leyenda del pie: SOLO las claves de lugar usadas ese día + canales de radio de deptos presentes.
        $usedCodes = $this->usedPlaceCodes($pid, $engine, $callDay);
        $radios    = $this->radioChannels($engine['groups']);
        $signers   = $this->signersFor($engine, $lang === 'en');   // firma = 3 roles con nombre

        // Cambios de horario TRAS aprobar (azul): compara el horario actual vs el congelado del paquete.
        $changed = $opts['changed'] ?? null;
        if ($changed === null) {
            $changed = ($fp = $this->frozenPackageFor($pid, $day)) ? $this->changedKeys($this->snapshotFromEngine($engine), $fp->frozen_schedule) : [];
        }

        // Wrap sólo si el flag ON.
        $wrap = (Features::enabled('callsheet_wrap_estimate') && $callDay && is_array($callDay->footer_extra))
            ? ($callDay->footer_extra['wrap_time'] ?? null) : null;

        return Pdf::loadView('admin.callsheet.back', [
            'day'         => $day,
            'lang'        => $lang,
            'preset'      => $preset,
            'tableCols'   => $tableCols,
            'callDay'     => $callDay,
            'general'     => $general,
            'wrap'        => $wrap,
            'dayLabel'    => ProductionCalendar::dayLabelWithTotal($day),
            'dayNumber'   => ProductionCalendar::dayNumber($day),
            'plannedM'    => ProductionCalendar::plannedShootDays(),
            'columns'     => $columns,
            'meals'       => $engine['meals'],
            'mealCrew'    => $mealCrew,
            'castCount'   => $engine['cast_count'],
            'bgCount'     => $engine['bg_count'],
            'usedCodes'   => $usedCodes,
            'radios'      => $radios,
            'signers'     => $signers,
            'globalNotes' => $s['callsheet_notes'] ?? null,
            'safetyBar'   => $s['callsheet_safety_bar'] ?? null,
            'hotelsNote'  => $s['callsheet_hotels'] ?? null,
            'emergNote'   => $s['callsheet_emergency'] ?? null,
            'hideSign'    => ! empty($opts['hideSign']),
            'changed'     => $changed,
            'production'  => $prod,
        ])->setPaper($paper, 'portrait')->output();
    }

    /** Abreviaturas para que el puesto quepa en UNA línea del back (como los llamados reales). */
    private const PUESTO_ABBR = [
        'Coordinador' => 'Coord.', 'Coordinadora' => 'Coord.', 'Asistente' => 'Asst.',
        'Supervisor' => 'Sup.', 'Supervisora' => 'Sup.', 'Diseñador' => 'Dis.', 'Diseñadora' => 'Dis.',
        'Operador' => 'Op.', 'Operadora' => 'Op.', 'Producción' => 'Prod.', 'Fotografía' => 'Foto.',
        'Seguridad' => 'Seg.',
    ];

    /**
     * Una fila del back a partir de una persona enriquecida del motor. Devuelve un BOLSO por columna
     * (el blade elige cuáles pinta según el preset). LLAMADO = N/C si no está llamado. Columnas sin
     * dato aún (hotel, out) van vacías = rellenables, como en los llamados reales.
     */
    private function backRow(array $p): array
    {
        $called   = in_array($p['state'], [PayeeContract::ROSTER_CALLED, PayeeContract::ROSTER_PENDING_SIGNATURE], true);
        $sched    = $p['schedule'];
        $llamado  = $called ? ($sched['literal'] ?? $sched['time'] ?? 'N/C') : 'N/C';
        $pickup   = $p['pickup'];

        return [
            'uid'    => (string) ($p['user_id'] ?? ''),
            'num'    => ! empty($p['meal_mark']) ? '1' : '',
            'title'  => strtr($p['cargo'], self::PUESTO_ABBR),
            'name'   => $p['name'],
            'hotel'  => $p['hotel'] ?? '',                           // clave de hotel por persona
            'pickup' => $pickup['literal'] ?? $pickup['time'] ?? '',
            'place'  => $pickup['place'] ?? '',
            'call'   => $llamado ?: 'N/C',
            'out'    => '',                                          // sin dato aún (wrap es por día)
        ];
    }

    /** Fila vacía (rellenable) para el bloque Crew Adicional. */
    private function emptyRow(): array
    {
        return ['uid' => '', 'num' => '', 'title' => '', 'name' => '', 'hotel' => '', 'pickup' => '', 'place' => '', 'call' => '', 'out' => ''];
    }

    /**
     * Los 3 FIRMANTES de aprobación (formato de la industria): Productor en Línea, Gerente de
     * Producción y 1er AD. Patrones de puesto es/en para resolverlos del roster.
     */
    private const SIGNER_ROLES = [
        'lp'  => ['es' => 'Productor en Línea',   'en' => 'Line Producer',      'm' => ['productor en línea', 'productor en linea', 'line prod', 'line producer', 'upm']],
        'gte' => ['es' => 'Gerente de Producción', 'en' => 'Production Manager', 'm' => ['gerente de producción', 'gerente de produccion', 'production manager']],
        'ad'  => ['es' => '1er AD',                'en' => '1st AD',             'm' => ['1er ad', '1st ad', 'first asst director', 'first assistant director', 'primer asistente de dirección']],
    ];

    /** A quién se FIJÓ a mano en Formato del back: [key => user_id]. Vacío = auto por puesto. */
    private function signerPicks(): array
    {
        $prod = CurrentProduction::get();
        $s    = ($prod && is_array($prod->settings)) ? $prod->settings : [];
        $picks = is_array($s['callsheet_signers'] ?? null) ? $s['callsheet_signers'] : [];

        return array_intersect_key($picks, self::SIGNER_ROLES);
    }

    /**
     * Resuelve cada rol firmante → [key => ['name'=>, 'user_id'=>]].
     *
     * 1º manda lo FIJADO en Formato del back (persona explícita); si esa key está en automático
     * se busca en el roster del día por el PUESTO (patrones 'm'). Si tampoco, la línea queda
     * rellenable a mano en el papel.
     */
    private function resolveSigners(array $engine): array
    {
        $people = [];
        foreach ($engine['groups'] as $g) {
            foreach ($g['people'] as $p) {
                if ($p['state'] !== PayeeContract::ROSTER_OUT) {
                    $people[] = ['cargo' => mb_strtolower((string) $p['cargo']), 'name' => $p['name'], 'user_id' => $p['user_id'] ?? null];
                }
            }
        }

        $picks = $this->signerPicks();

        $out = [];
        foreach (self::SIGNER_ROLES as $key => $r) {
            $found = ['name' => '', 'user_id' => null];

            if (! empty($picks[$key])) {
                $uid = (int) $picks[$key];
                // Si además está en el roster, reusa su nombre de ahí (mismo criterio de créditos).
                $inRoster = null;
                foreach ($people as $pp) {
                    if ((int) ($pp['user_id'] ?? 0) === $uid) { $inRoster = $pp; break; }
                }
                if ($inRoster) {
                    $out[$key] = ['name' => $inRoster['name'], 'user_id' => $uid];
                    continue;
                }
                $u = User::find($uid);
                if ($u && $u->activo) {
                    $out[$key] = ['name' => User::displayName($u), 'user_id' => $uid];
                    continue;
                }
                // Fijado pero dado de baja → cae al automático (no imprime a un desactivado).
            }

            foreach ($people as $pp) {
                foreach ($r['m'] as $needle) {
                    if (str_contains($pp['cargo'], $needle)) { $found = ['name' => $pp['name'], 'user_id' => $pp['user_id']]; break 2; }
                }
            }
            $out[$key] = $found;
        }

        return $out;
    }

    /** Nombre que saldría en AUTOMÁTICO por puesto hoy (para explicarlo en Formato del back). */
    private function autoSignerNames(): array
    {
        $pid = CurrentProduction::id();
        $out = array_fill_keys(array_keys(self::SIGNER_ROLES), '');
        if ($pid === null || ! auth()->check()) {
            return $out;
        }

        $engine = CallSheetEngine::forDay(auth()->user(), Carbon::today(), true);
        $people = [];
        foreach ($engine['groups'] as $g) {
            foreach ($g['people'] as $p) {
                if ($p['state'] !== PayeeContract::ROSTER_OUT) {
                    $people[] = ['cargo' => mb_strtolower((string) $p['cargo']), 'name' => $p['name']];
                }
            }
        }
        foreach (self::SIGNER_ROLES as $key => $r) {
            foreach ($people as $pp) {
                foreach ($r['m'] as $needle) {
                    if (str_contains($pp['cargo'], $needle)) { $out[$key] = $pp['name']; break 2; }
                }
            }
        }

        return $out;
    }

    /**
     * Gente ELEGIBLE como firmante: usuarios ACTIVOS ligados a esta producción (production_user),
     * tengan o no contrato de crew — el aprobador puede ser oficina de producción sin llamado.
     * Devuelve [['id'=>, 'name'=>, 'cargo'=>], ...] ordenado por nombre.
     */
    private function productionPeople(): array
    {
        $pid = CurrentProduction::id();
        if ($pid === null) {
            return [];
        }

        $posNameById = DB::table('positions')->pluck('name', 'id')->all();

        $rows = DB::table('production_user as pu')
            ->join('users', 'users.id', '=', 'pu.user_id')
            ->where('pu.production_id', $pid)
            ->where('users.activo', 1)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.lname', 'users.lname2', 'users.ncreditos', 'users.puestodepartamento', 'pu.position_id']);

        $out = [];
        foreach ($rows as $r) {
            $cargo = ($r->position_id && isset($posNameById[$r->position_id]))
                ? $posNameById[$r->position_id]
                : (string) $r->puestodepartamento;
            $out[] = ['id' => (int) $r->id, 'name' => User::displayName($r), 'cargo' => $cargo];
        }

        return $out;
    }

    /** Firmantes para el pie del BACK (rol + nombre). Si el puesto no está, la línea queda rellenable. */
    private function signersFor(array $engine, bool $en): array
    {
        $resolved = $this->resolveSigners($engine);
        $out = [];
        foreach (self::SIGNER_ROLES as $key => $r) {
            $out[] = ['role' => $en ? $r['en'] : $r['es'], 'name' => $resolved[$key]['name']];
        }

        return $out;
    }

    /** Firmantes para el PAQUETE (con key + user_id, para gatear quién firma). Default: los 3. */
    private function packageSigners(array $engine, bool $en): array
    {
        $resolved = $this->resolveSigners($engine);
        $out = [];
        foreach (self::SIGNER_ROLES as $key => $r) {
            $out[] = [
                'key'        => $key,
                'role_label' => $en ? $r['en'] : $r['es'],
                'user_id'    => $resolved[$key]['user_id'],
                'name'       => $resolved[$key]['name'],
            ];
        }

        return $out;
    }

    /** Distribuye los bloques en $n columnas CONTIGUAS balanceadas por altura (conserva el orden canónico). */
    private function balanceColumns(array $blocks, int $n): array
    {
        // Cada persona = 1 renglón (nowrap); depto con gente = banda + N filas; depto vacío = 1 renglón.
        $height = fn ($b) => count($b['rows']) ? 1 + count($b['rows']) : 1;
        $total  = array_sum(array_map($height, $blocks));
        $target = $total / $n;

        $columns = array_fill(0, $n, []);
        $col = 0; $acc = 0;
        foreach ($blocks as $b) {
            $columns[$col][] = $b;
            $acc += $height($b);
            // Salta a la siguiente columna al alcanzar el objetivo (deja la última recibir el resto).
            if ($col < $n - 1 && $acc >= $target * ($col + 1)) {
                $col++;
            }
        }

        return $columns;
    }

    /**
     * Rellena las columnas cortas con renglones RESERVADOS (vacíos) hasta que las 3 midan lo mismo,
     * para una rejilla rectangular como las plantillas CASPER (nada "esperando una columna" al lado).
     * La última columna suma el alto del bloque de Alimentación (banda + header + servicios).
     */
    private function padColumns(array &$columns, int $enabledMeals): void
    {
        $n = count($columns);
        $blockH = function ($b) {
            if (! empty($b['filler'])) { return count($b['rows']); }
            return count($b['rows']) ? 1 + count($b['rows']) : 1;   // banda + filas, o banda N/C
        };
        $mealBoxH = $enabledMeals > 0 ? $enabledMeals + 3 : 0;       // banda + header + servicios (+totales)

        $heights = [];
        foreach ($columns as $i => $col) {
            $h = 0;
            foreach ($col as $b) { $h += $blockH($b); }
            if ($i === $n - 1) { $h += $mealBoxH; }
            $heights[$i] = $h;
        }
        $max = max($heights);
        foreach ($columns as $i => &$col) {
            $gap = $max - $heights[$i];
            if ($gap > 0) {
                $col[] = ['filler' => true, 'rows' => array_fill(0, $gap, $this->emptyRow())];
            }
        }
    }

    /** Claves de lugar usadas ese día (locación, basecamp, pick ups, comidas) para la leyenda del pie. */
    private function usedPlaceCodes(int $pid, array $engine, $callDay): array
    {
        $codes = [];
        $byId  = DB::table('call_places')->where('production_id', $pid)->pluck('code', 'id')->all();
        $add   = function ($code) use (&$codes) { if ($code) { $codes[$code] = true; } };

        if ($callDay) {
            $add($byId[$callDay->location_place_id] ?? null);
            $add($byId[$callDay->basecamp_place_id] ?? null);
        }
        foreach ($engine['meals'] as $m) { $add($m['place']); }
        foreach ($engine['groups'] as $g) {
            foreach ($g['people'] as $p) { $add($p['pickup']['place'] ?? null); }
        }

        $names = DB::table('call_places')->where('production_id', $pid)->pluck('name', 'code')->all();
        $out = [];
        foreach (array_keys($codes) as $c) { $out[$c] = $names[$c] ?? $c; }
        ksort($out);

        return $out;
    }

    /**
     * Canales de radio del pie: cada canal con los departamentos presentes que lo comparten
     * (p. ej. "1 → Dirección, Producción"), no sólo el número suelto. Orden natural de canal.
     */
    private function radioChannels(array $groups): array
    {
        $present = [];
        foreach ($groups as $g) {
            if (count($g['people'])) { $present[$g['label']] = true; }
        }
        if (! $present) { return []; }

        $byChannel = [];
        foreach (DB::table('departments')->whereIn('name', array_keys($present))->whereNotNull('radio_channel')
            ->orderBy('sort_order')->orderBy('name')->get(['name', 'radio_channel']) as $d) {
            $ch = trim((string) $d->radio_channel);
            if ($ch === '') { continue; }
            $byChannel[$ch][] = $d->name;
        }
        uksort($byChannel, 'strnatcasecmp');

        $out = [];
        foreach ($byChannel as $ch => $depts) {
            $out[] = ['channel' => $ch, 'depts' => implode(', ', $depts)];
        }

        return $out;
    }

    // ============================ Catálogo de LUGARES ============================

    public function places()
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);

        return view('admin.callsheet.places', [
            'places' => CallPlace::forProduction($pid)->orderBy('name')->get(),
        ]);
    }

    public function savePlaces(Request $request)
    {
        $pid = CurrentProduction::id();
        abort_if($pid === null, 404);

        $data = $request->validate([
            'places'        => ['nullable', 'array'],
            'places.*.id'   => ['nullable', 'integer'],
            'places.*.code' => ['nullable', 'string', 'max:24'],
            'places.*.name' => ['nullable', 'string', 'max:150'],
        ]);

        foreach ($data['places'] ?? [] as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '' && $name === '') {
                if (! empty($row['id'])) {
                    CallPlace::where('production_id', $pid)->where('id', $row['id'])->delete();
                }
                continue;
            }
            if ($code === '' || $name === '') {
                continue;   // ambos requeridos
            }
            if (! empty($row['id'])) {
                CallPlace::where('production_id', $pid)->where('id', $row['id'])->update(['code' => $code, 'name' => $name]);
            } else {
                CallPlace::updateOrCreate(['production_id' => $pid, 'code' => $code], ['name' => $name, 'active' => 1]);
            }
        }

        return redirect()->route('callsheet.places')->with('success', 'Catálogo de lugares guardado.');
    }

    /** Day players con fecha de trabajo ese día → sugeridos para Crew Adicional. */
    private function suggestedDayPlayers(int $pid, Carbon $day): array
    {
        $rows = DB::table('payee_contracts as pc')
            ->join('payees as p', 'p.id', '=', 'pc.payee_id')
            ->join('users', 'users.id', '=', 'p.user_id')
            ->where('pc.concept', PayeeContract::CONCEPT_CREW)
            ->where('pc.production_id', $pid)
            ->where('pc.payment_frequency', PayeeContract::FREQ_DAY_PLAYER)
            ->where('pc.is_active', 1)
            ->where('users.activo', 1)
            ->whereExists(function ($q) use ($day) {
                $q->select(DB::raw(1))->from('payee_contract_work_dates as wd')
                    ->whereColumn('wd.payee_contract_id', 'pc.id')
                    ->whereDate('wd.work_date', $day->toDateString());
            })
            ->distinct()
            ->get(['users.id as user_id', 'users.name', 'users.lname', 'users.ncreditos']);

        $out = [];
        foreach ($rows as $r) {
            $out[] = ['user_id' => $r->user_id, 'name' => $r->ncreditos ?: trim($r->name . ' ' . $r->lname)];
        }
        return $out;
    }

    // ============================ Helpers ============================

    /** Y-m-d válido o 404 (nada de 500 por basura). */
    private function parseDate($date): Carbon
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    /** El CallDay del día (lo crea si no existe) + siembra sus comidas la 1ª vez (hereda del día previo). */
    private function resolveCallDay(Carbon $day, int $pid): CallDay
    {
        // (2026-09-07 · Unidades 2b) El call_day es POR UNIDAD: (producción, fecha) → (producción, fecha,
        // unidad). El unit_id entra a la LLAVE sólo cuando hay más de una unidad; con una sola, la llave es
        // idéntica a la de hoy (y las filas existentes tienen unit_id NULL → coincide por IS NULL). Los
        // hijos que cuelgan del call_day (comidas) heredan por call_day_id.
        $key = ['production_id' => $pid, 'call_date' => $day->toDateString()];
        if (CurrentUnit::hasMultiple()) {
            $key['unit_id'] = CurrentUnit::id();
        }
        $callDay = CallDay::firstOrCreate($key);

        if ($callDay->wasRecentlyCreated || $callDay->meals()->count() === 0) {
            $this->seedMeals($callDay, $pid, $day);
        }

        return $callDay;
    }

    /**
     * Siembra las comidas HEREDANDO del día previo más cercano (memoria del día anterior: se conservan
     * los offsets, se recalculan las horas contra el general nuevo). Si no hay día previo, NO inventa:
     * las comidas se auto-establecen por la hora del general al guardar la config ({@see establishMeals}).
     */
    private function seedMeals(CallDay $callDay, int $pid, Carbon $day): void
    {
        if ($callDay->meals()->count() > 0) {
            return;
        }
        // (2026-09-07 · Unidades 2b) Hereda las comidas del día previo DE LA MISMA UNIDAD (con una sola
        // unidad no filtra → idéntico a hoy).
        $prev = CurrentUnit::applyTo(
            CallDay::where('production_id', $pid)->whereDate('call_date', '<', $day->toDateString())
        )
            ->orderByDesc('call_date')->first();

        if ($prev && $prev->meals()->count() > 0) {
            $sort = 0;
            foreach ($prev->meals as $m) {
                $callDay->meals()->create([
                    'sort_order' => $sort++, 'label' => $m->label, 'enabled' => $m->enabled,
                    'offset_minutes' => $m->offset_minutes, 'explicit_time' => $m->explicit_time,
                    'place_id' => $m->place_id, 'place_text' => $m->place_text,
                    'cast_override' => $m->cast_override, 'bg_override' => $m->bg_override,
                ]);
            }
        }
    }

    /** Auto-establece el SET de comidas según la hora del general (Café/Craft siempre; sin box lunch). */
    private function establishMeals(CallDay $callDay, string $general): void
    {
        $callDay->meals()->delete();
        $sort = 0;
        foreach (CallSheetEngine::autoMealSet($general) as [$label, $offset]) {
            $callDay->meals()->create([
                'sort_order' => $sort++, 'label' => $label, 'enabled' => true, 'offset_minutes' => $offset,
            ]);
        }
    }

    private function activePlaces(int $pid)
    {
        return CallPlace::forProduction($pid)->where('active', 1)->orderBy('name')->get();
    }

    /** Navegación día anterior/siguiente + enlaces a las pantallas hermanas. */
    private function nav(Carbon $day): array
    {
        return [
            'prev' => $day->copy()->subDay()->toDateString(),
            'next' => $day->copy()->addDay()->toDateString(),
            'today' => Carbon::today()->toDateString(),
            'dateStr' => $day->toDateString(),
        ];
    }
}
