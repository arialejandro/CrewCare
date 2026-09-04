@extends('layouts.app')

@section('content')

@include('componentes._form-kit')
{{-- Confirmación de submits destructivos por delegación (data-confirm), sin onsubmit inline (CSP). --}}
@include('componentes._confirm-submit')

@push('styles')
<style>
    .cc-cat-wrap { max-width: 1100px; margin: 0 auto; }
    .cc-cat-table { color: var(--text); margin: 0; width: 100%; border-collapse: collapse; }
    .cc-cat-table thead th {
        font-size: .68rem; text-transform: uppercase; letter-spacing: .04em;
        color: var(--text-muted); font-weight: 700; background: transparent;
        border-bottom: 1px solid var(--stroke, var(--border)); padding: .5rem .6rem; white-space: nowrap; text-align: left;
    }
    .cc-cat-table tbody td { border-bottom: 1px solid var(--stroke, var(--border)); vertical-align: middle; padding: .5rem .6rem; }
    .cc-cat-table tbody tr:last-child td { border-bottom: 0; }
    .cc-cat-table tbody tr:hover td { background: rgba(var(--brand-primary-rgb), .05); }
    .cc-pos-name { font-weight: 600; color: var(--text); }
    .cc-pos-en   { color: var(--text-muted); font-size: .82rem; }
    .cc-pos--off td { opacity: .5; }

    .cc-dept { margin: 0 0 1rem; border: 1px solid var(--stroke, var(--border)); border-radius: 12px; overflow: hidden; background: var(--surface); }
    .cc-dept__head { display: flex; align-items: center; gap: .6rem; padding: .7rem .9rem; background: var(--surface-2); border-bottom: 1px solid var(--stroke, var(--border)); }
    .cc-dept__name { font-weight: 700; color: var(--text); }
    .cc-dept__en { color: var(--text-muted); font-weight: 500; font-size: .85rem; }
    .cc-dept__spacer { margin-left: auto; }

    .cc-chip { display: inline-block; font-size: .68rem; font-weight: 700; padding: .12rem .45rem; border-radius: 999px; letter-spacing: .02em; }
    .cc-chip--muted { color: var(--text-muted); background: var(--surface-2); border: 1px solid var(--stroke-2, var(--border)); }
    .cc-chip--cond  { color: #8a5a00; background: rgba(240,170,0,.14); border: 1px solid rgba(240,170,0,.35); }
    .cc-chip--juris { color: #5a3aa0; background: rgba(120,80,220,.12); border: 1px solid rgba(120,80,220,.32); }
    .cc-chip--rank  { color: var(--text); background: var(--surface-2); border: 1px solid var(--stroke-2, var(--border)); font-variant-numeric: tabular-nums; }
    .cc-chip--dup   { color: #8a5a00; background: rgba(240,170,0,.14); border: 1px solid rgba(240,170,0,.35); cursor: help; margin-left: .35rem; }

    .cc-hod { border: 1px solid var(--stroke-2, var(--border)); background: var(--surface-2); color: var(--text-muted);
              font-weight: 700; font-size: .72rem; border-radius: 999px; padding: .18rem .55rem; cursor: pointer; min-width: 3.2rem; }
    .cc-hod.is-on { background: rgba(var(--brand-primary-rgb), .14); border-color: rgba(var(--brand-primary-rgb), .5); color: var(--brand-primary, var(--text)); }
    .cc-mini { font-size: .78rem; padding: .2rem .5rem; border-radius: 8px; border: 1px solid var(--stroke-2, var(--border)); background: var(--surface); color: var(--text); cursor: pointer; text-decoration: none; display: inline-block; }
    .cc-mini--danger { color: #b23; border-color: rgba(200,40,60,.35); }
    .cc-assign { font-variant-numeric: tabular-nums; color: var(--text-muted); font-size: .82rem; }
    .cc-toolbar { display: flex; gap: .5rem; flex-wrap: wrap; margin: 0 0 1rem; }
    .cc-panel { border: 1px solid var(--stroke, var(--border)); border-radius: 12px; padding: 1rem; margin: 0 0 1.2rem; background: var(--surface); }
    .cc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .7rem; align-items: end; }
    .cc-scroll { overflow-x: auto; }
</style>
@endpush

<div class="cc-cat-wrap">

    <div class="cc-form-hero" style="margin-bottom:1rem;">
        <h1 class="cc-form-title">{{ __('Catálogo organizacional') }}</h1>
        <p class="cc-help">
            {{ __('Corrige la estructura sin tocar la base. La jefatura (HOD) es una marca operativa: un departamento puede tener varias.') }}
            <strong>{{ $departments->count() }}</strong> {{ __('departamentos') }} ·
            <strong>{{ $positionsByDept->flatten()->count() }}</strong> {{ __('puestos') }}.
        </p>
    </div>

    @foreach (['success' => 'ok', 'error' => 'danger'] as $key => $kind)
        @if (session($key))
            <div class="cc-alert cc-alert--{{ $kind }}" role="status">{{ session($key) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="cc-alert cc-alert--danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="cc-toolbar">
        <button type="button" class="cc-btn cc-btn--ghost" data-cc-toggle="cc-new-dept">+ {{ __('Departamento') }}</button>
        <button type="button" class="cc-btn cc-btn--ghost" data-cc-toggle="cc-new-pos">+ {{ __('Puesto') }}</button>
    </div>

    {{-- Alta de departamento --}}
    <div class="cc-panel" id="cc-new-dept" style="display:none;">
        <form method="POST" action="{{ route('catalogo.dept.store') }}">
            @csrf
            <div class="cc-grid">
                <div class="cc-field"><label class="cc-label">{{ __('Nombre (ES)') }}</label>
                    <input type="text" name="name" class="form-control cc-control" required maxlength="120"></div>
                <div class="cc-field"><label class="cc-label">{{ __('Nombre (EN)') }}</label>
                    <input type="text" name="name_en" class="form-control cc-control" maxlength="255"></div>
                <div class="cc-field"><button type="submit" class="cc-btn cc-btn--primary">{{ __('Crear departamento') }}</button></div>
            </div>
        </form>
    </div>

    {{-- Alta de puesto --}}
    <div class="cc-panel" id="cc-new-pos" style="display:none;">
        <form method="POST" action="{{ route('catalogo.position.store') }}">
            @csrf
            <div class="cc-grid">
                <div class="cc-field"><label class="cc-label">{{ __('Departamento') }}</label>
                    <select name="department_id" class="form-select cc-control" required>
                        @foreach ($departments as $d)
                            @if ($d->active)<option value="{{ $d->id }}">{{ $d->name }}</option>@endif
                        @endforeach
                    </select></div>
                <div class="cc-field"><label class="cc-label">{{ __('Nombre (ES)') }}</label>
                    <input type="text" name="name" class="form-control cc-control" required maxlength="150"></div>
                <div class="cc-field"><label class="cc-label">{{ __('Nombre (EN)') }}</label>
                    <input type="text" name="name_en" class="form-control cc-control" maxlength="255"></div>
                <div class="cc-field"><label class="cc-label">{{ __('Rango') }}</label>
                    <input type="number" name="rank" class="form-control cc-control" required min="1" max="99" value="60"></div>
                <div class="cc-field"><label class="cc-label">{{ __('Binding') }}</label>
                    <select name="binding" class="form-select cc-control" required>
                        @foreach ($bindings as $b)<option value="{{ $b }}" @if($b==='unit') selected @endif>{{ $b }}</option>@endforeach
                    </select></div>
                <div class="cc-field"><label class="cc-label">{{ __('Grado') }}</label>
                    <select name="grade" class="form-select cc-control">
                        <option value="">{{ __('—') }}</option>
                        @foreach ($grades as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
                    </select></div>
                <div class="cc-field"><label class="cc-label">&nbsp;</label>
                    <label class="cc-check"><input type="checkbox" name="is_hod" value="1"> {{ __('Jefatura (HOD)') }}</label></div>
                <div class="cc-field"><button type="submit" class="cc-btn cc-btn--primary">{{ __('Crear puesto') }}</button></div>
            </div>
        </form>
    </div>

    {{-- Listado por departamento --}}
    @foreach ($departments as $dept)
        @php $rows = $positionsByDept[$dept->id] ?? collect(); @endphp
        <section class="cc-dept">
            <div class="cc-dept__head">
                <span class="cc-dept__name">{{ $dept->name }}</span>
                @if ($dept->name_en)<span class="cc-dept__en">· {{ $dept->name_en }}</span>@endif
                @unless ($dept->active)<span class="cc-chip cc-chip--muted">{{ __('Inactivo') }}</span>@endunless
                <span class="cc-dept__spacer"></span>
                <span class="cc-assign">{{ $assignByDept[$dept->id] ?? 0 }} {{ __('asignados') }}</span>
                <a href="{{ route('catalogo.dept.edit', $dept->id) }}" class="cc-mini">{{ __('Editar') }}</a>
                @if ($dept->active)
                    <form method="POST" action="{{ route('catalogo.dept.deactivate', $dept->id) }}" class="d-inline"
                          data-confirm="{{ __('Desactivar el departamento') }} “{{ $dept->name }}”. {{ $assignByDept[$dept->id] ?? 0 }} {{ __('persona(s) asignada(s). No se borra. ¿Continuar?') }}">
                        @csrf <button type="submit" class="cc-mini cc-mini--danger">{{ __('Desactivar') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('catalogo.dept.activate', $dept->id) }}" class="d-inline">
                        @csrf <button type="submit" class="cc-mini">{{ __('Reactivar') }}</button>
                    </form>
                @endif
            </div>

            @if ($rows->isEmpty())
                <div style="padding:.8rem .9rem;color:var(--text-muted);font-size:.85rem;">{{ __('Sin puestos.') }}</div>
            @else
                <div class="cc-scroll">
                <table class="cc-cat-table">
                    <thead><tr>
                        <th>{{ __('Rango') }}</th><th>{{ __('Puesto') }}</th><th>{{ __('Binding') }}</th>
                        <th>{{ __('Grado') }}</th><th>{{ __('Jefatura') }}</th><th>{{ __('Existencia') }}</th>
                        <th>{{ __('Asignados') }}</th><th>{{ __('Acciones') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($rows as $p)
                        <tr class="{{ $p->active ? '' : 'cc-pos--off' }}">
                            <td><span class="cc-chip cc-chip--rank">{{ $p->rank }}</span></td>
                            <td>
                                <div class="cc-pos-name">{{ $p->name }}@if (! empty($similar[$p->id]))<span class="cc-chip cc-chip--dup" title="{{ __('Nombre parecido a') }}: {{ implode(' · ', $similar[$p->id]) }}">≈</span>@endif</div>
                                @if ($p->name_en)<div class="cc-pos-en">{{ $p->name_en }}</div>@endif
                            </td>
                            <td style="font-size:.82rem;color:var(--text-muted);">{{ $p->binding }}</td>
                            <td style="font-size:.82rem;color:var(--text-muted);">{{ $p->grade ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('catalogo.position.hod', $p->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="cc-hod {{ $p->is_hod ? 'is-on' : '' }}"
                                            title="{{ __('Marca operativa de jefatura. Un depto puede tener varias.') }}">
                                        {{ $p->is_hod ? 'HOD' : '—' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                @if ($p->existence === 'conditional')
                                    <span class="cc-chip cc-chip--cond">{{ __('condicional') }}</span>
                                @elseif ($p->existence === 'jurisdictional')
                                    <span class="cc-chip cc-chip--juris">{{ __('jurisdiccional') }}</span>
                                @else
                                    <span class="cc-chip cc-chip--muted">core</span>
                                @endif
                            </td>
                            <td class="cc-assign">{{ $assignByPos[$p->id] ?? 0 }}</td>
                            <td>
                                <a href="{{ route('catalogo.position.edit', $p->id) }}" class="cc-mini">{{ __('Editar') }}</a>
                                @if ($p->active)
                                    <form method="POST" action="{{ route('catalogo.position.deactivate', $p->id) }}" class="d-inline"
                                          data-confirm="{{ __('Desactivar') }} “{{ $p->name }}”. {{ $assignByPos[$p->id] ?? 0 }} {{ __('persona(s) asignada(s). No se borra. ¿Continuar?') }}">
                                        @csrf <button type="submit" class="cc-mini cc-mini--danger">{{ __('Desactivar') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('catalogo.position.activate', $p->id) }}" class="d-inline">
                                        @csrf <button type="submit" class="cc-mini">{{ __('Reactivar') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </section>
    @endforeach
</div>

@push('scripts')
<script>
    function ccToggle(id) {
        var el = document.getElementById(id);
        if (el) { el.style.display = (el.style.display === 'none' || !el.style.display) ? 'block' : 'none'; }
    }
    // Delegación de los toggles (+Departamento / +Puesto) — CSP: sin onclick inline.
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-cc-toggle]');
        if (t) { ccToggle(t.getAttribute('data-cc-toggle')); }
    });
</script>
@endpush

@endsection
