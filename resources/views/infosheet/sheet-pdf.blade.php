{{-- HOJA DE INFORMACIÓN · página autónoma para PDF (Chrome headless). Reusa el MISMO documento que se
     ve en la ficha (_infosheet-document); su <style> va INLINE con el partial. Se congela byte-intact
     en el sobre y se firma junto al contrato. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { size: A4 portrait; margin: 12mm; }
    body { margin:0; background:#fff; color:#1f2a3a; font-family:'Poppins','Segoe UI',Arial,sans-serif; }
</style>
</head>
<body>
@include('componentes._infosheet-document', [
    'contract'         => $contract,
    'contractedSig'    => $contractedSig ?? null,
    'contractedAnchor' => $contractedAnchor ?? false,
])
</body>
</html>
