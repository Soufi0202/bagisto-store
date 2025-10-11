<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Update all HTTP URLs to HTTPS in core_config table
try {
    DB::table('core_config')->where('code', 'general.general.base_url')
        ->update(['value' => 'https://web-production-50b36.up.railway.app']);
    
    DB::table('core_config')->where('code', 'LIKE', '%url%')
        ->where('value', 'LIKE', 'http://%')
        ->update(['value' => DB::raw("REPLACE(value, 'http://', 'https://')")]);
    
    DB::table('channels')->update([
        'home_seo' => DB::raw("REPLACE(home_seo, 'http://', 'https://')"),
        'root_category_id' => DB::raw('root_category_id')
    ]);
    
    echo "✓ HTTPS URLs updated successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}