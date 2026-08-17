{{-- COTIZACIÓN · SE SOLICITA. Página PÚBLICA (enlace firmado, sin login) donde el proveedor/crew
     llena su cotización. Autocontenida y móvil-first; hereda el color de marca de la app. --}}
@php
    $brand   = \App\Support\Branding::all()['primary_color'] ?? '#ff9900';
    $brandOn = \App\Support\Branding::textOn($brand);
    $v = $version ?? null;
    $isPdf = old('source_kind', $v ? $v->source_kind : 'items') === 'pdf';
    $rows = old('items');
    if (! $rows) {
        $rows = ($v && $v->source_kind === 'items' && $v->items->count())
            ? $v->items->map(fn ($i) => ['description' => $i->description, 'detail' => $i->detail,
                'quantity' => rtrim(rtrim((string) $i->quantity, '0'), '.'),
                'days' => $i->days !== null ? rtrim(rtrim((string) $i->days, '0'), '.') : '',
                'unit_price' => rtrim(rtrim((string) $i->unit_price, '0'), '.')])->all()
            : [['description' => '', 'detail' => '', 'quantity' => '1', 'days' => '', 'unit_price' => '']];
    }
@endphp
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Cotización — CrewCare</title>
<style>
  *{box-sizing:border-box}
  :root{--brand:{{ $brand }};--brand-on:{{ $brandOn }}}
  body{margin:0;background:#0b0f16;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  .wrap{max-width:620px;margin:0 auto;padding:18px 16px 120px}
  .brand{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:var(--brand);font-weight:800}
  h1{font-size:1.2rem;margin:6px 0 2px;color:#fff}
  .who{font-size:.85rem;color:#9aa5b5;margin-bottom:14px}
  label{display:block;font-size:.78rem;color:#c7ccd6;margin:12px 0 5px;font-weight:600}
  input,select,textarea{width:100%;min-height:46px;padding:8px 12px;font-size:16px;color:#fff;background:#111827;border:1px solid #2b3648;border-radius:10px;outline:none}
  textarea{min-height:60px}
  input:focus,select:focus,textarea:focus{border-color:var(--brand)}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
  .card{background:#111827;border:1px solid #232c3b;border-radius:14px;padding:12px;margin:12px 0}
  .seg{display:flex;gap:8px;margin:8px 0}
  .seg label{flex:1;margin:0;text-align:center;padding:10px;border:1px solid #2b3648;border-radius:10px;cursor:pointer;color:#cbd5e1}
  .seg input{display:none}
  .seg input:checked + label{background:color-mix(in srgb, var(--brand) 22%, #111827);border-color:var(--brand);color:#fff}
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  th{color:#9aa5b5;text-align:left;font-weight:600;padding:4px}
  td{padding:3px}
  .del{background:transparent;border:1px solid #3a475c;color:#cbd5e1;border-radius:8px;min-height:36px;width:36px;cursor:pointer}
  .add{background:transparent;border:1px dashed #3a475c;color:#cbd5e1;border-radius:10px;min-height:42px;padding:0 14px;font-weight:700;cursor:pointer;margin-top:6px}
  .tot{text-align:right;font-size:.9rem;margin-top:6px}
  .tot b{color:#fff}
  .hint{font-size:.76rem;color:#9aa5b5;margin-top:6px}
  footer{position:fixed;bottom:0;left:0;right:0;background:#0b0f16;border-top:1px solid #1c2635;padding:12px 16px calc(12px + env(safe-area-inset-bottom));max-width:620px;margin:0 auto}
  .btn{width:100%;min-height:52px;border:0;border-radius:12px;font-size:1rem;font-weight:800;background:var(--brand);color:var(--brand-on);cursor:pointer}
  .d-none{display:none}
</style></head>
<body>
<div class="wrap">
  <div class="brand">CrewCare · Cotización</div>
  <h1>Envía tu cotización</h1>
  <div class="who">La producción te pidió tu cotización. Súbela en PDF o captura sus partidas.</div>

  @if($errors->any())<div class="card" style="border-color:#dc2626;color:#fecaca">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

  <form method="POST" action="{{ $postUrl }}" enctype="multipart/form-data" id="qform">
    @csrf
    <label>Nombre del emisor</label>
    <input name="emitter_name" value="{{ old('emitter_name', $quotation->emitter_name) }}" required>
    <label>Correo</label>
    <input name="emitter_email" type="email" value="{{ old('emitter_email', $quotation->emitter_email) }}">

    <div class="seg">
      <input type="radio" name="source_kind" id="s_items" value="items" @checked(! $isPdf)><label for="s_items">Capturar partidas</label>
      <input type="radio" name="source_kind" id="s_pdf" value="pdf" @checked($isPdf)><label for="s_pdf">Subir PDF</label>
    </div>

    <div data-src="pdf" class="{{ $isPdf ? '' : 'd-none' }}">
      <label>Archivo PDF</label>
      <input type="file" name="pdf" accept="application/pdf">
      <div class="row3" style="margin-top:8px">
        <div><label>Subtotal</label><input name="subtotal" type="number" step="0.01" value="{{ old('subtotal') }}"></div>
        <div><label>IVA</label><input name="iva_amount" type="number" step="0.01" value="{{ old('iva_amount') }}"></div>
        <div><label>Total</label><input name="total" type="number" step="0.01" value="{{ old('total') }}"></div>
      </div>
    </div>

    <div data-src="items" class="{{ $isPdf ? 'd-none' : '' }}">
      <div class="card">
        <label style="margin-top:0"><input type="checkbox" name="iva_included" value="1" @checked(old('iva_included', $v ? $v->iva_included : false)) style="width:auto;min-height:auto;vertical-align:-2px"> Los precios ya incluyen IVA</label>
        <label>Tasa IVA %</label>
        <input name="iva_rate" type="number" step="0.01" value="{{ old('iva_rate', $v ? rtrim(rtrim((string) $v->iva_rate,'0'),'.') : '16') }}" style="max-width:120px">
        <table style="margin-top:8px"><thead><tr><th>Descripción</th><th style="width:60px">Cant.</th><th style="width:60px">Días</th><th style="width:90px">P.unit</th><th style="width:36px"></th></tr></thead>
          <tbody data-repeat="items">
            @foreach($rows as $i => $r)
            <tr class="rrow">
              <td><input name="items[{{ $i }}][description]" placeholder="Ej. Renta cámara" value="{{ $r['description'] }}">
                  <input name="items[{{ $i }}][detail]" placeholder="Detalle (opcional)" value="{{ $r['detail'] }}" style="margin-top:4px"></td>
              <td><input name="items[{{ $i }}][quantity]" class="amt" type="number" step="0.01" value="{{ $r['quantity'] }}"></td>
              <td><input name="items[{{ $i }}][days]" class="amt" type="number" step="0.01" placeholder="—" value="{{ $r['days'] }}"></td>
              <td><input name="items[{{ $i }}][unit_price]" class="amt" type="number" step="0.01" value="{{ $r['unit_price'] }}"></td>
              <td><button type="button" class="del">&times;</button></td>
            </tr>
            @endforeach
          </tbody>
        </table>
        <button type="button" class="add" id="addRow">+ Agregar partida</button>
        <div class="tot">Total: <b id="sumTot">0.00</b></div>
      </div>
    </div>

    <div class="row2">
      <div><label>Número</label><input name="quotation_number" value="{{ old('quotation_number', optional($v)->quotation_number) }}"></div>
      <div><label>Fecha</label><input name="issued_at" type="date" value="{{ old('issued_at', optional(optional($v)->issued_at)->format('Y-m-d')) }}"></div>
    </div>
    <label>Vigencia (vence)</label>
    <input name="valid_until" type="date" value="{{ old('valid_until', optional(optional($v)->valid_until)->format('Y-m-d')) }}">
    <label>Condiciones de pago</label>
    <input name="payment_terms" value="{{ old('payment_terms', optional($v)->payment_terms) }}" placeholder="Anticipo del 50%…">
    <label>Datos bancarios</label>
    <input name="bank_details" value="{{ old('bank_details', optional($v)->bank_details) }}">

    <footer><button type="submit" class="btn">Enviar cotización</button></footer>
  </form>
</div>
<script>
(function(){
  var form=document.getElementById('qform');
  form.querySelectorAll('input[name="source_kind"]').forEach(function(r){
    r.addEventListener('change',function(){
      form.querySelector('[data-src="pdf"]').classList.toggle('d-none',this.value!=='pdf');
      form.querySelector('[data-src="items"]').classList.toggle('d-none',this.value!=='items');
    });
  });
  var box=form.querySelector('[data-repeat="items"]');
  function renumber(){box.querySelectorAll('.rrow').forEach(function(row,idx){row.querySelectorAll('input').forEach(function(el){if(el.name)el.name=el.name.replace(/^items\[\d*\]/,'items['+idx+']');});});}
  document.getElementById('addRow').addEventListener('click',function(){
    var rows=box.querySelectorAll('.rrow');var c=rows[rows.length-1].cloneNode(true);
    c.querySelectorAll('input').forEach(function(i){i.value='';});box.appendChild(c);renumber();recompute();
  });
  box.addEventListener('click',function(e){if(e.target.classList.contains('del')){if(box.querySelectorAll('.rrow').length>1)e.target.closest('.rrow').remove();else e.target.closest('.rrow').querySelectorAll('input').forEach(function(i){i.value='';});renumber();recompute();}});
  function recompute(){var s=0;box.querySelectorAll('.rrow').forEach(function(row){var q=parseFloat(row.querySelector('[name$="[quantity]"]').value||0)||0;var d=parseFloat(row.querySelector('[name$="[days]"]').value||0)||0;var p=parseFloat(row.querySelector('[name$="[unit_price]"]').value||0)||0;s+=q*(d>0?d:1)*p;});
    var rate=(parseFloat(form.querySelector('[name="iva_rate"]').value||0)||0)/100;var incl=form.querySelector('[name="iva_included"]').checked;var tot=incl?s:s*(1+rate);
    document.getElementById('sumTot').textContent=(Math.round(tot*100)/100).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2});}
  form.addEventListener('input',function(e){if(e.target.classList.contains('amt')||e.target.name==='iva_rate'||e.target.name==='iva_included')recompute();});
  recompute();
})();
</script>
</body></html>
