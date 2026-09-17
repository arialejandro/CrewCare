<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No se pudo emitir el contrato. Si `$missing` no está vacío, son los campos del CONTRATANTE
 * que faltan en la configuración (no se emite una carátula con el contratante incompleto).
 */
class ContractEmitException extends RuntimeException
{
    public array $missing = [];

    public static function missingContractor(array $missing): self
    {
        $e = new self('Faltan datos del contratante: ' . implode(', ', $missing));
        $e->missing = $missing;
        return $e;
    }
}
