<?php

/**
 * Visible login-screen strings (resources/views/auth/login.blade.php).
 *
 * First surface migrated in the incremental i18n rollout. Used via
 * __('auth_ui.key'). es/ and en/ mirror each other exactly: same keys.
 * (Kept separate from auth.php, which holds Laravel's authentication messages.)
 *
 * (2026-08-07) Copyright year is dynamic (:year).
 */
return [

    'subtitle'        => 'Health & Safety',
    'email'           => 'Email',
    'email_ph'        => 'Enter your email',
    'password'        => 'Password',
    'password_ph'     => 'Enter your password',
    'sign_in'         => 'Sign In',
    'signing_in'      => 'Signing in…',
    'show_password'   => 'Show password',
    'hide_password'   => 'Hide password',
    'forgot_password' => 'Forgot your password?',
    'copyright'       => '© CrewCare :year · All Rights Reserved',
];
