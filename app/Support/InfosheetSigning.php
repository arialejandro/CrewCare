<?php

namespace App\Support;

use App\Exceptions\ContractEmitException;
use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractClause;
use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EL INFOSHEET · FASE 3 — la AUTORIZACIÓN (paso 2) y el DISPARO (paso 3).
 *
 * Los AUTORIZADORES configurados en el módulo de firma aprueban el trato con su firma AUTÓGRAFA.
 * Cada casillero es un PUESTO configurado, o el token DEPT_HOD (el jefe del departamento del
 * contrato), o —por defecto, sin config— el Line Producer (por rol). Cada autorización se sella (la
 * imagen entra al hash). Al completarse todas, se DISPARA la generación: emite el contrato
 * (clausulado activo) + arma el sobre (con la lista de firmantes) + lo envía. El paso 4 vive en el sobre.
 */
class InfosheetSigning
{
    const ROLE_LP       = 'line_producer';
    const ROLE_DEPT_HOD = 'dept_hod';
    const ROLE_POSITION = 'position';

    /** Casilleros de autorización requeridos (tipados). Sin config → un LP por defecto. */
    public static function slots(PayeeContract $contract): array
    {
        $entries = SignaturePositions::authorizerEntries();
        if (empty($entries)) {
            return [['type' => self::ROLE_LP, 'position_id' => null, 'label' => __('Line Producer')]];
        }
        return array_map(function ($e) {
            if ($e === SignaturePositions::DEPT_HOD) {
                return ['type' => self::ROLE_DEPT_HOD, 'position_id' => null, 'label' => __('HOD del departamento')];
            }
            return ['type' => self::ROLE_POSITION, 'position_id' => (int) $e, 'label' => SignaturePositions::positionLabel((int) $e)];
        }, $entries);
    }

    /** Llave estable de un casillero (para casar con las autorizaciones ya hechas). */
    private static function slotKey(array $slot): string
    {
        return $slot['type'] === self::ROLE_POSITION ? 'pos:' . $slot['position_id'] : $slot['type'];
    }

    /** Llave estable de una autorización ya registrada (misma convención que slotKey). */
    private static function authKey($auth): string
    {
        return $auth->role === self::ROLE_POSITION ? 'pos:' . $auth->position_id : (string) $auth->role;
    }

    /** Autorizaciones ya hechas, indexadas por llave de casillero. */
    public static function done(PayeeContract $contract)
    {
        return $contract->authorizations()->get()->keyBy(fn ($a) => self::authKey($a));
    }

    /** Casilleros que faltan por autorizar. */
    public static function pendingSlots(PayeeContract $contract): array
    {
        $done = self::done($contract);
        return array_values(array_filter(self::slots($contract), fn ($slot) => ! $done->has(self::slotKey($slot))));
    }

    public static function isComplete(PayeeContract $contract): bool
    {
        return count(self::pendingSlots($contract)) === 0;
    }

    /** B3 · ¿La autorización es una escalera secuencial (nivel a nivel)? */
    public static function sequential(): bool
    {
        return SignaturePositions::authSequential();
    }

    /**
     * B3 · Casilleros DISPONIBLES para autorizar AHORA. Paralelo (default) = todos los pendientes;
     * ESCALERA = solo el SIGUIENTE por autorizar (los de más arriba esperan su turno). Así una cadena
     * de 3-6 niveles se aprueba en orden. `isComplete` sigue mirando TODOS los pendientes.
     */
    public static function availableSlots(PayeeContract $contract): array
    {
        $pending = self::pendingSlots($contract);
        if (self::sequential() && count($pending) > 1) {
            return [$pending[0]];
        }
        return $pending;
    }

    /**
     * Estado de cada casillero para la UI: etiqueta + si está hecho + la autorización (sellada, con
     * autógrafa) si existe. Deja la vista TONTA (no recalcula llaves ni conoce lo privado).
     *
     * @return array<int,array{label:string,done:bool,auth:?\App\Models\InfosheetAuthorization}>
     */
    public static function statusFor(PayeeContract $contract): array
    {
        $done = self::done($contract);
        $seq  = self::sequential();

        // En escalera, el ÚNICO casillero abierto es el primer pendiente; los de arriba esperan turno.
        $openKey = null;
        foreach (self::slots($contract) as $slot) {
            if (! $done->has(self::slotKey($slot))) {
                $openKey = self::slotKey($slot);
                break;
            }
        }

        return array_map(function ($slot) use ($done, $seq, $openKey) {
            $key    = self::slotKey($slot);
            $isDone = $done->has($key);
            return [
                'label'   => $slot['label'],
                'done'    => $isDone,
                'auth'    => $done->get($key),
                // 'blocked' = pendiente pero aún no es su turno en la escalera (solo en modo secuencial).
                'blocked' => $seq && ! $isDone && $key !== $openKey,
            ];
        }, self::slots($contract));
    }

    /** ¿Puede este usuario cubrir ESTE casillero? Por rol LP, por jefatura del depto, o por puesto ocupado. */
    public static function canCover(User $user, PayeeContract $contract, array $slot): bool
    {
        if ($slot['type'] === self::ROLE_LP) {
            return $user->hasRole('line-producer');
        }
        if ($slot['type'] === self::ROLE_DEPT_HOD) {
            return $contract->department_id && DB::table('production_user')
                ->where('production_id', $contract->production_id)
                ->where('user_id', $user->id)
                ->where('department_id', $contract->department_id)
                ->where('is_lead', 1)
                ->exists();
        }
        return DB::table('production_user')
            ->where('production_id', $contract->production_id)
            ->where('user_id', $user->id)
            ->where('position_id', $slot['position_id'])
            ->exists();
    }

    /**
     * El casillero DISPONIBLE que este usuario puede cubrir (o null). En escalera, solo el nivel en
     * turno está disponible → un autorizador de nivel superior espera hasta que aprueben los de abajo.
     */
    public static function slotForUser(User $user, PayeeContract $contract): ?array
    {
        foreach (self::availableSlots($contract) as $slot) {
            if (self::canCover($user, $contract, $slot)) {
                return $slot;
            }
        }
        return null;
    }

    /**
     * CANDADO DE COMPLETITUD (2026-08-25). Lo que le FALTA al trato para poder autorizarse, en
     * lenguaje llano y listo para pintar. Un Infosheet en blanco NO debe poder firmarse: la firma
     * emite el contrato y lo CONGELA, así que autorizar un stub produce un contrato vacío e
     * inmutable (fue justo lo que pasó). El mínimo acordado con el owner: puesto, departamento,
     * total de honorarios > 0 y fecha de inicio. Las fechas de trabajo quedan opcionales.
     *
     * @return array<int,string> vacío = listo para autorizar
     */
    public static function missingToAuthorize(PayeeContract $contract): array
    {
        $missing = [];
        if (trim((string) $contract->title) === '') {
            $missing[] = __('el puesto');
        }
        if (! $contract->department_id) {
            $missing[] = __('el departamento');
        }
        if ((float) $contract->fee_amount <= 0) {
            $missing[] = __('el total de honorarios');
        }
        if (! $contract->effective_date) {
            $missing[] = __('la fecha de inicio');
        }

        return $missing;
    }

    /** ¿El trato tiene lo mínimo capturado para poder autorizarse? */
    public static function isReadyToAuthorize(PayeeContract $contract): bool
    {
        return count(self::missingToAuthorize($contract)) === 0;
    }

    /** Frase para la UI: "Falta capturar el puesto, el departamento y el total de honorarios." */
    public static function missingLabel(PayeeContract $contract): string
    {
        $m = self::missingToAuthorize($contract);
        if (empty($m)) {
            return '';
        }
        $last = array_pop($m);
        $list = $m ? implode(', ', $m) . ' ' . __('y') . ' ' . $last : $last;

        return __('Falta capturar :lista.', ['lista' => $list]);
    }

    /** ¿Este usuario tiene algo pendiente por autorizar en este contrato? */
    public static function canAuthorize(User $user, PayeeContract $contract): bool
    {
        return $contract->isCrewWork()
            && self::isReadyToAuthorize($contract)
            && self::slotForUser($user, $contract) !== null;
    }

    /**
     * Registrar la autorización de $user (con su autógrafa) en el casillero que le toca, sellarla, y
     * DISPARAR la generación si con esto se completa. Devuelve el resultado para avisar al usuario.
     *
     * @return array{ok:bool,message?:string,fired?:bool,error?:bool}
     */
    public static function authorize(PayeeContract $contract, User $user, ?string $imageData, ?Request $request): array
    {
        // Candado: nunca se firma un trato incompleto (la firma emite y CONGELA).
        if (! self::isReadyToAuthorize($contract)) {
            return ['ok' => false, 'message' => self::missingLabel($contract) . ' ' . __('Complétalo en la hoja de información antes de autorizar.')];
        }

        $slot = self::slotForUser($user, $contract);
        if (! $slot) {
            return ['ok' => false, 'message' => __('No tienes un puesto pendiente por autorizar en este contrato.')];
        }

        DB::transaction(function () use ($contract, $user, $slot, $imageData, $request) {
            $auth = $contract->authorizations()->create([
                'production_id'   => $contract->production_id,
                'position_id'     => $slot['position_id'],
                'role'            => $slot['type'],
                'user_id'         => $user->id,
                'name'            => trim($user->name . ' ' . $user->lname), // nombre LEGAL, no el de créditos
                'signature_image' => $imageData,
                'accepted_at'     => now(),
                'ip_address'      => $request ? $request->ip() : null,
            ]);
            // Sella: la autógrafa y los datos entran al hash → verificable e íntegra.
            $auth->signDocument($user, $request);
        });

        if (self::isComplete($contract->fresh())) {
            return array_merge(['ok' => true], self::fire($contract->fresh(), $user));
        }

        return ['ok' => true, 'fired' => false, 'message' => __('Autorización registrada. Faltan otros autorizadores.')];
    }

    /** DISPARO (paso 3): emitir el contrato (clausulado activo) + armar el sobre + enviarlo a firma. */
    public static function fire(PayeeContract $contract, ?User $actor = null): array
    {
        if ($contract->envelopes()->exists()) {
            return ['fired' => false, 'message' => __('El contrato ya fue generado.')];
        }

        // El requisito para emitir es una PLANTILLA-contrato activa (editor HTML o PDF). El clausulado
        // subido se acepta como fallback para producciones que aún no migraron a plantillas.
        $template = ContractTemplate::activeFor($contract->production_id, $contract->concept);
        $clause   = $template ? null : self::defaultClause($contract);
        if (! $template && ! $clause) {
            return ['fired' => false, 'error' => true,
                'message' => __('Autorizado, pero falta una plantilla de contrato activa para este tipo de contrato (Producción → Plantillas).')];
        }

        // PRE-VUELO: si la ruta de firma no se puede armar (puesto vacante, auto-firma), se corta
        // ANTES de emitir. Emitir y fallar después dejaba el contrato congelado sin sobre.
        try {
            ContractEnvelopeBuilder::preflight($contract);
        } catch (\Throwable $e) {
            return ['fired' => false, 'error' => true,
                'message' => __('Autorizado, pero la ruta de firma no está lista, así que el contrato NO se emitió:') . ' ' . $e->getMessage()];
        }

        try {
            if (! $contract->isEmitted()) {
                ContractEmitter::emit($contract, $clause, null, $actor);
            }
            $envelope = ContractEnvelopeBuilder::build($contract->fresh(), $actor);
            ContractSigning::send($envelope);
        } catch (ContractEmitException $e) {
            return ['fired' => false, 'error' => true,
                'message' => __('Autorizado, pero no se pudo generar el contrato:') . ' ' . $e->getMessage()];
        } catch (ContractEnvelopeException $e) {
            return ['fired' => false, 'error' => true,
                'message' => __('Autorizado y contrato emitido, pero no se pudo armar el sobre:') . ' ' . $e->getMessage()];
        }

        return ['fired' => true, 'message' => __('Autorizado. El contrato y sus documentos se enviaron a firma.')];
    }

    /** El clausulado activo MÁS RECIENTE que aplica al subtipo del contrato (filtro en PHP, robusto). */
    public static function defaultClause(PayeeContract $contract): ?ContractClause
    {
        return ContractClause::forProduction($contract->production_id)
            ->active()
            ->orderByDesc('version')->orderByDesc('id')
            ->get()
            ->first(fn ($c) => $c->appliesToSubtype($contract->concept));
    }

    /** @var array<string,int> cache por-request del conteo (el sidebar lo pide 2 veces). */
    private static array $countCache = [];

    /**
     * BANDEJA "POR AUTORIZAR" — el DISPARADOR de descubrimiento: los tratos crew_work de la producción
     * que ESTE usuario puede autorizar AHORA: capturados (no un stub vacío del firstOrCreate), aún SIN
     * sobre (no emitidos), y con un casillero pendiente que el usuario cubre (rol LP / jefatura de depto
     * / puesto). Espejo de {@see PendingSignatures} para las autorizaciones; no depende del correo.
     */
    public static function pendingForUser(User $user, ?int $prodId = null)
    {
        $prodId = $prodId ?? CurrentProduction::id();

        return PayeeContract::query()
            ->where('concept', PayeeContract::CONCEPT_CREW)
            ->when($prodId, fn ($q) => $q->where('production_id', $prodId))
            ->whereDoesntHave('envelopes')                       // aún no se generó el contrato
            ->where(function ($w) {                               // capturado (algo del trato, no un stub)
                $w->where(fn ($x) => $x->whereNotNull('title')->where('title', '!=', ''))
                  ->orWhere(fn ($x) => $x->whereNotNull('crew_activity')->where('crew_activity', '!=', ''))
                  ->orWhere('fee_amount', '>', 0);
            })
            ->with(['payee', 'department', 'fiscalRegime'])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn ($c) => self::canAuthorize($user, $c))   // tiene un casillero que puede cubrir
            ->values();
    }

    /** Conteo ligero para el badge del sidebar (cacheado por-request). */
    public static function countForUser(User $user, ?int $prodId = null): int
    {
        $prodId = $prodId ?? CurrentProduction::id();
        $key = $user->id . ':' . ($prodId ?? '0');
        if (! array_key_exists($key, self::$countCache)) {
            self::$countCache[$key] = self::pendingForUser($user, $prodId)->count();
        }
        return self::$countCache[$key];
    }

    /**
     * Usuarios a AVISAR de que hay un trato por autorizar: quienes pueden cubrir los casilleros
     * pendientes (LP por rol, jefe del depto, u ocupantes del puesto). Con correo, distintos.
     */
    public static function authorizerRecipients(PayeeContract $contract)
    {
        $ids = collect();
        foreach (self::pendingSlots($contract) as $slot) {
            if ($slot['type'] === self::ROLE_LP) {
                $ids = $ids->merge(User::role('line-producer')->pluck('id'));
            } elseif ($slot['type'] === self::ROLE_DEPT_HOD) {
                if ($contract->department_id) {
                    $ids = $ids->merge(DB::table('production_user')
                        ->where('production_id', $contract->production_id)
                        ->where('department_id', $contract->department_id)
                        ->where('is_lead', 1)->pluck('user_id'));
                }
            } else {
                $ids = $ids->merge(DB::table('production_user')
                    ->where('production_id', $contract->production_id)
                    ->where('position_id', $slot['position_id'])->pluck('user_id'));
            }
        }
        $ids = $ids->unique()->filter()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        return User::whereIn('id', $ids->all())
            ->whereNotNull('email')->where('email', '!=', '')->get();
    }
}
