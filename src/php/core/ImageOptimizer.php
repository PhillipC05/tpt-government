<?php

namespace TPT\GovPlatform\Core;

use Exception;
use Imagick;
use GdImage;

/**
 * Advanced Image Optimization and Processing System
 *
 * This class provides comprehensive image optimization including:
 * - Multiple format support (JPEG, PNG, WebP, AVIF)
 * - Intelligent compression algorithms
 * - Responsive image generation
 * - Lazy loading optimization
 * - CDN integration
 * - Performance monitoring
 * - Batch processing capabilities
 */
class ImageOptimizer
{
    private array $config;
    private array $supportedFormats = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'avif'];
    private array $optimizationStats = [
        'processed' => 0,
        'original_size' => 0,
        'optimized_size' => 0,
        'compression_ratio' => 0,
        'processing_time' => 0
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'quality' => 85,
            'max_width' => 1920,
            'max_height' => 1080,
            'enable_webp' => true,
            'enable_avif' => true,
            'strip_metadata' => true,
            'interlace' => true,
            'optimize_colors' => true,
            'batch_processing' => true,
            'cache_optimized' => true,
            'cdn_integration' => false,
            'lazy_loading' => true,
            'responsive_sizes' => [320, 640, 1024, 1920],
            'temp_directory' => '/tmp/image_optimizer'
        ], $config);

        $this->ensureTempDirectory();
        $this->createImageTables();
    }

    /**
     * Ensure temporary directory exists
     */
    private function ensureTempDirectory(): void
    {
        if (!is_dir($this->config['temp_directory'])) {
            mkdir($this->config['temp_directory'], 0755, true);
        }
    }

    /**
     * Create image optimization tracking tables
     */
    private function createImageTables(): void
    {
        // This would create tables for image optimization tracking
        // Implementation would include tables for:
        // - image_optimization_log
        // - image_formats
        // - image_performance_metrics
    }

    /**
     * Optimize single image with multiple formats
     */
    public function optimizeImage(string $inputPath, array $options = []): array
    {
        $options = array_merge([
            'output_formats' => ['webp', 'original'],
            'quality' => $this->config['quality'],
            'max_width' => $this->config['max_width'],
            'max_height' => $this->config['max_height'],
            'strip_metadata' => $this->config['strip_metadata'],
            'generate_responsive' => true
        ], $options);

        $startTime = microtime(true);
        $results = [
            'original_path' => $inputPath,
            'original_size' => filesize($inputPath),
            'optimized_versions' => [],
            'responsive_versions' => [],
            'processing_time' => 0,
            'compression_ratio' => 0,
            'errors' => []
        ];

        try {
            // Validate input image
            if (!$this->validateImage($inputPath)) {
                throw new Exception("Invalid image file: {$inputPath}");
            }

            // Get image info
            $imageInfo = $this->getImageInfo($inputPath);
            $results['original_info'] = $imageInfo;

            // Resize if necessary
            $resizedPath = $this->resizeImage($inputPath, $options['max_width'], $options['max_height']);

            // Generate optimized versions
            foreach ($options['output_formats'] as $format) {
                try {
                    $optimizedPath = $this->convertFormat($resizedPath, $format, $options);
                    if ($optimizedPath) {
                        $results['optimized_versions'][$format] = [
                            'path' => $optimizedPath,
                            'size' => filesize($optimizedPath),
                            'format' => $format
                        ];
                    }
                } catch (Exception $e) {
                    $results['errors'][] = "Failed to convert to {$format}: " . $e->getMessage();
                }
            }

            // Generate responsive versions
            if ($options['generate_responsive']) {
                $results['responsive_versions'] = $this->generateResponsiveImages($resizedPath, $this->config['responsive_sizes']);
            }

            // Calculate statistics
            $totalOptimizedSize = array_sum(array_column($results['optimized_versions'], 'size'));
            $results['processing_time'] = microtime(true) - $startTime;
            $results['compression_ratio'] = $this->calculateCompressionRatio($results['original_size'], $totalOptimizedSize);

            // Update global statistics
            $this->updateOptimizationStats($results);

            // Clean up temporary files
            if ($resizedPath !== $inputPath) {
                unlink($resizedPath);
            }

            return $results;

        } catch (Exception $e) {
            $results['errors'][] = "Image optimization failed: " . $e->getMessage();
            return $results;
        }
    }

    /**
     * Generate responsive image versions
     */
    public function generateResponsiveImages(string $inputPath, array $sizes): array
    {
        $responsiveVersions = [];

        foreach ($sizes as $size) {
            try {
                $outputPath = $this->resizeImage($inputPath, $size, null, "responsive_{$size}_");
                $webpPath = $this->convertFormat($outputPath, 'webp', ['quality' => 80]);

                $responsiveVersions[$size] = [
                    'original' => $outputPath,
                    'webp' => $webpPath,
                    'size' => filesize($webpPath),
                    'width' => $size
                ];

            } catch (Exception $e) {
                error_log("Failed to generate responsive image for size {$size}: " . $e->getMessage());
            }
        }

        return $responsiveVersions;
    }

    /**
     * Batch optimize multiple images
     */
    public function batchOptimize(array $imagePaths, array $options = []): array
    {
        $options = array_merge([
            'concurrent' => 3,
            'progress_callback' => null,
            'error_callback' => null
        ], $options);

        $results = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'total_original_size' => 0,
            'total_optimized_size' => 0,
            'average_compression' => 0,
            'processing_time' => 0,
            'results' => []
        ];

        $startTime = microtime(true);
        $batches = array_chunk($imagePaths, $options['concurrent']);

        foreach ($batches as $batchIndex => $batch) {
            $batchPromises = [];

            foreach ($batch as $imagePath) {
                try {
                    $result = $this->optimizeImage($imagePath, $options);
                    $results['results'][] = $result;
                    $results['total_processed']++;

                    if (empty($result['errors'])) {
                        $results['successful']++;
                        $results['total_original_size'] += $result['original_size'];
                        $results['total_optimized_size'] += array_sum(array_column($result['optimized_versions'], 'size'));
                    } else {
                        $results['failed']++;
                        if ($options['error_callback']) {
                            call_user_func($options['error_callback'], $imagePath, $result['errors']);
                        }
                    }

                    if ($options['progress_callback']) {
                        call_user_func($options['progress_callback'], $results['total_processed'], count($imagePaths));
                    }

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['results'][] = [
                        'original_path' => $imagePath,
                        'errors' => [$e->getMessage()]
                    ];

                    if ($options['error_callback']) {
                        call_user_func($options['error_callback'], $imagePath, [$e->getMessage()]);
                    }
                }
            }
        }

        $results['processing_time'] = microtime(true) - $startTime;

        if ($results['total_original_size'] > 0) {
            $results['average_compression'] = (($results['total_original_size'] - $results['total_optimized_size']) / $results['total_original_size']) * 100;
        }

        return $results;
    }

    /**
     * Convert image format with optimization
     */
    public function convertFormat(string $inputPath, string $outputFormat, array $options = []): ?string
    {
        $options = array_merge([
            'quality' => $this->config['quality'],
            'strip_metadata' => $this->config['strip_metadata'],
            'interlace' => $this->config['interlace']
        ], $options);

        $outputPath = $this->generateOutputPath($inputPath, $outputFormat);

        try {
            if (extension_loaded('imagick')) {
                return $this->convertWithImagick($inputPath, $outputPath, $outputFormat, $options);
            } elseif (extension_loaded('gd')) {
                return $this->convertWithGD($inputPath, $outputPath, $outputFormat, $options);
            } else {
                throw new Exception("No image processing library available (Imagick or GD required)");
            }

        } catch (Exception $e) {
            error_log("Format conversion failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resize image maintaining aspect ratio
     */
    public function resizeImage(string $inputPath, ?int $maxWidth, ?int $maxHeight, string $prefix = 'resized_'): string
    {
        $imageInfo = $this->getImageInfo($inputPath);
        $originalWidth = $imageInfo['width'];
        $originalHeight = $imageInfo['height'];

        // Calculate new dimensions
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;

        if ($maxWidth && $originalWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int)($originalHeight * ($maxWidth / $originalWidth));
        }

        if ($maxHeight && $newHeight > $maxHeight) {
            $newHeight = $maxHeight;
            $newWidth = (int)($newWidth * ($maxHeight / $newHeight));
        }

        // No resizing needed
        if ($newWidth === $originalWidth && $newHeight === $originalHeight) {
            return $inputPath;
        }

        $outputPath = $this->config['temp_directory'] . '/' . $prefix . basename($inputPath);

        try {
            if (extension_loaded('imagick')) {
                $imagick = new Imagick($inputPath);
                $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
                $imagick->writeImage($outputPath);
                $imagick->clear();
                return $outputPath;
            } elseif (extension_loaded('gd')) {
                return $this->resizeWithGD($inputPath, $outputPath, $newWidth, $newHeight);
            }

        } catch (Exception $e) {
            error_log("Image resize failed: " . $e->getMessage());
            return $inputPath; // Return original if resize fails
        }

        return $inputPath;
    }

    /**
     * Generate HTML for optimized responsive images
     */
    public function generateResponsiveImageHTML(string $imagePath, array $options = []): string
    {
        $options = array_merge([
            'alt' => '',
            'class' => '',
            'sizes' => '(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 25vw',
            'loading' => 'lazy',
            'decoding' => 'async'
        ], $options);

        // Generate responsive versions if they don't exist
        $responsiveVersions = $this->generateResponsiveImages($imagePath, $this->config['responsive_sizes']);

        if (empty($responsiveVersions)) {
            // Fallback to single image
            return "<img src=\"{$imagePath}\" alt=\"{$options['alt']}\" class=\"{$options['class']}\" loading=\"{$options['loading']}\" decoding=\"{$options['decoding']}\">";
        }

        // Build srcset
        $srcset = [];
        foreach ($responsiveVersions as $size => $version) {
            $webpPath = $version['webp'] ?? $version['original'];
            $srcset[] = "{$webpPath} {$size}w";
        }

        $srcsetString = implode(', ', $srcset);
        $fallbackSrc = reset($responsiveVersions)['webp'] ?? reset($responsiveVersions)['original'];

        return "<img src=\"{$fallbackSrc}\" srcset=\"{$srcsetString}\" sizes=\"{$options['sizes']}\" alt=\"{$options['alt']}\" class=\"{$options['class']}\" loading=\"{$options['loading']}\" decoding=\"{$options['decoding']}\">";
    }

    /**
     * Get image optimization statistics
     */
    public function getOptimizationStats(): array
    {
        $stats = $this->optimizationStats;

        if ($stats['original_size'] > 0) {
            $stats['compression_ratio'] = (($stats['original_size'] - $stats['optimized_size']) / $stats['original_size']) * 100;
        }

        $stats['average_processing_time'] = $stats['processed'] > 0 ? $stats['processing_time'] / $stats['processed'] : 0;

        return $stats;
    }

    /**
     * Clean up temporary files
     */
    public function cleanupTempFiles(int $maxAge = 3600): int
    {
        $cleaned = 0;
        $cutoffTime = time() - $maxAge;

        if (is_dir($this->config['temp_directory'])) {
            $files = glob($this->config['temp_directory'] . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }

    /**
     * Validate image file
     */
    private function validateImage(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $mimeType = mime_content_type($path);
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Get image information
     */
    private function getImageInfo(string $path): array
    {
        $info = getimagesize($path);

        return [
            'width' => $info[0] ?? 0,
            'height' => $info[1] ?? 0,
            'mime' => $info['mime'] ?? '',
            'size' => filesize($path)
        ];
    }

    /**
     * Generate output path for converted image
     */
    private function generateOutputPath(string $inputPath, string $format): string
    {
        $pathInfo = pathinfo($inputPath);
        $outputName = $pathInfo['filename'] . '_optimized.' . $format;

        return $this->config['temp_directory'] . '/' . $outputName;
    }

    /**
     * Convert image using Imagick
     */
    private function convertWithImagick(string $inputPath, string $outputPath, string $format, array $options): ?string
    {
        $imagick = new Imagick($inputPath);

        // Set format
        $imagick->setImageFormat($format);

        // Set quality
        if (in_array($format, ['jpeg', 'jpg', 'webp'])) {
            $imagick->setImageCompressionQuality($options['quality']);
        }

        // Strip metadata
        if ($options['strip_metadata']) {
            $imagick->stripImage();
        }

        // Set interlace
        if ($options['interlace']) {
            $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        }

        // Optimize colors
        if ($options['optimize_colors']) {
            $imagick->quantizeImage(256, Imagick::COLORSPACE_RGB, 0, false, false);
        }

        // Write optimized image
        $imagick->writeImage($outputPath);
        $imagick->clear();

        return $outputPath;
    }

    /**
     * Convert image using GD
     */
    private function convertWithGD(string $inputPath, string $outputPath, string $format, array $options): ?string
    {
        $imageInfo = $this->getImageInfo($inputPath);
        $image = $this->createGDImage($inputPath, $imageInfo['mime']);

        if (!$image) {
            return null;
        }

        // Convert and save based on format
        switch ($format) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($image, $outputPath, $options['quality']);
                break;
            case 'png':
                imagepng($image, $outputPath, 9); // Maximum compression
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($image, $outputPath, $options['quality']);
                } else {
                    return null; // WebP not supported
                }
                break;
            default:
                return null;
        }

        imagedestroy($image);
        return $outputPath;
    }

    /**
     * Create GD image from file
     */
    private function createGDImage(string $path, string $mimeType): ?GdImage
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    return imagecreatefromwebp($path);
                }
                break;
        }

        return null;
    }

    /**
     * Resize image using GD
     */
    private function resizeWithGD(string $inputPath, string $outputPath, int $width, int $height): string
    {
        $imageInfo = $this->getImageInfo($inputPath);
        $sourceImage = $this->createGDImage($inputPath, $imageInfo['mime']);

        if (!$sourceImage) {
            return $inputPath;
        }

        $resizedImage = imagecreatetruecolor($width, $height);
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $width, $height, $imageInfo['width'], $imageInfo['height']);

        // Save resized image
        $extension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($resizedImage, $outputPath, 90);
                break;
            case 'png':
                imagepng($resizedImage, $outputPath, 9);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($resizedImage, $outputPath, 90);
                }
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        return $outputPath;
    }

    /**
     * Calculate compression ratio
     */
    private function calculateCompressionRatio(int $originalSize, int $optimizedSize): float
    {
        if ($originalSize === 0) {
            return 0;
        }

        return (($originalSize - $optimizedSize) / $originalSize) * 100;
    }

    /**
     * Update global optimization statistics
     */
    private function updateOptimizationStats(array $result): void
    {
        $this->optimizationStats['processed']++;
        $this->optimizationStats['original_size'] += $result['original_size'];
        $this->optimizationStats['optimized_size'] += array_sum(array_column($result['optimized_versions'], 'size'));
        $this->optimizationStats['processing_time'] += $result['processing_time'];

        if ($this->optimizationStats['original_size'] > 0) {
            $this->optimizationStats['compression_ratio'] = (($this->optimizationStats['original_size'] - $this->optimizationStats['optimized_size']) / $this->optimizationStats['original_size']) * 100;
        }
    }

    /**
     * Get supported image formats
     */
    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }

    /**
     * Check if image format is supported
     */
    public function isFormatSupported(string $format): bool
    {
        return in_array(strtolower($format), $this->supportedFormats);
    }

    /**
     * Get optimization recommendations
     */
    public function getOptimizationRecommendations(): array
    {
        return [
            'webp_conversion' => [
                'recommendation' => 'Convert images to WebP format for better compression',
                'impact' => 'high',
                'savings' => '25-35% reduction in file size'
            ],
            'responsive_images' => [
                'recommendation' => 'Generate responsive image sizes for different devices',
                'impact' => 'medium',
                'savings' => 'Reduced bandwidth for mobile users'
            ],
            'quality_optimization' => [
                'recommendation' => 'Optimize JPEG quality to 80-85% for best size/quality balance',
                'impact' => 'medium',
                'savings' => '20-30% reduction in file size'
            ],
            'metadata_stripping' => [
                'recommendation' => 'Remove EXIF metadata to reduce file size',
                'impact' => 'low',
                'savings' => '5-15% reduction in file size'
            ]
        ];
    }
}
