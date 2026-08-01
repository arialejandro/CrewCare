$paths = [
    "resources/views/admin/hazardnotification.blade.php",
    "resources/views/admin/hazard.blade.php",
    "resources/views/admin/hazards.blade.php",
];
foreach ($paths as $p) {
    try {
        \Illuminate\Support\Facades\Blade::compileString(file_get_contents($p));
        echo "OK: $p\n";
    } catch (\Throwable $e) {
        echo "FAIL: $p => " . $e->getMessage() . "\n";
    }
}
