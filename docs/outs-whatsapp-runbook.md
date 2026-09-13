# Salidas · canal Meta/WhatsApp — RUNBOOK (🔴 NO VERIFICADO)

**Estado: escrito, APAGADO, sin probar contra la API real.** Flag `outs_whatsapp` en `config/features.php`
(default `false`). Mientras esté apagado, el webhook responde **404**. La captura en la app (pantalla de
Salidas + parser + turnaround) **NO depende de este flag** y va activa.

> Es la misma trampa de Browsershot: código que se ve terminado y **nunca tocó la cosa real**. Se escribió
> contra la documentación de la WhatsApp Cloud API de hoy (2026-09-12), **sin un número** y **sin haber
> llamado nunca la API**.

## Antes de encender — checklist de verificación contra la doc VIGENTE de entonces

1. **Formato del webhook (entrante).** `OutWhatsappController::extractMessage()` asume
   `entry[0].changes[0].value.messages[0].text.body` y `.from`. Confirmar la estructura real (mensajes de
   texto vs plantillas, y los *status updates* que hay que ignorar).
2. **Firma.** `signatureOk()` asume `X-Hub-Signature-256: sha256=` + `HMAC_SHA256(rawBody, app_secret)`.
   Confirmar el header y que se firma el **cuerpo crudo** (no el reparseado). Sin `app_secret` configurado,
   el webhook rechaza todo (fail-safe).
3. **Respuesta (saliente).** `reply()` hace `POST graph.facebook.com/v20.0/{phone_number_id}/messages`
   con `Authorization: Bearer {access_token}` y `type: text`. Confirmar versión de API, endpoint y cuerpo,
   y las reglas de la **ventana de 24 h** de Meta para mensajes de servicio.
4. **Handshake de verificación (GET).** `verify()` responde `hub.challenge` si `hub.verify_token` casa con
   `whatsapp_verify_token`. Confirmar los nombres de los parámetros (`hub.mode`/`hub_mode`, etc.).

## Credenciales (panel)

`/salidas/whatsapp` (permiso `settings.manage`) guarda en la tabla `app_settings`:
`whatsapp_verify_token`, `whatsapp_phone_number_id`, `whatsapp_app_secret`, `whatsapp_access_token`.

🔴 Se guardan en **texto plano** en la base — NO es un gestor de secretos. Cuando el canal se active de
verdad: evaluar **cifrado en reposo** o mover los secretos a variables de entorno.

## Reglas del bot (§6)

- **Confirma de vuelta** lo que entendió (`OutIngest::ingestText()` arma el `reply`).
- **Número no reconocido** → "No te identifico…". El emisor se resuelve por **teléfono → persona →
  puesto con autoridad** (`resolveSender()` + `OutAuthority`). ⚠ Los teléfonos (`users.phone`) son
  **texto libre sin validar**: se casa por los últimos 10 dígitos y, si dos personas comparten esos
  dígitos, NO se adivina (se trata como no identificado).
- **Formato mal escrito** → dice cómo se escribe (rama de `OutIngest::buildReply()`).

## Fuera de alcance a propósito

- **Nada de emergencias** (no está aterrizado).
- El canal registra en la **unidad principal** (`unit_id = null`). Si hace falta por unidad, resolver la
  unidad del emisor antes de `ingestText()`.
