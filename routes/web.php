<?php
// Add this to your routes/web.php temporarily for debugging

Route::get('/debug-images', function() {
    $debugInfo = [
        'PHP Version' => PHP_VERSION,
        'GD Installed' => extension_loaded('gd'),
        'GD Info' => function_exists('gd_info') ? gd_info() : 'GD not available',
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time'),
        'Storage Link Exists' => is_link(public_path('storage')),
        'Storage Link Target' => is_link(public_path('storage')) ? readlink(public_path('storage')) : 'N/A',
        'Cache Directory Exists' => is_dir(public_path('cache')),
        'Cache Writable' => is_writable(public_path('cache')),
        'Cache Permissions' => substr(sprintf('%o', fileperms(public_path('cache'))), -4),
        'Storage App Public Exists' => is_dir(storage_path('app/public')),
        'Storage App Public Writable' => is_writable(storage_path('app/public')),
        'APP_URL' => config('app.url'),
        'ASSET_URL' => config('app.asset_url'),
        'Filesystem Default' => config('filesystems.default'),
        'Image Driver' => config('image.driver', 'not set'),
    ];
    
    // Try to create a test image
    try {
        if (class_exists('\Intervention\Image\Facades\Image')) {
            $testImage = \Intervention\Image\Facades\Image::canvas(100, 100, '#ff0000');
            $testPath = public_path('cache/test-image.jpg');
            $testImage->save($testPath);
            $debugInfo['Test Image Created'] = file_exists($testPath) ? 'Success' : 'Failed';
            if (file_exists($testPath)) {
                unlink($testPath);
            }
        } else {
            $debugInfo['Intervention Image'] = 'Not loaded';
        }
    } catch (\Exception $e) {
        $debugInfo['Image Test Error'] = $e->getMessage();
    }
    
    // Check if original images exist
    $sampleImagePath = storage_path('app/public/product');
    if (is_dir($sampleImagePath)) {
        $debugInfo['Product Images Directory'] = 'Exists';
        $files = glob($sampleImagePath . '/*/*.*');
        $debugInfo['Product Images Count'] = count($files);
        if (count($files) > 0) {
            $debugInfo['Sample Image'] = basename($files[0]);
        }
    }
    
    return response()->json($debugInfo, 200, [], JSON_PRETTY_PRINT);
});