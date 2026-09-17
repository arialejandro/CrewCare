<?php

namespace App\Http\Controllers;

use App\Models\FileDelivery;
use App\Models\FileDeliveryRecipient;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\FileDeliveryDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * DISTRIBUCIÓN — envío de cualquier documento (avisos, mapas, memos) a todo el equipo, con MARCA DE
 * AGUA por persona (nombre en créditos). Reusa el mismo outbox + despachador + marca de agua del
 * paquete del llamado ({@see \App\Support\FileDeliveryDispatcher}, {@see \App\Support\PdfWatermarker}):
 * el request solo ENCOLA + una ráfaga inline; el cron `deliveries:dispatch` termina el resto.
 *
 * Destinatarios por defecto: TODOS los usuarios ACTIVOS con correo (los desactivados nunca reciben).
 * Gate `settings.manage` — mandar a todo el sitio es una acción de administración.
 */
class FileDeliveryController extends Controller
{
    /** Lista de envíos hechos (últimos primero). */
    public function index()
    {
        if (! $this->tablesReady()) {
            return view('admin.deliveries.index', ['deliveries' => null, 'unavailable' => true]);
        }

        $deliveries = FileDelivery::where('source_type', FileDelivery::SOURCE_MANUAL)
            ->withCount([
                'recipients as total',
                'recipients as sent'    => fn ($q) => $q->where('status', FileDeliveryRecipient::SENT),
                'recipients as pending' => fn ($q) => $q->where('status', FileDeliveryRecipient::PENDING),
                'recipients as failed'  => fn ($q) => $q->where('status', FileDeliveryRecipient::FAILED),
            ])
            ->latest('id')->paginate(20);

        return view('admin.deliveries.index', compact('deliveries'));
    }

    /** Formulario de nuevo envío (muestra a cuántos activos llegaría). */
    public function create()
    {
        if (! $this->tablesReady()) {
            return redirect()->route('deliveries.index');
        }

        return view('admin.deliveries.create', [
            'recipientCount' => $this->activeRecipients()->count(),
        ]);
    }

    /** Crea el envío + encola a todos los activos con correo + ráfaga inline. */
    public function store(Request $request)
    {
        if (! $this->tablesReady()) {
            return redirect()->route('deliveries.index');
        }

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:180'],
            'body'      => ['nullable', 'string', 'max:4000'],
            'document'  => ['required', 'file', 'mimetypes:application/pdf', 'max:30720'],   // 30 MB
            'watermark' => ['nullable', 'boolean'],
        ]);

        $recips = $this->activeRecipients()->get()->map(fn ($u) => [
            'user_id' => $u->id,
            'name'    => User::creditShortName($u),   // créditos o Primer nombre + Primer apellido
            'email'   => trim((string) $u->email),
        ])->filter(fn ($r) => $r['email'] !== '')->values();

        if ($recips->isEmpty()) {
            return back()->withInput()->with('warning', 'No hay usuarios activos con correo para enviar.');
        }

        $delivery = FileDelivery::create([
            'production_id' => CurrentProduction::id(),
            'created_by'    => $request->user()->id,
            'source_type'   => FileDelivery::SOURCE_MANUAL,
            'source_id'     => null,
            'title'         => $data['title'],
            'body'          => $data['body'] ?? null,
            'base_path'     => 'pending',   // se rellena tras guardar el archivo con el id del envío
            'base_name'     => 'documento.pdf',
            'watermark'     => $request->boolean('watermark', true),
        ]);

        // Guardar el PDF base con el id del envío + un nombre visible a partir del título.
        $path = $request->file('document')->storeAs('deliveries', $delivery->id . '-base.pdf', 'local');
        $delivery->update([
            'base_path' => $path,
            'base_name' => $this->safeFileName($data['title']) . '.pdf',
        ]);

        $now  = now();
        $rows = $recips->map(fn ($r) => [
            'file_delivery_id' => $delivery->id,
            'user_id'          => $r['user_id'],
            'name'             => $r['name'],
            'email'            => $r['email'],
            'watermark_text'   => $r['name'],
            'status'           => FileDeliveryRecipient::PENDING,
            'attempts'         => 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ])->all();
        foreach (array_chunk($rows, 200) as $chunk) {
            FileDeliveryRecipient::insert($chunk);
        }

        $burst = FileDeliveryDispatcher::drain(20, 12.0);

        $msg = "Envío encolado a {$recips->count()} persona(s)";
        $msg .= $burst['sent'] > 0 ? " — {$burst['sent']} ya salieron; el resto sale solo en unos minutos." : ' — saldrán en unos minutos.';

        return redirect()->route('deliveries.show', $delivery->id)->with('success', $msg);
    }

    /** Tablero de seguimiento de un envío (con polling mientras haya pendientes). */
    public function show(FileDelivery $delivery)
    {
        abort_unless($delivery->source_type === FileDelivery::SOURCE_MANUAL, 404);
        $recipients = $delivery->recipients()->orderBy('name')->get();

        return view('admin.deliveries.show', [
            'delivery'   => $delivery,
            'counts'     => $delivery->counts(),
            'recipients' => $recipients,
        ]);
    }

    /** Estado del envío (JSON para polling). */
    public function state(FileDelivery $delivery)
    {
        abort_unless($delivery->source_type === FileDelivery::SOURCE_MANUAL, 404);

        return response()->json($delivery->counts());
    }

    /** ¿Está aplicada la actualización de BD del módulo? (si no, el módulo se muestra "no disponible"). */
    private function tablesReady(): bool
    {
        return Schema::hasTable('file_deliveries') && Schema::hasTable('file_delivery_recipients');
    }

    /** Usuarios ACTIVOS con correo (nunca los desactivados). */
    private function activeRecipients()
    {
        return User::where('activo', 1)
            ->whereNotNull('email')->where('email', '!=', '');
    }

    /** Título → nombre de archivo seguro (para el adjunto). */
    private function safeFileName(string $title): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $title);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? mb_substr($slug, 0, 80) : 'documento';
    }
}
