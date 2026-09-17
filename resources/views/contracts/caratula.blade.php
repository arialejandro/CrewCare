{{-- CARÁTULA DEL CONTRATO — plantilla NEUTRA para dompdf. ⚠ CERO MARCA DE CREWCARE: el logo y
     los datos son de la productora. Tabla etiqueta/valor; un campo vacío no llega aquí (se filtra
     en ContractCoverSheet::rows). Idioma es | en | bilingual. --}}
@php
    $lang = $language ?? 'es';
    $bilingual = $lang === 'bilingual';
    $heading = $bilingual ? 'Carátula del contrato · Contract cover sheet'
        : ($lang === 'en' ? 'Contract cover sheet' : 'Carátula del contrato');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; margin: 0; }
    .head { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; }
    .head td { vertical-align: middle; }
    .logo { max-height: 54px; max-width: 200px; }
    .title { font-size: 15px; font-weight: bold; text-align: right; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
    td.lbl { width: 34%; background: #f3f4f6; font-weight: bold; }
    td.lbl .en { display: block; font-weight: normal; color: #6b7280; font-size: 10px; }
    td.val { width: 66%; }
</style>
</head>
<body>
    <table class="head" width="100%"><tr>
        <td width="55%">
            @if(!empty($logo))
                <img class="logo" src="{{ $logo }}" alt="">
            @endif
        </td>
        <td width="45%" class="title">{{ $heading }}</td>
    </tr></table>

    <table class="data">
        @foreach($rows as $r)
            <tr>
                <td class="lbl">
                    @if($bilingual)
                        {{ $r['label_es'] }}<span class="en">{{ $r['label_en'] }}</span>
                    @elseif($lang === 'en')
                        {{ $r['label_en'] }}
                    @else
                        {{ $r['label_es'] }}
                    @endif
                </td>
                <td class="val">{{ $r['value'] }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
