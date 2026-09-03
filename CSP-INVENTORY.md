# CSP — Inventario estático de `<script>`/handlers/estilos en línea (2026-08-30)

> **Solo inventario. NADA se movió.** La CSP sigue en **modo REPORTE**
> (`Content-Security-Policy-Report-Only`) hasta que el owner vea el alcance. Búsqueda ESTÁTICA sobre
> `resources/views` (no navegando — los scripts de vistas no visitadas también cuentan).

## Totales
| Qué | Conteo | ¿Bloquea `script-src 'self'`? |
|---|---:|---|
| `<script>` **en línea** (sin `src`) | **117** en ~96 vistas | 🔴 Sí — hay que darles nonce o moverlos |
| Manejadores **`on*=`** (`onclick`, `onchange`, `onsubmit`…) | **81** | 🔴 Sí — el nonce NO los cubre; hay que pasarlos a `addEventListener` |
| `<script src="…">` (externos, mismo origen) | 60 | ✅ No — ya cumplen `'self'` |
| **`style="…"` en línea** | **1314** | ⚪ No para *script*-src — es `style-src`; se mantiene `'unsafe-inline'` (volumen enorme, riesgo bajo) |

**Entrelazado con Blade:** de las ~96 vistas con `<script>` en línea, **prácticamente todas** referencian
Blade en el archivo (`route()`, `@json`, `{{ }}`, meta CSRF). Es decir: el JS en línea es **pegamento
específico de cada vista** (URLs de rutas, token CSRF, config `@json`), **no** librerías portables. Mover
todo a archivos `.js` estáticos NO es trivial: cada bloque tendría que recibir esos valores por
`data-*`/config, no por interpolación.

## Trivial de mover vs entretejido
- **Entretejido (la gran mayoría):** los 117 bloques viven en vistas que interpolan Blade. Sacarlos a un
  archivo exige plumbing de datos (data-attributes / objeto de config) por cada uno. NO es copiar-pegar.
- **Trivial de mover (minoría):** bloques puramente estáticos (sin `{{ }}`/`route()`), típicos de widgets
  reutilizables. Son pocos; no mueven la aguja por sí solos.
- **Los 81 `on*=`** son mecánicos pero numerosos: `onclick="foo()"` → `addEventListener`. **Ninguno** se
  salva con nonce (la CSP bloquea los handlers en atributo pase lo que pase, salvo `unsafe-hashes`).

## Top vistas por `<script>` en línea
| # | Vista |
|---:|---|
| 10 | `admin/dailyreports/create.blade.php` |
| 8 | `inicio.blade.php` |
| 6 | `layouts/app.blade.php` |
| 5 | `admin/injuryreportcreate.blade.php` |
| 4 | `contracts/sign.blade.php`, `ambulance/execute.blade.php`, `admin/riskmaps/edit.blade.php` |
| 3 | `profile.blade.php`, `payee/intake.blade.php`, `inspection/execute.blade.php`, `auth/register.blade.php`, `admin/unsafecondnotification.blade.php`, `admin/sfx-effects/show.blade.php`, `admin/hazardnotification.blade.php`, `admin/dailyreports/show-legacy.blade.php` |

## Top vistas por manejadores `on*=`
`admin/callsheet/package` (5), `admin/catalogo/index` (4), `inspection/acta` (3), `admin/medevac/show` (3),
luego `vehicle/vehicle-show`, `transport/orders/edit`, `permits/show`, `payee/show`, `inspection/records`,
`epi/index` (2 c/u), y una cola larga de 1.

## Estimación para llegar a `script-src 'self'` sin `unsafe-inline`
**Camino recomendado: NONCE por petición** (no mover 117 bloques a archivos).
1. Middleware que genera un nonce aleatorio por request y lo mete en el `script-src` de la CSP.
2. Estampar `nonce="{{ $cspNonce }}"` en cada `<script>` en línea. Como casi todos pasan por
   `@push('scripts')` + `layouts/app`, un componente/directiva Blade compartida (`<x-script>` o `@script`)
   lo inyecta en un solo punto → los 117 se cubren editando el envoltorio, no 117 archivos.
3. **Convertir los 81 `on*=` a `addEventListener`** — esto SÍ es sitio-por-sitio (el nonce no los cubre).
4. `style-src` se queda con `'unsafe-inline'` (1314 estilos en línea; fuera de alcance de este objetivo).

**Esfuerzo:** plumbing del nonce (chico) + envoltorio Blade (chico) + **los 81 handlers (el grueso, mecánico
pero disperso)**. Es una pasada acotada de ~1–2 días, sin lógica nueva, tocando ~150 sitios. **No hay
bloqueadores** — es volumen, no dificultad. Recomiendo hacerlo DESPUÉS de leer unos días de reportes de la
CSP en modo observación, por si aparece algún origen externo (CDN/inline de terceros) que no se ve estático.
