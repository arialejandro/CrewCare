<?php

namespace App\Support;

/**
 * AVISO DE PRIVACIDAD — fuente única de la VERSIÓN vigente.
 * (2026-07-24 · PIEZA 3, corrida 2/2)
 *
 * ⚠ EL TEXTO ES UN BORRADOR PENDIENTE DE REDACCIÓN LEGAL. Está deliberadamente marcado como tal
 * en la vista (`avisos.privacidad`) para que nadie lo confunda con un aviso definitivo. Lo que
 * SÍ es definitivo es el MECANISMO: se pide antes de recabar datos de salud, y queda registrado
 * quién aceptó, cuándo y QUÉ VERSIÓN.
 *
 * CÓMO SE SUSTITUYE (dos pasos, sin tocar nada más):
 *   1. Reescribir `resources/views/avisos/privacidad.blade.php` con el texto legal definitivo.
 *   2. Cambiar `VERSION` aquí (p. ej. a '2026-09-01-v1').
 * Al cambiar la versión, TODOS vuelven a ver el aviso la próxima vez que entren al cuestionario
 * y su aceptación queda registrada contra el texto nuevo. Las aceptaciones de la versión
 * anterior NO se borran ni se pisan: siguen probando qué aceptó cada quien y cuándo.
 *
 * Por eso la versión no es un número de secuencia sino una fecha: al leer un registro de
 * consentimiento se sabe de un vistazo a qué texto corresponde, sin consultar un changelog.
 */
class PrivacyNotice
{
    /**
     * Versión VIGENTE del aviso. Cambiarla = volver a pedir consentimiento a todos.
     *
     * DEFINITIVA desde 2026-07-24: se definió el correo de derechos ARCO (contacto@crewcare.mx)
     * y se retiró el sufijo `-borrador`, por lo que `esBorrador()` devuelve false y la
     * advertencia deja de pintarse. El formato es una FECHA, no un número de secuencia: al leer
     * un registro de `privacy_consents` se sabe de un vistazo a qué texto corresponde. Si más
     * adelante se publica un texto nuevo, cambiar esta constante (p. ej. a otra fecha) vuelve a
     * pedir consentimiento a todos; las aceptaciones anteriores NO se pisan.
     */
    const VERSION = '2026-07-24';

    /** Vista con el cuerpo del aviso. */
    const VISTA = 'avisos.privacidad';

    /**
     * ¿La versión vigente sigue siendo un borrador? La vista lo usa para pintar la advertencia
     * sin que haya que acordarse de quitarla a mano el día del texto definitivo.
     *
     * @return bool
     */
    public static function esBorrador()
    {
        return substr(self::VERSION, -9) === '-borrador';
    }
}
