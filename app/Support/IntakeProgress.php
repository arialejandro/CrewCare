<?php

namespace App\Support;

use App\Models\Payee;

/**
 * Resumen de completitud del INTAKE (la mitad PERSONAL del Infosheet), SOLO LECTURA.
 *
 * Espeja la heurística de IntakeController::completedSteps() para pintar "qué falta" en la tarjeta
 * del perfil SIN tocar el flujo del intake (aditivo). Es una comodidad de despliegue: si el intake
 * cambia su heurística, actualizar aquí. No decide nada — solo muestra qué secciones faltan.
 */
class IntakeProgress
{
    /** Etiquetas por sección, en el orden del asistente. */
    public const LABELS = [
        'identity'  => 'Identidad',
        'fiscal'    => 'Fiscales',
        'documents' => 'Documentos',
        'emergency' => 'Emergencia',
        'equipment' => 'Equipo',
        'logistics' => 'Logística',
    ];

    /** @return array<string,bool> sección => completa */
    public static function steps(Payee $payee): array
    {
        return [
            'identity'  => (bool) ($payee->nationality || $payee->addr_cp || $payee->elector_credential),
            'fiscal'    => (bool) ($payee->rfc || $payee->bank_clabe || $payee->fiscalRegimes->isNotEmpty()),
            'documents' => $payee->documents->isNotEmpty(),
            'emergency' => (bool) ($payee->emergency_contact_name || $payee->beneficiaries->isNotEmpty()),
            'equipment' => $payee->declaredEquipment->isNotEmpty(),
            'logistics' => (bool) $payee->shirt_size,
        ];
    }

    /** Secciones que faltan (claves). */
    public static function missing(Payee $payee): array
    {
        return array_keys(array_filter(self::steps($payee), fn ($done) => ! $done));
    }

    public static function isComplete(Payee $payee): bool
    {
        return self::missing($payee) === [];
    }
}
