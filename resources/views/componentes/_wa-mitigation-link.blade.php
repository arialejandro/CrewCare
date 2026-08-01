{{-- Partial: canal WhatsApp + estado de foto de mitigación para un ActionItem.
     El orquestador lo incluye en las vistas SHOW donde se listan action items:
        @include('componentes._wa-mitigation-link', ['item' => $actionItem])
     Recibe $item (un App\Models\ActionItem con el trait HasMagicMitigation).
     Todo va envuelto en @feature('magic_links'). --}}
@feature('magic_links')
    <div class="mt-2">
        @if ($item->hasMitigation())
            {{-- Ya llegó la evidencia: check verde + miniatura + fecha --}}
            <div class="d-flex align-items-start gap-2">
                <span class="text-success" style="font-size:1.1rem; line-height:1;">&#10003;</span>
                <div>
                    <div class="text-success fw-semibold small">Foto de mitigación recibida</div>
                    @php
                        $mitDate = null;
                        if (\Illuminate\Support\Facades\Schema::hasColumn('action_items', 'mitigation_uploaded_at')
                            && ! empty($item->mitigation_uploaded_at)) {
                            $mitDate = $item->mitigation_uploaded_at instanceof \Carbon\Carbon
                                ? $item->mitigation_uploaded_at->format('d/m/Y H:i')
                                : (string) $item->mitigation_uploaded_at;
                        }
                    @endphp
                    @if ($mitDate)
                        <div class="text-muted small">{{ $mitDate }}</div>
                    @endif
                    <a href="{{ $item->mitigation_image_path }}" target="_blank" rel="noopener">
                        <img src="{{ $item->mitigation_image_path }}"
                             alt="Foto de mitigación"
                             class="rounded border mt-1"
                             style="max-width:120px; max-height:120px; object-fit:cover;">
                    </a>
                </div>
            </div>
        @else
            {{-- Aún sin evidencia: enviar el Magic Link por WhatsApp --}}
            <a href="{{ $item->waLink() }}" target="_blank" rel="noopener"
               class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1">
                {{-- Icono WhatsApp (SVG inline; sin dependencias externas por CSP) --}}
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.02h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.36c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.83c0 4.55-3.7 8.24-8.25 8.24Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.25-.64.81-.79.98-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.43l-.48-.01c-.16 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.16 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29Z"/>
                </svg>
                Enviar link por WhatsApp
            </a>
            <div class="text-muted small mt-1">
                Si no hay red, omite el link: el cierre se gestiona dentro del sistema.
            </div>
        @endif
    </div>
@endfeature
