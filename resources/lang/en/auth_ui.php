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

    // Password recovery (auth/passwords/email + reset).
    'reset_link_title'    => 'Reset your password',
    'reset_link_subtitle' => 'Enter your email and we\'ll send you a link to set a new one.',
    'send_reset_link'     => 'Send link',
    'sending'             => 'Sending…',
    'reset_title'         => 'New password',
    'reset_subtitle'      => 'Choose a new password for your account.',
    'new_password'        => 'New password',
    'new_password_ph'     => 'Create a password',
    'confirm_password'    => 'Confirm password',
    'confirm_password_ph' => 'Repeat the password',
    'save_password'       => 'Save password',
    'saving'              => 'Saving…',
    'back_to_login'       => 'Back to sign in',
    'confirm_title'       => 'Confirm your password',
    'confirm_subtitle'    => 'For your security, re-enter your password to continue.',
    'confirm_btn'         => 'Confirm',
];
