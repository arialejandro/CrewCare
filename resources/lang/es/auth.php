<?php

/**
 * Mensajes de autenticación de Laravel EN ESPAÑOL.
 *
 * (2026-08-07) Antes NO existía este archivo → `trans('auth.failed')` caía al
 * inglés de lang/en/auth.php aunque el locale fuera 'es' (por eso el aviso de
 * credenciales incorrectas salía en inglés en una pantalla en español). Estas son
 * las 3 claves estándar del scaffold de Laravel (mismas que en/auth.php).
 */
return [

    'failed'   => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña indicada es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Inténtalo de nuevo en :seconds segundos.',

];
