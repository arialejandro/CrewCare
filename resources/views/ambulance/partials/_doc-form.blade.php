{{--
    _doc-form.blade.php — FORMULARIO DE CAPTURA de un documento externo.
    Reutilizable para los DOS niveles (empresa y persona): todos los documentos van
    a la MISMA ruta (ambulance.document.store) y a la misma tabla; solo cambia el
    titular (holder_type + holder_id, ocultos).

    ⚠ Es un formulario de CAPTURA, no de validación. Quien captura ≠ quien valida:
      la validación tiene su propio <form> (ver _doc-row.blade.php).

    Recibe:
      - $holderType  'empresa' | 'persona'   (rótulo; el controlador lo mapea al FQCN)
      - $holderId    id del titular (proveedor o tripulante)
      - $idPrefix    prefijo ÚNICO para los id/for (esta vista incluye MUCHOS de estos
                     formularios; sin prefijo los id se repetirían y el HTML sería inválido)
      - $title,$sub,$icon  (opcionales) cabecera de la tarjeta
      - $conocer     (opcional, bool) si true, resalta el bloque CONOCER

    NOTA sobre old(): este parcial se incluye N veces con nombres de campo PLANOS
    idénticos (document_type, folio, …). Por eso NO rehidrata con old(): hacerlo
    volcaría los valores de un formulario en todos los demás. El resumen de errores
    de _form-feedback sí es global y se ve arriba.
--}}
@php
    $idp    = $idPrefix ?? ('doc-' . ($holderType ?? 'x') . '-' . ($holderId ?? '0'));
    $ttl    = $title ?? __('Agregar documento');
    $sub    = $sub   ?? __('Se captura ahora; otra persona lo valida después.');
    $ico    = $icon  ?? 'file-plus';
    $conocer = $conocer ?? false;
@endphp
<div class="cc-form-card amb-docform">
    <div class="cc-form-card__head">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => $ico, 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div class="cc-form-card__titles">
            <h3 class="cc-form-card__title">{{ $ttl }}</h3>
            <p class="cc-form-card__sub">{{ $sub }}</p>
        </div>
    </div>
    <div class="cc-form-card__body">
        <form action="{{ route('ambulance.document.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="holder_type" value="{{ $holderType }}">
            <input type="hidden" name="holder_id" value="{{ $holderId }}">

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-document_type" class="cc-label">
                            {{ __('Tipo de documento') }} <span class="cc-req">*</span>
                        </label>
                        <input id="{{ $idp }}-document_type" type="text" name="document_type" maxlength="120" required
                               class="form-control cc-control"
                               placeholder="{{ __('Aviso de funcionamiento, póliza, TAMP, CONOCER, cédula…') }}">
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-authority" class="cc-label">{{ __('Autoridad emisora') }}</label>
                        <input id="{{ $idp }}-authority" type="text" name="authority" maxlength="160"
                               class="form-control cc-control"
                               placeholder="{{ __('Autoridad sanitaria, CONOCER, SEP…') }}">
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-folio" class="cc-label">{{ __('Folio') }}</label>
                        <input id="{{ $idp }}-folio" type="text" name="folio" maxlength="160"
                               class="form-control cc-control">
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-valid_until" class="cc-label">{{ __('Vigencia (vence el)') }}</label>
                        <input id="{{ $idp }}-valid_until" type="date" name="valid_until"
                               class="form-control cc-control">
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-origen" class="cc-label">{{ __('Origen del requisito') }}</label>
                        <select id="{{ $idp }}-origen" name="origen" class="form-select cc-select">
                            <option value="normativo" selected>{{ __('Obligatorio por norma') }}</option>
                            <option value="contractual">{{ __('Exigido por contrato') }}</option>
                            <option value="recomendado">{{ __('Recomendado') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-exigido_por" class="cc-label">{{ __('Exigido por') }}</label>
                        <input id="{{ $idp }}-exigido_por" type="text" name="exigido_por" maxlength="160"
                               class="form-control cc-control"
                               placeholder="{{ __('Estudio, plataforma, marca, agencia…') }}">
                        <span class="cc-help">{{ __('Solo si no es obligatorio por norma: quién lo pide.') }}</span>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-status" class="cc-label">{{ __('Estado del documento') }}</label>
                        <select id="{{ $idp }}-status" name="status" class="form-select cc-select">
                            <option value="presentado" selected>{{ __('Presentado') }}</option>
                            <option value="en_tramite">{{ __('En trámite') }}</option>
                            <option value="no_aplica">{{ __('No aplica') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="cc-field">
                        <label for="{{ $idp }}-pending_commit_date" class="cc-label">{{ __('Fecha compromiso') }}</label>
                        <input id="{{ $idp }}-pending_commit_date" type="date" name="pending_commit_date"
                               class="form-control cc-control">
                        <span class="cc-help">{{ __('Solo si está en trámite. Sin folio ni esta fecha, «en trámite» no cuenta: es «no lo tiene».') }}</span>
                    </div>
                </div>

                {{-- Compuerta vs condicionante: DATO, no categoría en código. --}}
                <div class="col-12">
                    <div class="cc-field mb-0">
                        <label class="d-flex align-items-start gap-2" for="{{ $idp }}-is_gate">
                            <input type="checkbox" id="{{ $idp }}-is_gate" name="is_gate" value="1" class="mt-1">
                            <span class="cc-help">
                                <strong>{{ __('Es compuerta') }}</strong>
                                {{ __('Sin este documento se DETIENE la actividad. Si se deja sin marcar, es condicionante: el hueco se registra pero no detiene.') }}
                            </span>
                        </label>
                    </div>
                </div>

                {{-- CONOCER: clave + nombre del estándar. Se IMPRIMEN tal cual: es lo que el
                     contratante coteja contra el certificado. No se queman en código. --}}
                <div class="col-12">
                    <div class="amb-conocer {{ $conocer ? 'amb-conocer--hi' : '' }}">
                        <p class="cc-group-title">
                            @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Estándar de competencia (CONOCER)') }}
                        </p>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="cc-field mb-0">
                                    <label for="{{ $idp }}-standard_code" class="cc-label">{{ __('Clave del estándar') }}</label>
                                    <input id="{{ $idp }}-standard_code" type="text" name="standard_code" maxlength="60"
                                           class="form-control cc-control" placeholder="EC0472…">
                                </div>
                            </div>
                            <div class="col-12 col-md-8">
                                <div class="cc-field mb-0">
                                    <label for="{{ $idp }}-standard_name" class="cc-label">{{ __('Nombre oficial del estándar') }}</label>
                                    <input id="{{ $idp }}-standard_name" type="text" name="standard_name" maxlength="255"
                                           class="form-control cc-control">
                                </div>
                            </div>
                        </div>
                        <span class="cc-help">{{ __('Cópialos tal cual vienen en el certificado: se imprimen para cotejarlos.') }}</span>
                    </div>
                </div>

                {{-- Foto del documento/credencial. ⚠ FIX (2026-08-13): el input se llamaba
                     `photo_path` pero storeDocument valida/lee `photo` (hasFile('photo')) →
                     la foto se descartaba EN SILENCIO. Ahora el name = `photo`, que es lo que
                     el controlador espera; la columna destino sigue siendo photo_path. --}}
                <div class="col-12">
                    <div class="cc-field mb-0">
                        <label for="{{ $idp }}-photo" class="cc-label">
                            @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico-16', 'label' => null])
                            {{ __('Foto del documento') }}
                        </label>
                        <input id="{{ $idp }}-photo" type="file" name="photo"
                               accept="image/*,.heic,.heif" capture="environment" data-cc-photo
                               class="form-control cc-control">
                        <span class="cc-help">{{ __('El riesgo real es un papel de otra persona: la foto permite cotejar el documento con quien lo presenta.') }}</span>
                    </div>
                </div>
            </div>

            <div class="d-grid d-md-flex justify-content-md-end mt-3">
                <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto">
                    @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-18', 'label' => null])
                    {{ __('Agregar documento') }}
                </button>
            </div>
        </form>
    </div>
</div>
