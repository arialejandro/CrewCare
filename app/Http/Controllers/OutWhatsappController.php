<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\Features;
use App\Support\OutAuthority;
use App\Support\OutIngest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OutWhatsappController — CANAL META/WHATSAPP para registrar salidas (§6).
 *
 * 🔴🔴🔴 CAPA NO VERIFICADA — NACE APAGADA (flag `outs_whatsapp`).
 * Se escribió contra la documentación de la Cloud API de hoy, SIN un número real y SIN haber tocado
 * NUNCA la API. Es la misma trampa de Browsershot: código que se ve terminado y nunca tocó la cosa real.
 * ANTES de encenderla hay que revisar, contra la documentación VIGENTE de entonces:
 *   · el formato exacto del webhook (entry[].changes[].value.messages[].text.body / .from),
 *   · el esquema de la firma (X-Hub-Signature-256 = 'sha256=' + HMAC_SHA256(rawBody, app_secret)),
 *   · el endpoint y el cuerpo de la respuesta (Graph API /{phone_number_id}/messages).
 * Runbook: docs/outs-whatsapp-runbook.md.
 *
 * TRES REGLAS DEL BOT (§6): confirma de vuelta lo que entendió · número no reconocido → "no te
 * identifico" · formato mal escrito → dice cómo se escribe. El emisor se resuelve por
 * TELÉFONO → PERSONA → PUESTO CON AUTORIDAD en su depto (OutAuthority). 🔴 Nada de emergencias.
 */
class OutWhatsappController extends Controller
{
    /** Handshake de verificación de Meta (GET): responde hub.challenge si el verify_token casa. */
    public function verify(Request $request)
    {
        abort_unless(Features::enabled('outs_whatsapp'), 404);

        $mode      = $request->query('hub_mode', $request->query('hub.mode'));
        $token     = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expected = AppSetting::get('whatsapp_verify_token');
        if ($mode === 'subscribe' && $expected && hash_equals((string) $expected, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('forbidden', 403);
    }

    /** Recepción de mensajes (POST). Verifica firma, resuelve emisor, registra, y contesta. */
    public function webhook(Request $request)
    {
        abort_unless(Features::enabled('outs_whatsapp'), 404);

        // 1) Firma. Sin app_secret configurado NO se procesa (fail-safe): un webhook sin firma verificable
        //    no se cree. 🔴 Verificar el esquema exacto contra la doc vigente antes de encender.
        $secret = AppSetting::get('whatsapp_app_secret');
        $raw    = $request->getContent();
        $sig    = (string) $request->header('X-Hub-Signature-256', '');
        if (! $secret || ! $this->signatureOk($raw, $sig, $secret)) {
            return response('invalid signature', 403);
        }

        // 2) Extraer texto + remitente del payload (formato Cloud API — NO VERIFICADO contra la real).
        $msg = $this->extractMessage($request->json()->all());
        if ($msg === null) {
            return response()->json(['status' => 'ignored']);   // status updates, etc.
        }
        [$fromPhone, $text] = $msg;

        // 3) Resolver emisor: teléfono → persona → autoridad por puesto.
        $user = $this->resolveSender($fromPhone);
        if ($user === null) {
            $this->reply($fromPhone, 'No te identifico. Pide que registren tu número para poder usar este canal.');

            return response()->json(['status' => 'unknown_sender']);
        }

        $pid = CurrentProduction::id();
        if (! $pid) {
            $this->reply($fromPhone, 'No hay una producción activa ahora mismo.');

            return response()->json(['status' => 'no_production']);
        }

        $allowed = OutAuthority::seesAll($user) ? null : OutAuthority::authorityDepartmentIds($user);
        if ($allowed !== null && empty($allowed)) {
            $this->reply($fromPhone, 'Tu puesto no tiene autoridad para registrar salidas de ningún departamento.');

            return response()->json(['status' => 'no_authority']);
        }

        // 4) Registrar (unidad principal por ahora en este canal; ver runbook) y contestar lo entendido.
        $report = OutIngest::ingestText($text, $pid, null, \App\Models\DepartmentOut::SOURCE_WHATSAPP, $user->id, Carbon::now(), $allowed);
        $this->reply($fromPhone, $report['reply']);

        return response()->json(['status' => 'ok']);
    }

    /** Panel de credenciales (settings.manage). El controlador de webhook las lee de app_settings. */
    public function settings(Request $request)
    {
        return view('admin.outs.whatsapp-settings', [
            'enabled'         => Features::enabled('outs_whatsapp'),
            'verify_token'    => AppSetting::get('whatsapp_verify_token'),
            'phone_number_id' => AppSetting::get('whatsapp_phone_number_id'),
            'has_secret'      => (bool) AppSetting::get('whatsapp_app_secret'),
            'has_token'       => (bool) AppSetting::get('whatsapp_access_token'),
            'webhook_url'     => url('/webhooks/outs/whatsapp'),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'verify_token'    => ['nullable', 'string', 'max:191'],
            'phone_number_id' => ['nullable', 'string', 'max:100'],
            'app_secret'      => ['nullable', 'string', 'max:191'],
            'access_token'    => ['nullable', 'string', 'max:500'],
        ]);

        AppSetting::set('whatsapp_verify_token', $data['verify_token'] ?? null);
        AppSetting::set('whatsapp_phone_number_id', $data['phone_number_id'] ?? null);
        // Secretos: sólo se sobrescriben si vienen con valor (dejar en blanco = conservar el actual).
        if (! empty($data['app_secret'])) {
            AppSetting::set('whatsapp_app_secret', $data['app_secret']);
        }
        if (! empty($data['access_token'])) {
            AppSetting::set('whatsapp_access_token', $data['access_token']);
        }

        return back()->with('success', 'Credenciales guardadas. Recuerda: la capa sigue SIN VERIFICAR hasta probarla contra la API real.');
    }

    // ---------------------------------------------------------------------------------------
    // Internos (NO VERIFICADOS contra la API real)
    // ---------------------------------------------------------------------------------------

    private function signatureOk(string $raw, string $header, string $secret): bool
    {
        if (strpos($header, 'sha256=') !== 0) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $header);
    }

    /**
     * Saca [telefono, texto] del payload de la Cloud API, o null si no es un mensaje de texto.
     * 🔴 Estructura NO verificada contra la API real — revisar antes de encender.
     */
    private function extractMessage(array $payload): ?array
    {
        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        if (! is_array($message)) {
            return null;
        }
        $from = $message['from'] ?? null;
        $text = $message['text']['body'] ?? null;
        if (! $from || ! is_string($text)) {
            return null;
        }

        return [(string) $from, $text];
    }

    /** Teléfono → usuario activo. Normaliza a dígitos y casa por los últimos 10 (formato MX). */
    private function resolveSender(string $phone): ?User
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 10) {
            return null;
        }
        $last10 = substr($digits, -10);

        $id = DB::table('users')
            ->where('activo', 1)
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')','') LIKE ?", ['%' . $last10])
            ->limit(2)
            ->pluck('id');

        // Si dos personas comparten los últimos 10 dígitos, NO se adivina (ambiguo → no identificado).
        if ($id->count() !== 1) {
            return null;
        }

        return User::find($id->first());
    }

    /** Contesta al remitente por la Graph API. 🔴 NO VERIFICADO — best-effort, no rompe si falla. */
    private function reply(string $toPhone, string $body): void
    {
        $phoneNumberId = AppSetting::get('whatsapp_phone_number_id');
        $accessToken   = AppSetting::get('whatsapp_access_token');
        if (! $phoneNumberId || ! $accessToken) {
            Log::info('[outs-whatsapp] reply omitido (sin credenciales)', ['to' => $toPhone]);

            return;
        }
        try {
            Http::withToken($accessToken)
                ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $toPhone,
                    'type'              => 'text',
                    'text'              => ['body' => $body],
                ]);
        } catch (\Throwable $e) {
            Log::warning('[outs-whatsapp] reply falló', ['error' => $e->getMessage()]);
        }
    }
}
