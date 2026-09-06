{{-- PASO 3 · FORMA DEL INTAKE (asistente por pasos, público via link firmado / o contratante
     autenticado). Autocontenida y MÓVIL-FIRST: una sección por pantalla, objetivos táctiles
     grandes, pie fijo con Atrás/Siguiente que el teclado no tapa. La voz dice QUÉ HACER. --}}
@php
    $labels = ['identity'=>'Identidad','fiscal'=>'Fiscales','documents'=>'Documentos','emergency'=>'Emergencia','equipment'=>'Equipo','logistics'=>'Logística'];
    $idx    = array_search($step, $steps, true);
    $isLast = $idx === count($steps) - 1;
    $u      = $payee->user;   // crew ligado (o usuario externo): fuente para NO re-teclear lo ya capturado
    $who    = trim(($payee->name ?: optional($u)->name) . ' ' . optional($u)->lname) ?: 'esta persona';
    $uName  = $u ? trim($u->name . ' ' . $u->lname) : null;
    // Prellena desde el usuario ligado SOLO lo que existe en User (nombre/teléfono/correo). La
    // persona lo puede editar; es punto de partida, no dato fijo. Dirección y contacto de
    // emergencia NO viven en User → se capturan aquí sin fuente que heredar.
    $val    = fn($f, $d = null) => old($f, $payee->$f ?? $d);
    // Docs ya recibidos, por tipo, para marcar "RECIBIDO" vs "Falta".
    $recibidos = $payee->documents->pluck('document_type_id')->filter()->flip();
    // Marca: esta página es autocontenida (enlace público, sin login) pero hereda el color de
    // marca configurable de la app (mismo token que el resto del sistema), no un amarillo fijo.
    $brand     = \App\Support\Branding::all()['primary_color'] ?? '#ff9900';
    $brandOn   = \App\Support\Branding::textOn($brand);
    $brandDark = \App\Support\Branding::shade($brand, 0.85);
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Registro — CrewCare</title>
<style>
  *{box-sizing:border-box}
  :root{--brand:{{ $brand }};--brand-on:{{ $brandOn }};--brand-dark:{{ $brandDark }}}
  body{margin:0;background:#0b0f16;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    -webkit-text-size-adjust:100%}
  .wrap{max-width:560px;margin:0 auto;min-height:100vh;min-height:100dvh;display:flex;flex-direction:column}
  header{padding:16px 18px 10px;position:sticky;top:0;background:#0b0f16;z-index:2}
  .brand{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:var(--brand);font-weight:800}
  .who{font-size:.86rem;color:#9aa5b5;margin-top:2px}
  .who b{color:#fff}
  .to-profile{display:inline-block;margin-top:8px;font-size:.8rem;font-weight:700;color:#93c5fd;text-decoration:none}
  .to-profile:hover{text-decoration:underline}
  .subhead{font-size:.92rem;font-weight:800;color:#fff;margin:16px 0 2px}
  .steps{display:flex;gap:6px;margin-top:12px}
  .steps .dot{flex:1;height:6px;border-radius:99px;background:#1f2937}
  .steps .dot.done{background:#16a34a}
  .steps .dot.now{background:var(--brand)}
  .stepname{font-size:1.15rem;font-weight:800;color:#fff;margin:14px 0 2px}
  .stepno{font-size:.72rem;color:#9aa5b5;text-transform:uppercase;letter-spacing:.1em}
  main{flex:1;padding:4px 18px 120px;overflow-y:auto}
  .banner{margin:10px 0;padding:11px 14px;border-radius:12px;font-size:.85rem;
    background:rgba(220,38,38,.14);border:1px solid rgba(220,38,38,.4);color:#fecaca}
  label{display:block;font-size:.78rem;color:#c7ccd6;margin:14px 0 6px;font-weight:600}
  input,select{width:100%;height:52px;padding:0 14px;font-size:16px;color:#fff;background:#111827;
    border:1px solid #2b3648;border-radius:12px;outline:none}
  input:focus,select:focus{border-color:var(--brand)}
  .cc-ta{position:relative}
  .cc-ta-list{position:absolute;z-index:20;left:0;right:0;top:calc(100% + 4px);margin:0;padding:4px 0;list-style:none;
    background:#111827;border:1px solid #2b3648;border-radius:12px;box-shadow:0 14px 34px -12px rgba(0,0,0,.7);
    max-height:260px;overflow-y:auto;display:none}
  .cc-ta.open .cc-ta-list{display:block}
  .cc-ta-opt{padding:13px 14px;font-size:.95rem;color:#e5e7eb;cursor:pointer}
  .cc-ta-opt:hover,.cc-ta-opt.active{background:color-mix(in srgb, var(--brand) 18%, #111827);color:#fff}
  .cc-ta-empty{padding:13px 14px;font-size:.85rem;color:#9aa5b5}
  input[type=checkbox]{width:24px;height:24px;vertical-align:-5px}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
  .hint{font-size:.76rem;color:#9aa5b5;margin-top:6px;line-height:1.4}
  .card{background:#111827;border:1px solid #232c3b;border-radius:14px;padding:12px;margin:10px 0}
  .repeat-row{border-bottom:1px dashed #2b3648;padding-bottom:10px;margin-bottom:10px}
  .repeat-row:last-child{border-bottom:0;margin-bottom:0;padding-bottom:0}
  .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:44px;padding:0 14px;border-radius:11px;
    background:transparent;border:1px dashed #3a475c;color:#cbd5e1;font-weight:700;font-size:.85rem;cursor:pointer;margin-top:8px}
  .doc{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .chip{flex:none;font-size:.7rem;font-weight:800;padding:4px 10px;border-radius:99px}
  .chip.ok{background:rgba(34,197,94,.16);color:#4ade80;border:1px solid rgba(34,197,94,.4)}
  .chip.miss{background:rgba(245,179,1,.14);color:#f5b301;border:1px solid rgba(245,179,1,.4)}
  .sum{font-weight:800}
  .toggle{display:flex;align-items:center;gap:12px;margin:14px 0}
  .toggle label{margin:0}
  footer{position:fixed;bottom:0;left:0;right:0;background:#0b0f16;border-top:1px solid #1c2635;
    padding:12px 18px calc(12px + env(safe-area-inset-bottom));display:flex;gap:10px;max-width:560px;margin:0 auto}
  .btn{flex:1;height:54px;border:0;border-radius:13px;font-size:1rem;font-weight:800;cursor:pointer}
  .btn.next{background:var(--brand);color:var(--brand-on)}
  .btn:disabled{opacity:.5;cursor:not-allowed}
  .btn.back{flex:0 0 34%;background:#1f2937;color:#cbd5e1}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="brand">CrewCare · Registro</div>
    <div class="who">Estás llenando los datos de <b>{{ $who }}</b>. Si no eres tú, avisa a la producción.</div>
    @if(auth()->check() && $isSelf)
      <a class="to-profile" href="{{ route('perfil') }}">← Volver a mi perfil</a>
    @endif
    <div class="steps">
      @foreach($steps as $s)
        <span class="dot {{ $s === $step ? 'now' : ($completed[$s] ? 'done' : '') }}"></span>
      @endforeach
    </div>
    <div class="stepno">Paso {{ $idx + 1 }} de {{ count($steps) }}</div>
    <div class="stepname">{{ $labels[$step] }}</div>
  </header>

  @if(session('error'))<div style="padding:0 18px"><div class="banner">{{ session('error') }}</div></div>@endif

  <form method="POST" action="{{ $postUrl }}" enctype="multipart/form-data" data-cc-drafts="intake-{{ $step }}" id="intakeForm">
    @csrf
    <input type="hidden" name="_step" value="{{ $step }}">
    <main>
      @switch($step)

        @case('identity')
          <label>Nombre completo</label>
          <input name="name" value="{{ $val('name', $uName) }}" autocomplete="name">
          <label>Nacionalidad</label>
          <select name="nationality">
            <option value="mexicana" @selected($val('nationality')==='mexicana')>Mexicana</option>
            <option value="extranjera" @selected($val('nationality')==='extranjera')>Extranjera</option>
          </select>
          <label>Número de credencial de elector</label>
          <input name="elector_credential" value="{{ $val('elector_credential') }}">
          <label>Estado civil</label>
          @php $mc = $val('marital_status'); @endphp
          <select name="marital_status" class="js-typeahead">
            <option value="">— Selecciona —</option>
            @foreach(\App\Support\SatCatalogs::ESTADOS_CIVILES as $opt)
              <option value="{{ $opt }}" @selected($mc === $opt)>{{ $opt }}</option>
            @endforeach
            @if($mc && ! in_array($mc, \App\Support\SatCatalogs::ESTADOS_CIVILES, true))
              <option value="{{ $mc }}" selected>{{ $mc }}</option>
            @endif
          </select>
          <div class="row2">
            <div><label>Teléfono</label><input name="phone" value="{{ $val('phone', optional($u)->phone) }}" inputmode="tel" autocomplete="tel"></div>
            <div><label>Correo electrónico</label><input name="email" type="email" value="{{ $val('email', optional($u)->email) }}" autocomplete="email"></div>
          </div>
          @if($payee->isMoral())
            <label>Representante legal</label>
            <input name="legal_representative" value="{{ $val('legal_representative') }}">
          @endif
          <label>Calle</label>
          <input name="addr_street" value="{{ $val('addr_street') }}" autocomplete="address-line1">
          <div class="row3">
            <div><label>No. ext.</label><input name="addr_ext_no" value="{{ $val('addr_ext_no') }}"></div>
            <div><label>No. int.</label><input name="addr_int_no" value="{{ $val('addr_int_no') }}"></div>
            <div><label>C.P.</label><input name="addr_cp" value="{{ $val('addr_cp') }}" inputmode="numeric"></div>
          </div>
          <label>Colonia</label>
          <input name="addr_colonia" value="{{ $val('addr_colonia') }}">
          <label>Alcaldía o municipio</label>
          <input name="addr_municipio" value="{{ $val('addr_municipio') }}">
          <div class="row2">
            <div><label>Ciudad</label><input name="addr_city" value="{{ $val('addr_city') }}"></div>
            <div><label>Estado</label><input name="addr_state" value="{{ $val('addr_state') }}"></div>
          </div>
          @break

        @case('fiscal')
          <label>RFC</label>
          <input name="rfc" value="{{ $val('rfc') }}" style="text-transform:uppercase">
          <label>Régimen(es) fiscal(es) — como aparece en tu CSF</label>
          <div class="card" data-repeat="regimes">
            @php $rrows = $payee->fiscalRegimes->count() ? $payee->fiscalRegimes : collect([null]); @endphp
            @foreach($rrows as $r)
              <div class="repeat-row">
                <select name="regimes[{{ $loop->index }}][code]" class="js-typeahead">
                  <option value="">— Selecciona tu régimen —</option>
                  @foreach($regimenOptions as $code => $rname)
                    <option value="{{ $code }}" @selected((string) optional($r)->code === (string) $code)>{{ $code }} — {{ $rname }}</option>
                  @endforeach
                  @if(optional($r)->code && ! array_key_exists($r->code, $regimenOptions))
                    <option value="{{ $r->code }}" selected>{{ $r->code }}{{ $r->name ? ' — '.$r->name : '' }}</option>
                  @endif
                </select>
              </div>
            @endforeach
          </div>
          <button type="button" class="btn-ghost" data-add="regimes">+ Agregar régimen</button>
          <label>País de residencia fiscal</label>
          <input name="tax_residence_country" value="{{ $val('tax_residence_country','México') }}">
          <label>Banco</label>
          <input name="bank_name" value="{{ $val('bank_name') }}">
          <div class="row2">
            <div><label>Sucursal de apertura</label><input name="bank_branch" value="{{ $val('bank_branch') }}"></div>
            <div><label>Cuenta</label><input name="bank_account" value="{{ $val('bank_account') }}" inputmode="numeric"></div>
          </div>
          <label>CLABE</label>
          <input name="bank_clabe" value="{{ $val('bank_clabe') }}" inputmode="numeric" maxlength="18">
          @break

        @case('documents')
          <div class="hint">Sube cada documento en PDF. Verás cuáles faltan.</div>
          @forelse($requiredDocs as $d)
            <div class="card">
              <div class="doc">
                <div><strong>{{ $d->name }}</strong></div>
                @if($recibidos->has($d->id))<span class="chip ok">RECIBIDO</span>@else<span class="chip miss">Falta</span>@endif
              </div>
              @unless($recibidos->has($d->id))
                <label>Archivo PDF</label>
                <input type="file" name="documents[{{ $d->id }}]" accept="application/pdf">
                @if($d->expects_cfdi_xml)
                  <label>Archivo XML del CFDI</label>
                  <input type="file" name="documents_xml[{{ $d->id }}]" accept="text/xml,application/xml,.xml">
                  <div class="hint">La factura es PDF y XML: el PDF es la representación impresa, el XML es la factura. Del XML se arma el enlace de verificación del SAT.</div>
                @endif
                @if($d->code === 'OPINION_32D')
                  <label>Folio de la 32-D (opcional)</label>
                  <input type="text" name="sat_folio[{{ $d->id }}]" maxlength="60" placeholder="Se extrae solo del PDF">
                  <div class="hint">El folio se lee solo de la Cadena Original del PDF; déjalo vacío salvo que quieras forzar uno.</div>
                @endif
              @endunless
            </div>
          @empty
            <div class="hint">No hay documentos configurados para tu naturaleza jurídica.</div>
          @endforelse
          @break

        @case('emergency')
          <div class="subhead">Contacto de emergencia</div>
          <label>Nombre</label>
          <input name="emergency_contact_name" value="{{ $val('emergency_contact_name') }}" autocomplete="name">
          <div class="row2">
            <div><label>Teléfono</label><input name="emergency_contact_phone" value="{{ $val('emergency_contact_phone') }}" inputmode="tel"></div>
            <div>
              <label>Parentesco</label>
              @php $ecr = $val('emergency_contact_relationship'); @endphp
              <select name="emergency_contact_relationship" class="js-typeahead">
                <option value="">— Parentesco —</option>
                @foreach(\App\Support\SatCatalogs::PARENTESCOS as $p)
                  <option value="{{ $p }}" @selected($ecr === $p)>{{ $p }}</option>
                @endforeach
                @if($ecr && ! in_array($ecr, \App\Support\SatCatalogs::PARENTESCOS, true))
                  <option value="{{ $ecr }}" selected>{{ $ecr }}</option>
                @endif
              </select>
            </div>
          </div>

          <div class="subhead">Beneficiarios <span style="font-weight:600;color:#9aa5b5;font-size:.8rem">(deben sumar 100%)</span></div>
          <div class="card" data-repeat="beneficiaries">
            @php $brows = $payee->beneficiaries->count() ? $payee->beneficiaries : collect([null]); @endphp
            @foreach($brows as $b)
              <div class="repeat-row">
                <input name="beneficiaries[{{ $loop->index }}][full_name]" placeholder="Nombre completo (como en su identificación)" value="{{ optional($b)->full_name }}">
                <div class="row2" style="margin-top:8px">
                  <div>
                    @php $brel = optional($b)->relationship; @endphp
                    <select name="beneficiaries[{{ $loop->index }}][relationship]" class="js-typeahead">
                      <option value="">— Parentesco —</option>
                      @foreach(\App\Support\SatCatalogs::PARENTESCOS as $p)
                        <option value="{{ $p }}" @selected($brel === $p)>{{ $p }}</option>
                      @endforeach
                      @if($brel && ! in_array($brel, \App\Support\SatCatalogs::PARENTESCOS, true))
                        <option value="{{ $brel }}" selected>{{ $brel }}</option>
                      @endif
                    </select>
                  </div>
                  <div><input name="beneficiaries[{{ $loop->index }}][percentage]" class="pct" type="number" min="0" max="100" step="1" placeholder="%" value="{{ optional($b)->percentage ? (int) $b->percentage : '' }}"></div>
                </div>
              </div>
            @endforeach
          </div>
          <button type="button" class="btn-ghost" data-add="beneficiaries">+ Agregar beneficiario</button>
          <div class="hint">Suma actual: <span class="sum" id="pctSum">0</span>%. Debe ser 100% para continuar.</div>
          @break

        @case('equipment')
          <div class="hint">Declara solo el equipo con factura a tu nombre y valor mayor a
            <strong>${{ number_format($threshold, 0) }} MXN</strong>. Lo que NO declares aquí, el seguro NO lo cubre.
            Esto es distinto del equipo que RENTAS a la producción (eso va por contrato).</div>
          <div class="card" data-repeat="declared_equipment">
            @foreach(($payee->declaredEquipment->count() ? $payee->declaredEquipment : [null]) as $e)
              <div class="repeat-row">
                <input name="declared_equipment[{{ $loop->index }}][description]" placeholder="Equipo (ej. Starlink)" value="{{ optional($e)->description }}">
                <div class="row2" style="margin-top:8px">
                  <div><input name="declared_equipment[{{ $loop->index }}][invoice_holder]" placeholder="Factura a nombre de" value="{{ optional($e)->invoice_holder }}"></div>
                  <div><input name="declared_equipment[{{ $loop->index }}][declared_value]" type="number" min="0" step="1" placeholder="Valor MXN" value="{{ optional($e)->declared_value ? (int) $e->declared_value : '' }}"></div>
                </div>
              </div>
            @endforeach
          </div>
          <button type="button" class="btn-ghost" data-add="declared_equipment">+ Agregar equipo</button>
          <div class="toggle"><input type="checkbox" id="acc" name="accept_equipment" value="1" @checked(old('accept_equipment'))><label for="acc">Acepto que lo no declarado no queda cubierto.</label></div>
          <div class="hint" id="eqHint">Para continuar: agrega tu equipo o marca la casilla de arriba.</div>
          @break

        @case('logistics')
          <label>Talla de playera</label>
          <select name="shirt_size">
            @foreach(['','XS','S','M','L','XL','XXL'] as $t)
              <option value="{{ $t }}" @selected($val('shirt_size')===$t)>{{ $t ?: '— Selecciona —' }}</option>
            @endforeach
          </select>
          <div class="toggle"><input type="checkbox" id="veg" name="is_vegetarian" value="1" @checked($val('is_vegetarian'))><label for="veg">Soy vegetariano/a</label></div>
          <div class="toggle"><input type="checkbox" id="don" name="is_donor" value="1" @checked($val('is_donor'))><label for="don">Soy donador/a</label></div>
          @break
      @endswitch
    </main>

    <footer>
      @if($idx > 0)
        <a class="btn back" href="{{ $navUrl($steps[$idx - 1]) }}" style="display:grid;place-items:center;text-decoration:none">Atrás</a>
      @endif
      <button type="submit" class="btn next">{{ $isLast ? 'Terminar' : 'Guardar y seguir' }}</button>
    </footer>
  </form>
</div>

<script src="{{ asset('js/cc-drafts.js') }}"></script>
<script>
// Typeahead AUTOCONTENIDO (escribe-y-filtra) para los <select class="js-typeahead">. Mismo
// contrato que el resto de la app: el <select> nativo sigue siendo el control REAL (su name
// se envía) y el fallback sin JS; el input solo filtra. Sin acentos, teclado ↑↓ Enter Esc.
window.__intakeTA = (function () {
  function norm(s) { return (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); }
  function build(sel) {
    if (!sel || sel.dataset.ta === '1') { return; }
    sel.dataset.ta = '1';
    var opts = [];
    Array.prototype.forEach.call(sel.options, function (o) { if (o.value !== '') { opts.push({ value: o.value, label: o.textContent.trim() }); } });
    var ph = sel.querySelector('option[value=""]');
    var placeholder = ph ? ph.textContent.trim() : 'Buscar…';
    sel.style.display = 'none'; sel.setAttribute('aria-hidden', 'true'); sel.tabIndex = -1;
    var wrap = document.createElement('div'); wrap.className = 'cc-ta';
    var input = document.createElement('input'); input.type = 'text'; input.autocomplete = 'off';
    input.setAttribute('role', 'combobox'); input.placeholder = placeholder;
    var list = document.createElement('ul'); list.className = 'cc-ta-list'; list.setAttribute('role', 'listbox');
    wrap.appendChild(input); wrap.appendChild(list);
    sel.parentNode.insertBefore(wrap, sel);
    var visible = [], activeIdx = -1;
    function setVal(v, l) { sel.value = v; input.value = l || ''; sel.dispatchEvent(new Event('change', { bubbles: true })); }
    function render(q) {
      list.innerHTML = ''; visible = []; activeIdx = -1; var nq = norm(q), any = false;
      opts.forEach(function (it) {
        if (nq !== '' && norm(it.label).indexOf(nq) === -1) { return; }
        any = true;
        var li = document.createElement('li'); li.className = 'cc-ta-opt'; li.setAttribute('role', 'option');
        li.dataset.value = it.value; li.textContent = it.label;
        li.addEventListener('mousedown', function (e) { e.preventDefault(); setVal(it.value, it.label); close(); });
        list.appendChild(li); visible.push(li);
      });
      if (!any) { var em = document.createElement('li'); em.className = 'cc-ta-empty'; em.textContent = 'Sin coincidencias'; list.appendChild(em); }
    }
    function open() { render(''); wrap.classList.add('open'); }
    function close() { wrap.classList.remove('open'); activeIdx = -1; }
    function hi(i) { visible.forEach(function (li) { li.classList.remove('active'); }); if (i >= 0 && i < visible.length) { visible[i].classList.add('active'); visible[i].scrollIntoView({ block: 'nearest' }); } }
    input.addEventListener('focus', open);
    input.addEventListener('input', function () { render(input.value); wrap.classList.add('open'); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (!wrap.classList.contains('open')) { open(); } activeIdx = Math.min(activeIdx + 1, visible.length - 1); hi(activeIdx); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); hi(activeIdx); }
      else if (e.key === 'Enter') { if (wrap.classList.contains('open') && activeIdx >= 0) { e.preventDefault(); var li = visible[activeIdx]; setVal(li.dataset.value, li.textContent); close(); } }
      else if (e.key === 'Escape') { close(); }
    });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) { close(); } });
    var so = sel.options[sel.selectedIndex];
    if (so && so.value !== '') { input.value = so.textContent.trim(); }
  }
  return { enhance: function (root) { (root || document).querySelectorAll('select.js-typeahead').forEach(build); } };
})();
document.addEventListener('DOMContentLoaded', function () { window.__intakeTA.enhance(document); });
</script>
<script>
(function () {
  var form = document.getElementById('intakeForm');
  // Renumera las filas de un repetidor: cada fila recibe un índice único y sus campos lo
  // comparten → prefix[N][campo]. (Con "[]" PHP parte cada campo en una fila distinta, así
  // que el nombre + parentesco + % de un mismo beneficiario quedarían separados.)
  function renumber(box) {
    var prefix = box.dataset.repeat;
    box.querySelectorAll('.repeat-row').forEach(function (row, idx) {
      row.querySelectorAll('input,select,textarea').forEach(function (el) {
        if (el.name) el.name = el.name.replace(/^[^\[]+\[\d*\]/, prefix + '[' + idx + ']');
      });
    });
  }
  // Plantilla PRISTINA de cada repetidor, capturada AHORA (síncrono, antes de que el typeahead
  // mejore los selects en DOMContentLoaded). Al agregar clonamos de aquí para no arrastrar el
  // typeahead ya montado y para que la fila nueva se mejore limpia.
  document.querySelectorAll('[data-repeat]').forEach(function (box) {
    var first = box.querySelector('.repeat-row');
    if (first) { box._tpl = first.cloneNode(true); }
  });
  // Repetidores: clona la plantilla pristina, renumera y mejora los selects de la fila nueva.
  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = document.querySelector('[data-repeat="' + btn.dataset.add + '"]');
      var clone = (box._tpl || box.querySelector('.repeat-row')).cloneNode(true);
      clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
      box.appendChild(clone); renumber(box);
      if (window.__intakeTA) { window.__intakeTA.enhance(clone); }
      recompute();
    });
  });
  // Suma de porcentajes en vivo (beneficiarios).
  var sumEl = document.getElementById('pctSum');
  function recompute() {
    if (!sumEl) return;
    var t = 0;
    document.querySelectorAll('.pct').forEach(function (i) { t += parseFloat(i.value || 0) || 0; });
    sumEl.textContent = t;
    sumEl.style.color = (t === 100) ? '#4ade80' : '#f5b301';
  }
  form.addEventListener('input', function (e) { if (e.target.classList.contains('pct')) recompute(); });
  recompute();

  // GATE del paso EQUIPO: el botón se bloquea hasta que haya equipo (una fila con descripción)
  // o se marque "Acepto que lo no declarado no queda cubierto". El servidor lo revalida.
  var accChk = form.querySelector('input[name="accept_equipment"]');
  if (accChk) {
    var nextBtn = form.querySelector('button.next');
    var eqHint  = document.getElementById('eqHint');
    function eqGate() {
      var hasRow = false;
      form.querySelectorAll('[data-repeat="declared_equipment"] [name$="[description]"]').forEach(function (i) {
        if (i.value.trim() !== '') { hasRow = true; }
      });
      var ok = hasRow || accChk.checked;
      if (nextBtn) { nextBtn.disabled = ! ok; }
      if (eqHint)  { eqHint.style.display = ok ? 'none' : 'block'; }
    }
    form.addEventListener('input', eqGate);
    accChk.addEventListener('change', eqGate);
    eqGate();
  }

  // cc-drafts como RED contra lo tecleado antes de guardar (el avance real vive en el servidor).
  if (window.CCDrafts && CCDrafts.available) { try { CCDrafts.attach(form, { formType: 'intake-{{ $step }}' }); } catch (e) {} }
})();
</script>
</body>
</html>
