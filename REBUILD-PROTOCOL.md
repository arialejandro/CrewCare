# REBUILD-PROTOCOL.md — Protocolo de reconstrucción por artefacto

**Qué es esto:** el **método estándar** para reconstruir CADA artefacto de la app
(view / model / controller) en la reconstrucción incremental por verticales. **No son
parches momentáneos: es arquitectura real y profesional que PRESERVA la función exacta.**
Resuelve el riesgo central que identificó el owner — *parches que funcionan al momento
pero se rompen al escalar*.

Complementa a [ROADMAP.md](ROADMAP.md) / [RECIPE.md](RECIPE.md) (el plan técnico y la
receta de ejecución ordenada) y a [VISION-PRODUCT.md](VISION-PRODUCT.md) (la estrategia
de producto): aquí se define **CÓMO se rehace cada archivo**, no qué se construye ni en
qué orden.

---

## Principio rector

**Reconstruir, no parchar.** Cada archivo se rehace con arquitectura real y profesional,
**preservando su función exacta**. El **RBAC** (`@can` / policies) reemplaza los booleanos
en BD (`admin` / `daytest`). El wrap aditivo `@if(viejo || @can)` es **SOLO el cinturón de
seguridad de la transición, NO el estado final**.

---

## Regla inviolable — Descifrar ANTES de tocar

*(lección del primer incidente del Home)*

Reconstruir **≠** borrar / reescribir a ciegas. Hay piezas que **parecen basura pero
sostienen la app** (ej. el orden de carga BS4/BS5). Antes de tocar hay que separar:

- **Contaminación** → se elimina. Vars sin uso, JS muerto, frameworks duplicados, estilos
  huérfanos.
- **Load-bearing** → se **conserva**, pero ahora de forma **intencional y documentada**, no
  accidental. Lo que se ve mal pero hace que funcione — y se anota **POR QUÉ**.

> **El oráculo del comportamiento = producción / el base local funcionando.** Esa es la
> verdad contra la que se verifica que la función se preservó. Un "arreglo" cosmético ya
> rompió el Home una vez porque el bug era carga estructural (load-bearing).

---

## Protocolo de reconstrucción por artefacto — 5 pasos

Aplica a cada **view / model / controller** que se reconstruye:

1. **Descifrar** — inventario del archivo: qué función cumple, qué datos recibe/entrega,
   quién depende de él. Clasificar **cada pieza** como **contaminación** (vars sin uso, JS
   muerto, frameworks duplicados, estilos huérfanos) o **load-bearing** (lo que se ve mal
   pero hace que funcione, **y POR QUÉ**).
   > **🔧 Matiz (owner, 2026-06-24) — bug claro ≠ load-bearing:** cuando el descifrado detecte
   > un **bug genuino** (HTML desbalanceado, anidamiento roto, lógica que nunca se ejecuta), se
   > **PROPONE y se ARREGLA explicándolo**, **no** se "preserva en silencio". El owner quiere
   > **corregir** estos problemas, no arrastrarlos. **Preservar-tal-cual** se reserva para
   > comportamiento **genuinamente load-bearing** (lo que se ve mal pero sostiene la app) — y
   > aun así se **marca (flag) + se propone el fix**. La copy/textos del usuario sí se conservan
   > verbatim (los typos de redacción son decisión del owner, no bugs de código).

2. **Definir el objetivo** — la versión limpia: Blade que extiende el layout correctamente,
   assets cargados desde donde corresponde (**no inyectados en el `<head>` de la vista**),
   **RBAC en vez de `admin` / `daytest`**, componentes donde se repite.

3. **Reconstruir bloqueando el comportamiento** — construir limpio, pero con **función
   idéntica** a la de producción.

4. **Verificar contra el oráculo** — sin errores de consola, sin Quirks mode, sin regresión
   visual ni funcional (**capturas antes / después**).

5. **Retirar lo viejo** — **SOLO tras verificar**, eliminar el flag booleano y los assets
   muertos de ESE artefacto (huérfanos / código muerto del artefacto verificado). **El
   booleano muere cuando su reemplazo RBAC está probado, no antes.**
   > **🔒 Regla del owner (2026-06-24):** el **borrado de los backups (`_legacy_backup/` y
   > cualquier respaldo) NO es parte del paso 5 — se DIFIERE al cierre del proyecto** ("el final
   > final de todo"). El paso 5 sigue retirando el booleano y borrando huérfanos/código muerto del
   > artefacto, **pero las carpetas de respaldo se conservan** hasta ese cierre y se borran al final.

---

## Cómo encaja con la estrategia

- Es la forma **concreta** de ejecutar el **estrangulamiento por verticales** ya decidido
  (ver [ROADMAP.md](ROADMAP.md) / [RECIPE.md](RECIPE.md)).
- **Método incremental, nunca reescritura masiva desde cero.**
- **Siempre explicar el código al owner** para que lo entienda y lo adapte a las necesidades
  inmediatas de set (ver [VISION-PRODUCT.md](VISION-PRODUCT.md)).
