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



Route::get('/debug-bagisto-images', function() {
    $debug = [];
    
    // Check Intervention Image
    $debug['Intervention Image Installed'] = class_exists('\Intervention\Image\ImageManager');
    $debug['Intervention Facade Available'] = class_exists('\Intervention\Image\Facades\Image');
    
    // Check Bagisto image cache paths
    $cachePaths = [
        'cache/large/theme/1' => public_path('cache/large/theme/1'),
        'cache/medium/theme/1' => public_path('cache/medium/theme/1'),
        'cache/small/theme/1' => public_path('cache/small/theme/1'),
    ];
    
    foreach ($cachePaths as $key => $path) {
        $debug['Path: ' . $key] = [
            'exists' => is_dir($path),
            'writable' => is_writable($path),
            'files_count' => is_dir($path) ? count(glob($path . '/*')) : 0
        ];
    }
    
    // Check original images
    $originalPath = storage_path('app/public/theme/1');
    if (is_dir($originalPath)) {
        $files = glob($originalPath . '/*');
        $debug['Original Theme Images'] = [
            'path' => $originalPath,
            'exists' => true,
            'count' => count($files),
            'samples' => array_slice(array_map('basename', $files), 0, 3)
        ];
    } else {
        $debug['Original Theme Images'] = 'Directory not found: ' . $originalPath;
    }
    
    // Try to manually process an image like Bagisto would
    try {
        if (class_exists('\Intervention\Image\ImageManager')) {
            // Find a test image
            $testImagePath = null;
            $possiblePaths = [
                storage_path('app/public/theme/1/*.webp'),
                storage_path('app/public/theme/1/*.jpg'),
                storage_path('app/public/product/*/*.jpg'),
            ];
            
            foreach ($possiblePaths as $pattern) {
                $files = glob($pattern);
                if (!empty($files)) {
                    $testImagePath = $files[0];
                    break;
                }
            }
            
            if ($testImagePath) {
                $debug['Test Image Processing'] = [
                    'original' => basename($testImagePath),
                    'size' => filesize($testImagePath) . ' bytes',
                ];
                
                // Try to process it
                $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
                $image = $manager->make($testImagePath);
                $image->resize(300, 300, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                
                $outputPath = public_path('cache/test-processed.jpg');
                $image->save($outputPath, 80);
                
                $debug['Test Image Processing']['success'] = file_exists($outputPath);
                if (file_exists($outputPath)) {
                    $debug['Test Image Processing']['output_size'] = filesize($outputPath) . ' bytes';
                    unlink($outputPath);
                }
            } else {
                $debug['Test Image Processing'] = 'No test images found';
            }
        }
    } catch (\Exception $e) {
        $debug['Image Processing Error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    
    // Check PHP settings
    $debug['PHP Settings'] = [
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
    ];
    
    return response()->json($debug, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

// Also add a route to see the actual error when loading an image
Route::get('/test-image-generation/{path}', function($path) {
    try {
        // Enable error reporting
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        
        $originalPath = storage_path('app/public/theme/1/' . $path);
        
        if (!file_exists($originalPath)) {
            return response()->json(['error' => 'Original image not found: ' . $originalPath], 404);
        }
        
        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $image = $manager->make($originalPath);
        
        // Resize like Bagisto would
        $image->resize(360, 360, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        
        // Try to save
        $cachePath = public_path('cache/large/theme/1');
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        
        $outputPath = $cachePath . '/' . $path;
        $image->save($outputPath, 80);
        
        return response()->json([
            'success' => true,
            'original' => $originalPath,
            'output' => $outputPath,
            'exists' => file_exists($outputPath)
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->where('path', '.*');