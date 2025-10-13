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


Route::get('/find-all-images', function() {
    $results = [];
    
    // Search for all image files in storage
    $storagePath = storage_path('app/public');
    
    // Find all directories
    $directories = [
        'storage/app/public root' => glob($storagePath . '/*'),
        'product' => glob($storagePath . '/product/*'),
        'category' => glob($storagePath . '/category/*'),
        'theme' => glob($storagePath . '/theme/*'),
        'theme/1' => glob($storagePath . '/theme/1/*'),
        'theme/default' => glob($storagePath . '/theme/default/*'),
    ];
    
    foreach ($directories as $key => $files) {
        if (!empty($files)) {
            $results[$key] = [
                'count' => count($files),
                'samples' => array_slice(array_map('basename', $files), 0, 5)
            ];
        }
    }
    
    // Also check public directory
    $publicChecks = [
        'public/storage exists' => is_link(public_path('storage')),
        'public/cache exists' => is_dir(public_path('cache')),
        'public/themes' => glob(public_path('themes/*')),
        'public/vendor' => glob(public_path('vendor/webkul/*')),
    ];
    
    foreach ($publicChecks as $key => $value) {
        if (is_bool($value)) {
            $results[$key] = $value;
        } elseif (!empty($value)) {
            $results[$key] = array_map('basename', $value);
        }
    }
    
    // Find .webp files specifically (the ones failing)
    $webpFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storagePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'webp') {
            $relativePath = str_replace($storagePath . '/', '', $file->getPathname());
            $webpFiles[] = $relativePath;
            if (count($webpFiles) >= 10) break; // Limit to first 10
        }
    }
    
    $results['WebP files found'] = $webpFiles;
    
    // Check database for image paths
    try {
        $productImages = \DB::table('product_images')
            ->select('path', 'type')
            ->limit(5)
            ->get();
        $results['Database product_images'] = $productImages;
        
        $categoryImages = \DB::table('categories')
            ->whereNotNull('image_url')
            ->select('image_url')
            ->limit(5)
            ->get();
        $results['Database category images'] = $categoryImages;
    } catch (\Exception $e) {
        $results['Database check'] = 'Error: ' . $e->getMessage();
    }
    
    return response()->json($results, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

Route::get('/fix-banner-images', function() {
    $results = [];
    
    try {
        // These are the exact images your homepage is trying to load
        $bannerImages = [
            'BdLuxahbnROBwiYu7aThBQeiErp371QTUPqbsWpu.webp',
            'M8IrLJi2CmtGQtX3FFn2zqqSNPMUiVIkGYQsOeaE.webp',
            'DumdAM9sTnjSxbD8MrvVLDSAeslGIOAJdmBSKmLX.webp',
            'CLqWVWAeyCkxALTGO3GBlbWui95L78emDrm9CtID.webp'
        ];
        
        // Create theme/1 directory in storage
        $themePath = storage_path('app/public/theme/1');
        if (!is_dir($themePath)) {
            mkdir($themePath, 0777, true);
            $results['theme_directory'] = 'Created: ' . $themePath;
        }
        
        // Create each banner image
        foreach ($bannerImages as $index => $filename) {
            $fullPath = $themePath . '/' . $filename;
            
            // Create a professional-looking banner image
            $width = 1920;
            $height = 450;
            $image = imagecreatetruecolor($width, $height);
            
            // Different color schemes for each banner
            $colorSchemes = [
                ['bg' => [41, 128, 185], 'accent' => [52, 152, 219]], // Blue
                ['bg' => [39, 174, 96], 'accent' => [46, 204, 113]], // Green  
                ['bg' => [192, 57, 43], 'accent' => [231, 76, 60]], // Red
                ['bg' => [142, 68, 173], 'accent' => [155, 89, 182]], // Purple
            ];
            
            $scheme = $colorSchemes[$index % count($colorSchemes)];
            
            // Create gradient background
            for ($y = 0; $y < $height; $y++) {
                $ratio = $y / $height;
                $r = $scheme['bg'][0] + ($scheme['accent'][0] - $scheme['bg'][0]) * $ratio;
                $g = $scheme['bg'][1] + ($scheme['accent'][1] - $scheme['bg'][1]) * $ratio;
                $b = $scheme['bg'][2] + ($scheme['accent'][2] - $scheme['bg'][2]) * $ratio;
                $color = imagecolorallocate($image, $r, $g, $b);
                imagefilledrectangle($image, 0, $y, $width, $y + 1, $color);
            }
            
            // Add some overlay shapes for visual interest
            $overlayColor = imagecolorallocatealpha($image, 255, 255, 255, 110);
            imagefilledellipse($image, -100, $height/2, 600, 600, $overlayColor);
            imagefilledellipse($image, $width + 100, $height/2, 600, 600, $overlayColor);
            
            // Add text
            $white = imagecolorallocate($image, 255, 255, 255);
            $texts = [
                "Welcome to Our B2B Store",
                "Quality Products for Your Business",
                "Trusted by Industry Leaders",
                "Exclusive B2B Pricing"
            ];
            
            $text = $texts[$index % count($texts)];
            $fontSize = 5; // Built-in font size
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $x = ($width - $textWidth) / 2;
            $y = ($height / 2) - 10;
            
            // Add shadow for text
            $shadow = imagecolorallocate($image, 0, 0, 0);
            imagestring($image, $fontSize, $x + 2, $y + 2, $text, $shadow);
            imagestring($image, $fontSize, $x, $y, $text, $white);
            
            // Add subtitle
            $subtitle = "Get Started Today";
            $subtitleWidth = imagefontwidth(3) * strlen($subtitle);
            $subX = ($width - $subtitleWidth) / 2;
            imagestring($image, 3, $subX, $y + 30, $subtitle, $white);
            
            // Save as WebP
            if (imagewebp($image, $fullPath, 85)) {
                $results['created_' . $index] = $filename;
            } else {
                $results['error_' . $index] = 'Failed to create ' . $filename;
            }
            
            imagedestroy($image);
        }
        
        // Also create the cache directories
        $cacheTypes = ['small', 'medium', 'large', 'original'];
        foreach ($cacheTypes as $type) {
            $cacheDir = public_path("cache/{$type}/theme/1");
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
                $results["cache_{$type}_dir"] = 'Created';
            }
        }
        
        // Clear any cached views
        \Artisan::call('view:clear');
        \Artisan::call('cache:clear');
        
        $results['success'] = true;
        $results['message'] = 'Banner images created successfully! Refresh your homepage.';
        
    } catch (\Exception $e) {
        $results['error'] = $e->getMessage();
        $results['trace'] = $e->getTraceAsString();
    }
    
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

// Optional: Route to check if images were created successfully
Route::get('/check-banner-images', function() {
    $themePath = storage_path('app/public/theme/1');
    $images = glob($themePath . '/*.webp');
    
    $result = [
        'theme_path' => $themePath,
        'theme_exists' => is_dir($themePath),
        'images_count' => count($images),
        'images' => array_map('basename', $images),
        'storage_link' => is_link(public_path('storage')),
        'cache_dirs' => [
            'large' => is_dir(public_path('cache/large/theme/1')),
            'medium' => is_dir(public_path('cache/medium/theme/1')),
            'small' => is_dir(public_path('cache/small/theme/1')),
        ]
    ];
    
    return response()->json($result, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/debug-storage', function() {
    $storagePath = storage_path('app/public');
    $publicStorage = public_path('storage');
    
    return [
        'storage_exists' => is_dir($storagePath),
        'storage_readable' => is_readable($storagePath),
        'storage_files' => is_dir($storagePath) ? scandir($storagePath) : [],
        'public_storage_link_exists' => is_link($publicStorage),
        'public_storage_target' => is_link($publicStorage) ? readlink($publicStorage) : 'not a link',
    ];
});
