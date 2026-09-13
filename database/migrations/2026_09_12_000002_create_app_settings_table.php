<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * app_settings — almacén clave/valor a nivel INSTANCIA para configuración que no vive en .env (2026-09-12).
 *
 * Nace para el panel de credenciales del canal Meta/WhatsApp de Salidas (capa 6, APAGADA). Genérico y
 * aditivo: cualquier integración futura puede colgar sus llaves aquí. Los valores son texto; los
 * secretos (app_secret, access_token) se guardan tal cual — 🔴 NO es un secret manager (ver runbook):
 * cuando la integración se active de verdad, evaluar cifrado en reposo / mover a variables de entorno.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_settings')) {
            return;
        }
        Schema::create('app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
