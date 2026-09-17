<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No se pudo crear/gestionar el sobre. Sobre todo: el puesto de la ruta de firma está VACANTE o lo
 * ocupan DOS personas → NO se elige por cuenta propia, se avisa claro al crear el sobre.
 */
class ContractEnvelopeException extends RuntimeException
{
    public static function vacantPosition(string $roleLabel): self
    {
        return new self("El puesto para «{$roleLabel}» está vacante: asígnalo antes de crear el sobre.");
    }

    public static function duplicatePosition(string $roleLabel): self
    {
        return new self("El puesto para «{$roleLabel}» lo ocupan dos o más personas: déjalo en una sola antes de crear el sobre.");
    }

    public static function positionNotConfigured(string $roleLabel): self
    {
        return new self("Falta configurar el puesto de «{$roleLabel}» en la ruta de firma (Producción).");
    }
}
