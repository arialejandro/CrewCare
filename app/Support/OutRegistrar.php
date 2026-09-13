<?php

namespace App\Support;

use App\Models\DepartmentOut;
use App\Models\IndividualOut;
use Carbon\Carbon;

/**
 * OutRegistrar — SERVICIO DE REGISTRO de salidas, SEPARADO de la pantalla, para que cualquier canal
 * (la app, y mañana el webhook de la capa Meta) lo llame IGUAL.
 *
 * Recibe datos YA RESUELTOS (shoot_date + out_at reales; la ventana la resolvió OutWindow antes). Una
 * salida por depto/día/unidad y una por persona/día/unidad: corregir una hora mal puesta = updateOrCreate
 * sobre la misma fila (fácil, como pide §3). NO decide a qué día pertenece: eso es de OutWindow, y si
 * quedó sin resolver NO se llama aquí (no se asigna a ciegas).
 */
class OutRegistrar
{
    /**
     * Registra (o corrige) la salida de un DEPARTAMENTO.
     */
    public static function departmentOut(
        int $productionId,
        ?int $unitId,
        string $shootDate,
        int $departmentId,
        Carbon $outAt,
        string $source = DepartmentOut::SOURCE_APP,
        ?int $registeredById = null,
        ?string $note = null
    ): DepartmentOut {
        return DepartmentOut::updateOrCreate(
            [
                'production_id' => $productionId,
                'unit_id'       => $unitId,
                'shoot_date'    => $shootDate,
                'department_id' => $departmentId,
            ],
            [
                'out_at'           => $outAt,
                'source'           => $source,
                'registered_by_id' => $registeredById,
                'note'             => $note,
            ]
        );
    }

    /**
     * Registra (o corrige) la salida INDIVIDUAL de una persona (a distinta hora que su equipo).
     */
    public static function individualOut(
        int $productionId,
        ?int $unitId,
        string $shootDate,
        int $userId,
        ?int $departmentId,
        Carbon $outAt,
        string $source = IndividualOut::SOURCE_APP,
        ?int $registeredById = null,
        ?string $note = null
    ): IndividualOut {
        return IndividualOut::updateOrCreate(
            [
                'production_id' => $productionId,
                'unit_id'       => $unitId,
                'shoot_date'    => $shootDate,
                'user_id'       => $userId,
            ],
            [
                'department_id'    => $departmentId,
                'out_at'           => $outAt,
                'source'           => $source,
                'registered_by_id' => $registeredById,
                'note'             => $note,
            ]
        );
    }
}
