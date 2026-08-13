{{-- PASO 3 · FORMA DEL INTAKE (asistente por pasos, público via link firmado / o contratante
     autenticado). Autocontenida y MÓVIL-FIRST: una sección por pantalla, objetivos táctiles
     grandes, pie fijo con Atrás/Siguiente que el teclado no tapa. La voz dice QUÉ HACER. --}}
@php
    $labels = ['identity'=>'Identidad','fiscal'=>'Fiscales','documents'=>'Documentos','emergency'=>'Emergencia','equipment'=>'Equipo','logistics'=>'Logística'];
    $idx    = array_search($step, $steps, true);
    $isLast = $idx === count($steps) - 1;
    $who    = trim(($payee->name ?: optional($payee->user)->name) . ' ' . optional($payee->user)->lname) ?: 'esta persona';
    $val    = fn($f, $d = null) => old($f, $payee->$f ?? $d);
    // Docs ya recibidos, por tipo, para marcar "RECIBIDO" vs "Falta".
    $recibidos = $payee->documents->pluck('document_type_id')->filter()->flip();
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Registro — CrewCare</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:#0b0f16;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    -webkit-text-size-adjust:100%}
  .wrap{max-width:560px;margin:0 auto;min-height:100vh;min-height:100dvh;display:flex;flex-direction:column}
  header{padding:16px 18px 10px;position:sticky;top:0;background:#0b0f16;z-index:2}
  .brand{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:#f5b301;font-weight:800}
  .who{font-size:.86rem;color:#9aa5b5;margin-top:2px}
  .who b{color:#fff}
  .steps{display:flex;gap:6px;margin-top:12px}
  .steps .dot{flex:1;height:6px;border-radius:99px;background:#1f2937}
  .steps .dot.done{background:#16a34a}
  .steps .dot.now{background:#f5b301}
  .stepname{font-size:1.15rem;font-weight:800;color:#fff;margin:14px 0 2px}
  .stepno{font-size:.72rem;color:#9aa5b5;text-transform:uppercase;letter-spacing:.1em}
  main{flex:1;padding:4px 18px 120px;overflow-y:auto}
  .banner{margin:10px 0;padding:11px 14px;border-radius:12px;font-size:.85rem;
    background:rgba(220,38,38,.14);border:1px solid rgba(220,38,38,.4);color:#fecaca}
  label{display:block;font-size:.78rem;color:#c7ccd6;margin:14px 0 6px;font-weight:600}
  input,select{width:100%;height:52px;padding:0 14px;font-size:16px;color:#fff;background:#111827;
    border:1px solid #2b3648;border-radius:12px;outline:none}
  input:focus,select:focus{border-color:#f5b301}
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
  .btn.next{background:#f5b301;color:#111}
  .btn.back{flex:0 0 34%;background:#1f2937;color:#cbd5e1}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="brand">CrewCare · Registro</div>
    <div class="who">Estás llenando los datos de <b>{{ $who }}</b>. Si no eres tú, avisa a la producción.</div>
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
          <input name="name" value="{{ $val('name') }}" autocomplete="name">
          <label>Nacionalidad</label>
          <select name="nationality">
            <option value="mexicana" @selected($val('nationality')==='mexicana')>Mexicana</option>
            <option value="extranjera" @selected($val('nationality')==='extranjera')>Extranjera</option>
          </select>
          <label>Número de credencial de elector</label>
          <input name="elector_credential" value="{{ $val('elector_credential') }}">
          <label>Estado civil</label>
          <input name="marital_status" value="{{ $val('marital_status') }}">
          <label>Calle</label>
          <input name="addr_street" value="{{ $val('addr_street') }}" autocomplete="address-line1">
          <div class="row2">
            <div><label>No. exterior</label><input name="addr_ext_no" value="{{ $val('addr_ext_no') }}"></div>
            <div><label>No. interior</label><input name="addr_int_no" value="{{ $val('addr_int_no') }}"></div>
          </div>
          <label>Colonia</label>
          <input name="addr_colonia" value="{{ $val('addr_colonia') }}">
          <label>Alcaldía o municipio</label>
          <input name="addr_municipio" value="{{ $val('addr_municipio') }}">
          <div class="row3">
            <div><label>C.P.</label><input name="addr_cp" value="{{ $val('addr_cp') }}" inputmode="numeric"></div>
            <div><label>Ciudad</label><input name="addr_city" value="{{ $val('addr_city') }}"></div>
            <div><label>Estado</label><input name="addr_state" value="{{ $val('addr_state') }}"></div>
          </div>
          @break

        @case('fiscal')
          <label>RFC</label>
          <input name="rfc" value="{{ $val('rfc') }}" style="text-transform:uppercase">
          <label>Régimen(es) fiscal(es) — como aparecen en tu CSF</label>
          <div class="card" data-repeat="regimes">
            @foreach(($payee->fiscalRegimes->count() ? $payee->fiscalRegimes : [null]) as $r)
              <div class="repeat-row">
                <div class="row2">
                  <div><input name="regimes[][code]" placeholder="Clave (605...)" value="{{ optional($r)->code }}"></div>
                  <div><input name="regimes[][name]" placeholder="Nombre del régimen" value="{{ optional($r)->name }}"></div>
                </div>
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
              @endunless
            </div>
          @empty
            <div class="hint">No hay documentos configurados para tu naturaleza jurídica.</div>
          @endforelse
          @break

        @case('emergency')
          <label>Contacto de emergencia — nombre</label>
          <input name="emergency_contact_name" value="{{ $val('emergency_contact_name') }}">
          <label>Contacto de emergencia — teléfono</label>
          <input name="emergency_contact_phone" value="{{ $val('emergency_contact_phone') }}" inputmode="tel">
          <label>Beneficiarios (los porcentajes deben sumar 100%)</label>
          <div class="card" data-repeat="beneficiaries">
            @foreach(($payee->beneficiaries->count() ? $payee->beneficiaries : [null]) as $b)
              <div class="repeat-row">
                <input name="beneficiaries[][full_name]" placeholder="Nombre completo (como en su identificación)" value="{{ optional($b)->full_name }}">
                <div class="row2" style="margin-top:8px">
                  <div><input name="beneficiaries[][relationship]" placeholder="Parentesco" value="{{ optional($b)->relationship }}"></div>
                  <div><input name="beneficiaries[][percentage]" class="pct" type="number" min="0" max="100" step="1" placeholder="%" value="{{ optional($b)->percentage ? (int) $b->percentage : '' }}"></div>
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
                <input name="declared_equipment[][description]" placeholder="Equipo (ej. Starlink)" value="{{ optional($e)->description }}">
                <div class="row2" style="margin-top:8px">
                  <div><input name="declared_equipment[][invoice_holder]" placeholder="Factura a nombre de" value="{{ optional($e)->invoice_holder }}"></div>
                  <div><input name="declared_equipment[][declared_value]" type="number" min="0" step="1" placeholder="Valor MXN" value="{{ optional($e)->declared_value ? (int) $e->declared_value : '' }}"></div>
                </div>
              </div>
            @endforeach
          </div>
          <button type="button" class="btn-ghost" data-add="declared_equipment">+ Agregar equipo</button>
          <div class="toggle"><input type="checkbox" id="acc" name="accept_equipment" value="1"><label for="acc">Acepto que lo no declarado no queda cubierto.</label></div>
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
(function () {
  var form = document.getElementById('intakeForm');
  // Repetidores: clona la última fila del bloque.
  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = document.querySelector('[data-repeat="' + btn.dataset.add + '"]');
      var rows = box.querySelectorAll('.repeat-row');
      var clone = rows[rows.length - 1].cloneNode(true);
      clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      box.appendChild(clone); recompute();
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
  // cc-drafts como RED contra lo tecleado antes de guardar (el avance real vive en el servidor).
  if (window.CCDrafts && CCDrafts.available) { try { CCDrafts.attach(form, { formType: 'intake-{{ $step }}' }); } catch (e) {} }
})();
</script>
</body>
</html>
