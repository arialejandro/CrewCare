@php
    // Nombre de la producción (fuente única) con fallback a la marca; logo de la producción =
    // logo del cliente si está configurado, si no el de CrewCare.
    $prodName = $roster['production'] ?: ($branding['brand_name'] ?? 'CrewCare');
    $logo     = ($branding['client_logo'] ?? '') ?: asset('img/logo-cc-usrs.svg');
    $issued   = now()->format('d/m/Y');
    $purpose  = trim($purpose ?? '');
@endphp
<!doctype html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $prodName }} | Crew List</title>
    <style>
        /* ============================================================================
           CREW LIST — DOCUMENTO vertical (no hoja de cálculo). FORMATO del crew list impreso
           que se circula en producción; ORDEN jerárquico (ver CrewRosterBuilder).
           Salida = window.print() (misma vía que el resto de documentos; dompdf/Browsershot
           descartados por el owner). Autocontenido: sin Bootstrap ni frameworks.
           ============================================================================ */
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            color: #14181f; background: #6b7280;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Barra de acciones (solo pantalla) ── */
        .toolbar {
            position: sticky; top: 0; z-index: 10;
            display: flex; flex-wrap: wrap; align-items: center; gap: .6rem;
            padding: .7rem 1rem; background: #111827; color: #e5e7eb;
            box-shadow: 0 2px 10px rgba(0,0,0,.35);
        }
        .toolbar .spacer { flex: 1; }
        .tb-btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .55rem .9rem; border-radius: 10px; border: 1px solid transparent;
            font: 600 .88rem 'Poppins', sans-serif; cursor: pointer; text-decoration: none;
        }
        .tb-btn--primary { background: #2563eb; color: #fff; }
        .tb-btn--primary:hover { background: #1d4ed8; }
        .tb-btn--ghost { background: transparent; color: #cbd5e1; border-color: #374151; }
        .tb-btn--ghost:hover { background: #1f2937; color: #fff; }
        .tb-note {
            width: 100%; margin: .2rem 0 0; font-size: .78rem; line-height: 1.4; color: #cbd5e1;
        }
        .tb-note b { color: #fde68a; }

        /* ── Hoja (pantalla) ── */
        .sheet {
            width: 216mm; max-width: calc(100% - 24px);
            margin: 18px auto; padding: 16mm 14mm;
            background: #fff; color: #14181f;
            box-shadow: 0 12px 40px rgba(0,0,0,.35);
            position: relative;
        }

        /* Encabezado del documento (pág. 1) */
        .doc-head {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border-bottom: 2px solid #14181f; padding-bottom: 10px; margin-bottom: 14px;
        }
        .doc-head__logo { max-height: 46px; max-width: 190px; width: auto; display: block; }
        .doc-head__mid { flex: 1; text-align: center; }
        .doc-head__title { margin: 0; font-size: 1.15rem; font-weight: 700; letter-spacing: .01em; }
        .doc-head__title span { color: #6b7280; font-weight: 600; }
        .doc-head__date { text-align: right; font-size: .8rem; color: #4b5563; white-space: nowrap; }

        /* Tabla del roster */
        table.roster { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.roster thead th {
            background: #f3f4f6; color: #374151;
            font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
            text-align: left; padding: 6px 8px; border-bottom: 1px solid #d1d5db;
        }
        col.c-cargo { width: 23%; } col.c-name { width: 26%; }
        col.c-email { width: 29%; } col.c-phone { width: 22%; }

        tr.band td {
            background: #1f2937; color: #fff;
            font-size: .82rem; font-weight: 700; letter-spacing: .02em;
            padding: 6px 8px;
        }
        tr.person td {
            font-size: .78rem; line-height: 1.25; color: #14181f;
            padding: 4px 8px; border-bottom: .5px solid #e5e7eb; vertical-align: top;
            word-break: break-word; overflow-wrap: anywhere;
        }
        tr.person td.c-cargo { color: #374151; }
        tr.person td.c-phone strong { font-weight: 700; }
        .roster-empty { padding: 24px; text-align: center; color: #6b7280; }

        /* Pie (pantalla) */
        .doc-foot-screen {
            margin-top: 16px; padding-top: 8px; border-top: 1px solid #d1d5db;
            font-size: .72rem; color: #6b7280; display: flex; justify-content: space-between;
        }

        /* Cabecera/pie/​marca corriente (solo impresión) */
        .print-run-head, .print-run-foot { display: none; }
        .watermark { display: none; }

        /* ============================ IMPRESIÓN ============================ */
        @media print {
            @page {
                size: letter portrait;
                margin: 12mm 12mm 15mm 12mm;
                /* Progressive enhancement: numeración de página donde el motor lo soporte
                   (Firefox / algunos PDF). Chromium (Guardar como PDF) NO pinta cajas @page,
                   por eso el "Página X de Y" también intenta ir por aquí y, si no, degrada al
                   pie corriente sin el conteo. */
                @bottom-right { content: "Página " counter(page) " de " counter(pages); font-size: 8pt; color: #6b7280; }
            }
            html, body { background: #fff !important; }
            .no-print { display: none !important; }
            .sheet {
                width: auto; max-width: none; margin: 0; padding: 0;
                box-shadow: none; background: #fff;
            }
            .doc-foot-screen { display: none; }
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

            /* thead del roster se REIMPRIME en cada hoja → los encabezados de columna repiten. */
            table.roster thead { display: table-header-group; }
            tr.band { break-inside: avoid; break-after: avoid-page; }   /* banda nunca queda huérfana */
            tr.person { break-inside: avoid; }

            /* Cabecera corriente (arriba-dcha, en el margen superior de CADA hoja). */
            .print-run-head {
                display: block; position: fixed; top: 5mm; right: 12mm;
                font-size: 8pt; color: #6b7280; text-align: right;
            }
            /* Pie corriente (en el margen inferior de CADA hoja): "Emisión: fecha". */
            .print-run-foot {
                display: block; position: fixed; bottom: 5mm; left: 12mm; right: 12mm;
                font-size: 8pt; color: #6b7280;
                display: flex; justify-content: space-between;
                border-top: .5px solid #d1d5db; padding-top: 2mm;
            }
            /* Marca de agua diagonal (si se eligió propósito): repite por hoja (fixed). */
            .watermark {
                display: flex; position: fixed; inset: 0; align-items: center; justify-content: center;
                pointer-events: none; z-index: 0;
            }
            .watermark span {
                transform: rotate(-32deg);
                font-size: 46pt; font-weight: 800; letter-spacing: .06em;
                color: rgba(17,24,39,.07); white-space: nowrap; text-transform: uppercase;
            }
        }
    </style>
</head>
<body>

    {{-- ── Barra de acciones (no se imprime) ── --}}
    <div class="toolbar no-print">
        <button type="button" class="tb-btn tb-btn--primary" id="cc-print-btn">Imprimir / Guardar PDF</button>
        <a href="{{ route('usuarioscrud') }}" class="tb-btn tb-btn--ghost">Volver al Crew List</a>
        <span class="spacer"></span>
        @if($purpose !== '')
            <span class="tb-note">Marca de agua: <b>{{ $purpose }}</b></span>
        @endif
        @if(!empty($roster['unordered']))
            <span class="tb-note">
                Departamentos <b>fuera del orden canónico</b> (van al final del documento):
                @foreach($roster['unordered'] as $i => $ud){{ $i ? ' · ' : '' }}{{ $ud['label'] }} ({{ $ud['count'] }})@endforeach.
                Son etiquetas sin equivalente en el catálogo; no se acomodan a mano.
            </span>
        @endif
        <span class="tb-note">
            Documento de <b>una sola unidad</b>: la app aún no modela unidades (1ª/2ª), así que no se
            inventa un desglose por unidad. El teléfono va sin tipo (Móvil/Casa): el esquema guarda
            un solo teléfono sin clasificar.
        </span>
    </div>

    {{-- ── Cabecera / pie / marca corrientes (solo impresión, repiten por hoja) ── --}}
    <div class="print-run-head">{{ $prodName }} · Crew List</div>
    <div class="print-run-foot">
        <span>Emisión: {{ $issued }}</span>
        <span>{{ $prodName }} · Crew List</span>
    </div>
    @if($purpose !== '')
        <div class="watermark" aria-hidden="true"><span>{{ $purpose }}</span></div>
    @endif

    {{-- ── Documento ── --}}
    <div class="sheet">
        <div class="doc-head">
            <img src="{{ $logo }}" alt="{{ $prodName }}" class="doc-head__logo">
            <div class="doc-head__mid">
                <h1 class="doc-head__title">{{ $prodName }} <span>| Crew List</span></h1>
            </div>
            <div class="doc-head__date">{{ $issued }}</div>
        </div>

        @if(empty($roster['groups']))
            <div class="roster-empty">No hay crew activo que mostrar.</div>
        @else
            <table class="roster">
                <colgroup>
                    <col class="c-cargo"><col class="c-name"><col class="c-email"><col class="c-phone">
                </colgroup>
                <thead>
                    <tr>
                        <th>Cargo</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Números de teléfono</th>
                    </tr>
                </thead>
                @foreach($roster['groups'] as $g)
                    <tbody class="dept">
                        <tr class="band"><td colspan="4">{{ $g['label'] }}</td></tr>
                        @foreach($g['people'] as $p)
                            <tr class="person">
                                <td class="c-cargo">{{ $p['cargo'] }}</td>
                                <td class="c-name">{{ $p['name'] }}</td>
                                {{-- LO VACÍO SE QUEDA VACÍO: sin guiones ni "N/D". --}}
                                <td class="c-email">{{ $p['email'] }}</td>
                                <td class="c-phone">@if($p['phone'] !== '')<strong>Tel.</strong> {{ $p['phone'] }}@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        @endif

        <div class="doc-foot-screen">
            <span>Emisión: {{ $issued }}</span>
            <span>{{ $roster['total'] }} integrantes</span>
        </div>
    </div>

    {{-- CSP: sin onclick inline. El nonce llega por la FUENTE ÚNICA (View::share del middleware),
         no por layouts.app — este documento es HTML completo autónomo. --}}
    <script nonce="{{ $cspNonce }}">
        document.addEventListener('DOMContentLoaded', function () {
            var b = document.getElementById('cc-print-btn');
            if (b) { b.addEventListener('click', function () { window.print(); }); }
        });
    </script>

</body>
</html>
