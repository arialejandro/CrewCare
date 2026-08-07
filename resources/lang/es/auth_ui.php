<?php

/**
 * Textos visibles de la pantalla de login (resources/views/auth/login.blade.php).
 *
 * Primera superficie migrada del rollout i18n incremental. Se usan con
 * __('auth_ui.clave'). es/ y en/ se reflejan exactamente: mismas claves.
 * (Separado de auth.php, que contiene los mensajes de autenticación de Laravel.)
 *
 * (2026-08-07) El login es interfaz en español, NO términos de cine → "Correo"/
 * "Contraseña" (no "Email"/"Password"). El año del pie es dinámico (:year).
 */
return [

    'subtitle'        => 'Salud y Seguridad',
    'email'           => 'Correo',
    'email_ph'        => 'Ingresa tu correo',
    'password'        => 'Contraseña',
    'password_ph'     => 'Ingresa tu contraseña',
    'sign_in'         => 'Entrar',
    'signing_in'      => 'Entrando…',
    'show_password'   => 'Mostrar contraseña',
    'hide_password'   => 'Ocultar contraseña',
    'forgot_password' => '¿Olvidaste tu contraseña?',
    'copyright'       => '© CrewCare :year · Todos los derechos reservados',
];
