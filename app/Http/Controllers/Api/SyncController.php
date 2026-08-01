<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * SUBSISTEMA SYNC / API (Módulo 12 — offline-first, aditivo por UUID).
 *
 * Recibe un LOTE de reportes de seguridad creados sin conexión (offline) por el
 * PWA/Service Worker y hace un UPSERT idempotente por 'uuid'. Cada tabla de
 * reporte ya tiene la columna 'uuid' (única, nullable) y los modelos auto-generan
 * un uuid al crear (trait GeneratesUuidKey). El cliente genera su propio uuid al
 * capturar offline; así, si el Service Worker reintenta el mismo POST (red
 * intermitente), el segundo intento ACTUALIZA en vez de DUPLICAR.
 *
 * AUTENTICACIÓN — ENDURECER EN PRODUCCIÓN:
 *   Por ahora este endpoint va bajo el middleware 'auth' de SESIÓN (mismo origen)
 *   declarado en routes/web.php. Es suficiente para un PWA mismo-origen que ya
 *   tiene la cookie de sesión. Para un cliente offline "de verdad" (app instalada
 *   que sincroniza en segundo plano sin sesión web viva) esto DEBE migrarse a un
 *   token Bearer (Laravel Sanctum: auth:sanctum) para no depender de la cookie.
 *   Ver routes/web.php para la nota de por qué NO va en routes/api.php todavía.
 *
 * CSRF — al vivir en el grupo 'web', el POST exige token CSRF; el PWA mismo-origen
 * puede incluir el X-CSRF-TOKEN (meta) en el fetch. Documentado también en la ruta.
 */
class SyncController extends Controller
{
    /**
     * Mapa type -> clase de modelo Eloquent. Se referencian como strings (FQCN)
     * para no importar los 5 modelos; se instancian/usan dinámicamente abajo.
     *
     * @var array<string, string>
     */
    private $modelMap = [
        'hazard'   => \App\Models\hazardnotification::class,
        'unsafe'   => \App\Models\unsafecond::class,
        'injury'   => \App\Models\InjuryReport::class,
        'scouting' => \App\Models\ScoutingReport::class,
        'daily'    => \App\Models\DailyReport::class,
    ];

    /**
     * UPSERT idempotente de un lote de reportes offline.
     *
     * Body JSON esperado:
     *   { "reports": [ { "type": "daily", "uuid": "...", "data": { ..campos.. } }, ... ] }
     * También se tolera que los campos vengan al nivel del objeto (sin 'data').
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function up(Request $request)
    {
        // Tolerante: aceptamos { reports: [...] } o directamente un arreglo en el body.
        $reports = $request->input('reports');
        if (!is_array($reports)) {
            $all = $request->all();
            // Si el body ES una lista (array secuencial) de items, úsala tal cual.
            $reports = (is_array($all) && array_keys($all) === range(0, count($all) - 1)) ? $all : [];
        }

        $results = [];
        $synced  = 0;

        foreach ($reports as $item) {
            // Cada item se procesa AISLADO: una falla NO tumba a los demás (idempotencia
            // parcial). NO usamos una transacción global a propósito: queremos que los
            // items buenos se persistan aunque uno venga corrupto.
            if (!is_array($item)) {
                $results[] = ['uuid' => null, 'type' => null, 'id' => null, 'status' => 'error', 'reason' => 'invalid_item'];
                continue;
            }

            $type = isset($item['type']) ? $item['type'] : null;
            $uuid = isset($item['uuid']) ? $item['uuid'] : null;

            try {
                // 1) type conocido.
                if ($type === null || !isset($this->modelMap[$type])) {
                    $results[] = ['uuid' => $uuid, 'type' => $type, 'id' => null, 'status' => 'error', 'reason' => 'unknown_type'];
                    continue;
                }

                // 2) uuid presente (es la llave de idempotencia).
                if ($uuid === null || $uuid === '') {
                    $results[] = ['uuid' => null, 'type' => $type, 'id' => null, 'status' => 'error', 'reason' => 'missing_uuid'];
                    continue;
                }

                $modelClass = $this->modelMap[$type];
                /** @var \Illuminate\Database\Eloquent\Model $instance */
                $instance = new $modelClass();
                $table    = $instance->getTable();

                // 3) DEFENSIVO PARA PROD: si la columna 'uuid' aún no existe (SQL no aplicado),
                //    no intentamos el upsert por uuid (rompería). Se reporta y se sigue.
                if (!Schema::hasColumn($table, 'uuid')) {
                    $results[] = ['uuid' => $uuid, 'type' => $type, 'id' => null, 'status' => 'error', 'reason' => 'uuid_column_missing'];
                    continue;
                }

                // 4) Campos entrantes: usa 'data' si viene; si no, el propio item (tolerante).
                $data = (isset($item['data']) && is_array($item['data'])) ? $item['data'] : $item;

                // 5) Whitelist: SOLO columnas $fillable del modelo. Evita inyectar basura o
                //    campos de control. Nunca permitimos sobreescribir 'id'.
                $fillable   = $instance->getFillable();
                $attributes = [];
                foreach ($fillable as $col) {
                    if ($col === 'id') {
                        continue;
                    }
                    if (array_key_exists($col, $data)) {
                        $attributes[$col] = $data[$col];
                    }
                }
                unset($attributes['id']);
                // Preserva SIEMPRE el uuid del cliente (aunque no viniera en 'data').
                $attributes['uuid'] = $uuid;

                // 6) Upsert por uuid.
                $existing = $modelClass::where('uuid', $uuid)->first();
                if ($existing !== null) {
                    $existing->fill($attributes);
                    $existing->save();
                    $results[] = ['uuid' => $uuid, 'type' => $type, 'id' => $existing->getKey(), 'status' => 'updated'];
                    $synced++;
                } else {
                    $created = $modelClass::create($attributes);
                    $results[] = ['uuid' => $uuid, 'type' => $type, 'id' => $created->getKey(), 'status' => 'created'];
                    $synced++;
                }
            } catch (\Throwable $e) {
                // Mensaje corto (no exponemos el stack). El SW puede reintentar el item.
                $results[] = [
                    'uuid'   => $uuid,
                    'type'   => $type,
                    'id'     => null,
                    'status' => 'error',
                    'reason' => substr($e->getMessage(), 0, 200),
                ];
            }
        }

        return response()->json([
            'synced'  => $synced,
            'results' => $results,
        ], 200);
    }
}
