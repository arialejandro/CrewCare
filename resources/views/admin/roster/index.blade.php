@extends('layouts.app')
@section('title', 'Roster del día - ' . ($branding['brand_name'] ?? 'CrewCare'))

@php
    use App\Models\PayeeContract;
    // Etiquetas + presentación de los 4 estados. Llamado/Pendiente = "presentes" (al frente);
    // No llamado/Fuera = "alcanzables" (colapsados, para responder "¿y fulano por qué no está?").
    $stateMeta = [
        PayeeContract::ROSTER_CALLED            => ['label' => 'Llamado',             'cls' => 'is-called',  'present' => true],
        PayeeContract::ROSTER_PENDING_SIGNATURE => ['label' => 'Pendiente de firma',  'cls' => 'is-pending', 'present' => true],
        PayeeContract::ROSTER_NOT_CALLED        => ['label' => 'No llamado',           'cls' => 'is-notcall', 'present' => false],
        PayeeContract::ROSTER_OUT               => ['label' => 'Fuera',                'cls' => 'is-out',     'present' => false],
    ];
    $c = $roster['counts'];
@endphp

@push('styles')
<style>
    .roster-wrap{max-width:920px;margin:0 auto}
    .roster-top{display:flex;flex-direction:column;gap:.7rem;margin-bottom:1rem}
    .roster-eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700}
    .roster-h1{font-family:'Poppins',sans-serif;font-weight:800;font-size:clamp(1.3rem,4vw,1.7rem);margin:.1rem 0 0;letter-spacing:-.01em;color:var(--text)}
    .roster-prod{font-size:.85rem;color:var(--text-muted)}

    /* Navegación de día */
    .roster-nav{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
    .roster-nav__btn{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;border:1px solid var(--stroke);background:var(--surface-3,var(--surface-2));color:var(--text);border-radius:10px;text-decoration:none;font-weight:700;font-size:1.1rem;cursor:pointer}
    .roster-nav__btn:active{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}
    .roster-nav__date{flex:1 1 auto;min-width:140px}
    .roster-nav__date input{width:100%;min-height:44px;background:var(--surface-3,var(--surface-2));color:var(--text);border:1px solid var(--stroke);border-radius:10px;padding:.4rem .6rem;font:inherit;font-size:16px}
    .roster-nav__today{min-height:44px;padding:0 .9rem;border:1px solid var(--stroke);background:var(--surface-3,var(--surface-2));color:var(--text);border-radius:10px;text-decoration:none;font-weight:600;font-size:.85rem;display:inline-flex;align-items:center}
    .roster-nav__today.is-today{opacity:.5;pointer-events:none}
    .roster-daychip{display:inline-flex;align-items:center;gap:.45rem;padding:.35rem .7rem;border-radius:999px;background:color-mix(in srgb,var(--brand-primary) 14%,var(--surface-2));border:1px solid color-mix(in srgb,var(--brand-primary) 30%,transparent);color:var(--text);font-weight:700;font-size:.9rem}
    .roster-daychip small{font-weight:600;color:var(--text-muted)}

    /* Notas de calendario (fuera del rodaje / sin ancla) */
    .roster-note{border-left:4px solid var(--warning,#d97706);background:color-mix(in srgb,var(--warning,#d97706) 10%,var(--surface-2));color:var(--text);border-radius:8px;padding:.6rem .8rem;font-size:.86rem;margin:.2rem 0 .4rem}

    /* Barra de totales */
    .roster-totals{display:flex;flex-wrap:wrap;gap:.4rem;margin:.4rem 0 1rem}
    .roster-stat{flex:1 1 auto;min-width:90px;border:1px solid var(--stroke);border-radius:10px;padding:.5rem .6rem;background:var(--surface-2);text-align:center}
    .roster-stat b{display:block;font-size:1.25rem;font-family:'Poppins',sans-serif;line-height:1;font-variant-numeric:tabular-nums}
    .roster-stat span{font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)}
    .roster-stat.is-called b{color:var(--success,#16a34a)}
    .roster-stat.is-pending b{color:var(--warning,#d97706)}

    /* Grupos por departamento */
    .roster-dept{border:1px solid var(--stroke);border-radius:14px;background:var(--glass-2,var(--surface-2));margin-bottom:.9rem;overflow:hidden}
    .roster-dept__head{display:flex;align-items:center;gap:.5rem;justify-content:space-between;padding:.7rem .85rem;background:color-mix(in srgb,var(--brand-primary) 8%,var(--surface-2));border-bottom:1px solid var(--stroke)}
    .roster-dept__name{font-weight:800;font-size:.95rem;color:var(--text);letter-spacing:-.01em}
    .roster-dept__count{font-size:.72rem;color:var(--text-muted);font-variant-numeric:tabular-nums;white-space:nowrap}
    .roster-dept__count b{color:var(--success,#16a34a)}

    .roster-people{list-style:none;margin:0;padding:.35rem}
    .roster-person{display:flex;align-items:center;gap:.6rem;padding:.5rem .5rem;border-radius:10px}
    .roster-person + .roster-person{border-top:1px solid color-mix(in srgb,var(--stroke) 60%,transparent)}
    .roster-person__main{flex:1;min-width:0}
    .roster-person__name{font-weight:600;font-size:.92rem;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .roster-person__cargo{font-size:.76rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .roster-chip{flex:none;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:.22rem .5rem;border-radius:999px;white-space:nowrap}
    .roster-chip.is-called{background:color-mix(in srgb,var(--success,#16a34a) 18%,transparent);color:var(--success,#16a34a);border:1px solid color-mix(in srgb,var(--success,#16a34a) 40%,transparent)}
    .roster-chip.is-pending{background:color-mix(in srgb,var(--warning,#d97706) 20%,transparent);color:var(--warning,#d97706);border:1px solid color-mix(in srgb,var(--warning,#d97706) 45%,transparent)}
    .roster-chip.is-notcall{background:var(--surface-3,var(--surface-2));color:var(--text-muted);border:1px solid var(--stroke)}
    .roster-chip.is-out{background:transparent;color:var(--text-muted);border:1px dashed var(--stroke);opacity:.8}
    .roster-person.is-out .roster-person__name{color:var(--text-muted)}

    /* Sección "alcanzable" (no llamados / fuera) colapsada */
    .roster-more{margin:.15rem .35rem .35rem}
    .roster-more>summary{cursor:pointer;list-style:none;font-size:.8rem;color:var(--text-muted);padding:.45rem .5rem;border-radius:8px;user-select:none}
    .roster-more>summary::-webkit-details-marker{display:none}
    .roster-more>summary:hover{color:var(--text)}
    .roster-more[open]>summary{color:var(--text);font-weight:600}

    .roster-empty{text-align:center;color:var(--text-muted);padding:2.4rem 1rem;border:1px dashed var(--stroke);border-radius:14px}
    .roster-empty svg{width:34px;height:34px;opacity:.5;margin-bottom:.5rem}

    @media (min-width:640px){
        .roster-top{flex-direction:row;align-items:flex-end;justify-content:space-between}
        .roster-nav{justify-content:flex-end}
    }
</style>
@endpush

@section('content')
<div class="container mt-4 mb-5 roster-wrap">

    <div class="roster-top">
        <div>
            <div class="roster-eyebrow">Producción</div>
            <h1 class="roster-h1">Roster del día</h1>
            @if(!empty($roster['production']))
                <div class="roster-prod">{{ $roster['production'] }}</div>
            @endif
        </div>
        <div class="roster-nav">
            <a class="roster-nav__btn" href="{{ route('roster.index', ['date' => $prevDate]) }}" title="Día anterior" aria-label="Día anterior">‹</a>
            <form method="GET" action="{{ route('roster.index') }}" class="roster-nav__date">
                <input type="date" name="date" value="{{ $date->toDateString() }}" data-autosubmit aria-label="Elegir fecha">
            </form>
            @push('scripts')
            <script>
                // Auto-envío del filtro al cambiar la fecha (CSP: sin onchange inline).
                document.addEventListener('change', function (e) {
                    var el = e.target.closest('[data-autosubmit]');
                    if (el && el.form) { el.form.submit(); }
                });
            </script>
            @endpush
            <a class="roster-nav__btn" href="{{ route('roster.index', ['date' => $nextDate]) }}" title="Día siguiente" aria-label="Día siguiente">›</a>
            <a class="roster-nav__today {{ $isToday ? 'is-today' : '' }}" href="{{ route('roster.index') }}">Hoy</a>
        </div>
    </div>

    <div class="roster-daychip">
        {{ $date->locale('es')->isoFormat('ddd D MMM YYYY') }}
        <small>· {{ $dayLabel }}</small>
    </div>

    {{-- Fuera del rodaje / sin ancla: se DICE explícito (prep no es "fuera": lo etiqueta el calendario). --}}
    @if($noAnchor)
        <div class="roster-note">Aún no hay fecha de inicio ni reportes de rodaje: el calendario todavía no puede numerar los días.</div>
    @elseif($afterWrap)
        <div class="roster-note">Este día es <strong>posterior al wrap</strong>@if($wrapDate) ({{ $wrapDate->locale('es')->isoFormat('D MMM YYYY') }})@endif — fuera del rodaje.</div>
    @elseif($beforeAnchor)
        <div class="roster-note">Este día es <strong>de prep</strong> (antes del día 1 de rodaje).</div>
    @endif

    {{-- Totales del día --}}
    <div class="roster-totals" role="group" aria-label="Totales del día">
        <div class="roster-stat is-called"><b>{{ $c[PayeeContract::ROSTER_CALLED] }}</b><span>Llamados</span></div>
        <div class="roster-stat is-pending"><b>{{ $c[PayeeContract::ROSTER_PENDING_SIGNATURE] }}</b><span>Pend. firma</span></div>
        <div class="roster-stat"><b>{{ $c[PayeeContract::ROSTER_NOT_CALLED] }}</b><span>No llamados</span></div>
        <div class="roster-stat"><b>{{ $c[PayeeContract::ROSTER_OUT] }}</b><span>Fuera</span></div>
    </div>

    @if(empty($roster['groups']))
        <div class="roster-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
            <div>Nadie tiene un contrato de crew activo este día.</div>
        </div>
    @else
        @foreach($roster['groups'] as $g)
            @php
                $present = array_values(array_filter($g['people'], fn($p) => $stateMeta[$p['state']]['present']));
                $absent  = array_values(array_filter($g['people'], fn($p) => ! $stateMeta[$p['state']]['present']));
                $gc = $g['counts'];
            @endphp
            <section class="roster-dept">
                <div class="roster-dept__head">
                    <span class="roster-dept__name">{{ $g['label'] }}</span>
                    <span class="roster-dept__count">
                        <b>{{ $gc[PayeeContract::ROSTER_CALLED] }}</b> llamados
                        @if($gc[PayeeContract::ROSTER_PENDING_SIGNATURE]) · {{ $gc[PayeeContract::ROSTER_PENDING_SIGNATURE] }} pend. @endif
                        · {{ $gc['total'] }} en total
                    </span>
                </div>

                {{-- PRESENTES (llamados + pendientes de firma) al frente, en orden de puesto (HOD arriba). --}}
                @if(count($present))
                    <ul class="roster-people">
                        @foreach($present as $p)
                            @php $m = $stateMeta[$p['state']]; @endphp
                            <li class="roster-person {{ $m['cls'] }}">
                                <div class="roster-person__main">
                                    <div class="roster-person__name">{{ $p['name'] }}</div>
                                    @if($p['cargo'] !== '')<div class="roster-person__cargo">{{ $p['cargo'] }}</div>@endif
                                </div>
                                <span class="roster-chip {{ $m['cls'] }}">{{ $m['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- ALCANZABLES (no llamados / fuera): colapsados, para "¿y fulano por qué no está?". --}}
                @if(count($absent))
                    <details class="roster-more">
                        <summary>Ver {{ count($absent) }} no llamado{{ count($absent) === 1 ? '' : 's' }} / fuera</summary>
                        <ul class="roster-people">
                            @foreach($absent as $p)
                                @php $m = $stateMeta[$p['state']]; @endphp
                                <li class="roster-person {{ $m['cls'] }}">
                                    <div class="roster-person__main">
                                        <div class="roster-person__name">{{ $p['name'] }}</div>
                                        @if($p['cargo'] !== '')<div class="roster-person__cargo">{{ $p['cargo'] }}</div>@endif
                                    </div>
                                    <span class="roster-chip {{ $m['cls'] }}">{{ $m['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        @endforeach
    @endif

</div>
@endsection
