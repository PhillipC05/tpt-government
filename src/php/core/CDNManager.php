<?php

namespace TPT\GovPlatform\Core;

use Exception;

/**
 * CDN Manager for Static Asset Delivery and Optimization
 *
 * This class provides comprehensive CDN management including:
 * - Multi-CDN support (Cloudflare, AWS CloudFront, Fastly, etc.)
 * - Automatic asset optimization and compression
 * - Cache invalidation and purging
 * - Real-time performance monitoring
 * - Geographic load balancing
 * - SSL/TLS certificate management
 */
class CDNManager
{
    private array $config;
    private array $cdnProviders = [];
    private array $performanceMetrics = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'primary_cdn' => 'cloudflare',
            'fallback_cdn' => null,
            'cache_ttl' => 86400, // 24 hours
            'compression_enabled' => true,
            'webp_conversion' => true,
            'lazy_loading' => true,
            'preload_critical' => true,
            'monitoring_enabled' => true,
            'auto_purge' => true,
            'purge_on_deploy' => true
        ], $config);

        $this->initializeCDNProviders();
        $this->createCDNTables();
    }

    /**
     * Initialize supported CDN providers
     */
    private function initializeCDNProviders(): void
    {
        $this->cdnProviders = [
            'cloudflare' => [
                'name' => 'Cloudflare',
                'api_endpoint' => 'https://api.cloudflare.com/client/v4',
                'supported_features' => ['purge', 'analytics', 'waf', 'ssl'],
                'global_network' => true,
                'pricing_model' => 'tiered'
            ],
            'cloudfront' => [
                'name' => 'AWS CloudFront',
                'api_endpoint' => 'https://cloudfront.amazonaws.com/2020-05-31',
                'supported_features' => ['purge', 'analytics', 'lambda@edge', 'ssl'],
                'global_network' => true,
                'pricing_model' => 'pay_per_use'
            ],
            'fastly' => [
                'name' => 'Fastly',
                'api_endpoint' => 'https://api.fastly.com',
                'supported_features' => ['purge', 'analytics', 'edge_compute', 'ssl'],
                'global_network' => true,
                'pricing_model' => 'bandwidth_based'
            ],
            'akamai' => [
                'name' => 'Akamai',
                'api_endpoint' => 'https://api.ccu.akamai.com',
                'supported_features' => ['purge', 'analytics', 'waf', 'ssl'],
                'global_network' => true,
                'pricing_model' => 'enterprise'
            ],
            'bunny' => [
                'name' => 'Bunny.net',
                'api_endpoint' => 'https://api.bunny.net',
                'supported_features' => ['purge', 'analytics', 'optimization'],
                'global_network' => true,
                'pricing_model' => 'bandwidth_based'
            ]
        ];
    }

    /**
     * Create CDN monitoring tables
     */
    private function createCDNTables(): void
    {
        // This would create tables for CDN performance tracking
        // Implementation would include tables for:
        // - cdn_performance_metrics
        // - cdn_asset_analytics
        // - cdn_purge_history
        // - cdn_ssl_certificates
    }

    /**
     * Get optimized asset URL with CDN
     */
    public function getAssetUrl(string $assetPath, array $options = []): string
    {
        $options = array_merge([
            'optimize' => true,
            'webp' => $this->config['webp_conversion'],
            'quality' => 85,
            'lazy' => $this->config['lazy_loading']
        ], $options);

        $cdnUrl = $this->getCDNBaseUrl();
        $optimizedPath = $this->optimizeAssetPath($assetPath, $options);

        return $cdnUrl . $optimizedPath;
    }

    /**
     * Generate responsive image srcset with CDN optimization
     */
    public function generateResponsiveImage(string $imagePath, array $sizes = []): string
    {
        if (empty($sizes)) {
            $sizes = [320, 640, 1024, 1920];
        }

        $srcset = [];
        $cdnUrl = $this->getCDNBaseUrl();

        foreach ($sizes as $size) {
            $optimizedPath = $this->optimizeImagePath($imagePath, [
                'width' => $size,
                'quality' => 85,
                'webp' => true
            ]);

            $srcset[] = "{$cdnUrl}{$optimizedPath} {$size}w";
        }

        return implode(', ', $srcset);
    }

    /**
     * Preload critical assets
     */
    public function generatePreloadTags(array $criticalAssets = []): string
    {
        if (empty($criticalAssets)) {
            $criticalAssets = $this->getDefaultCriticalAssets();
        }

        $preloadTags = [];

        foreach ($criticalAssets as $asset) {
            $url = $this->getAssetUrl($asset['path']);
            $as = $this->getAssetType($asset['path']);
            $crossorigin = $this->needsCrossorigin($asset['path']) ? ' crossorigin' : '';

            $preloadTags[] = "<link rel=\"preload\" href=\"{$url}\" as=\"{$as}\"{$crossorigin}>";
        }

        return implode("\n", $preloadTags);
    }

    /**
     * Purge CDN cache for specific assets or patterns
     */
    public function purgeCache(array $assets = [], string $pattern = null): array
    {
        $results = [
            'primary_cdn' => null,
            'fallback_cdn' => null,
            'errors' => []
        ];

        try {
            // Purge from primary CDN
            $results['primary_cdn'] = $this->purgeFromCDN($this->config['primary_cdn'], $assets, $pattern);

            // Purge from fallback CDN if configured
            if ($this->config['fallback_cdn']) {
                $results['fallback_cdn'] = $this->purgeFromCDN($this->config['fallback_cdn'], $assets, $pattern);
            }

        } catch (Exception $e) {
            $results['errors'][] = "CDN purge failed: " . $e->getMessage();
        }

        // Log purge operation
        $this->logPurgeOperation($assets, $pattern, $results);

        return $results;
    }

    /**
     * Get CDN performance analytics
     */
    public function getPerformanceAnalytics(string $timeframe = '24h'): array
    {
        $analytics = [
            'cache_hit_rate' => 0,
            'bandwidth_savings' => 0,
            'response_times' => [],
            'top_assets' => [],
            'geographic_distribution' => [],
            'error_rates' => []
        ];

        // This would query CDN provider APIs for real analytics
        // Implementation would vary by CDN provider

        return $analytics;
    }

    /**
     * Optimize and compress static assets
     */
    public function optimizeAssets(array $assetGroups = []): array
    {
        $results = [
            'optimized' => 0,
            'original_size' => 0,
            'optimized_size' => 0,
            'compression_ratio' => 0,
            'errors' => []
        ];

        if (empty($assetGroups)) {
            $assetGroups = $this->getDefaultAssetGroups();
        }

        foreach ($assetGroups as $group) {
            try {
                $optimizedAssets = $this->optimizeAssetGroup($group);
                $results['optimized'] += count($optimizedAssets);

                foreach ($optimizedAssets as $asset) {
                    $results['original_size'] += $asset['original_size'];
                    $results['optimized_size'] += $asset['optimized_size'];
                }

            } catch (Exception $e) {
                $results['errors'][] = "Asset optimization failed for group {$group['name']}: " . $e->getMessage();
            }
        }

        if ($results['original_size'] > 0) {
            $results['compression_ratio'] = (($results['original_size'] - $results['optimized_size']) / $results['original_size']) * 100;
        }

        return $results;
    }

    /**
     * Set up CDN for a domain with SSL
     */
    public function setupCDNDomain(string $domain, array $sslConfig = []): array
    {
        $sslConfig = array_merge([
            'auto_ssl' => true,
            'ssl_type' => 'lets_encrypt',
            'force_ssl' => true,
            'hsts' => true
        ], $sslConfig);

        $setupResults = [
            'domain' => $domain,
            'cdn_provider' => $this->config['primary_cdn'],
            'ssl_configured' => false,
            'dns_configured' => false,
            'errors' => []
        ];

        try {
            // Configure SSL certificate
            if ($sslConfig['auto_ssl']) {
                $sslResult = $this->configureSSL($domain, $sslConfig);
                $setupResults['ssl_configured'] = $sslResult['success'];
                if (!$sslResult['success']) {
                    $setupResults['errors'][] = $sslResult['error'];
                }
            }

            // Configure DNS records
            $dnsResult = $this->configureDNS($domain);
            $setupResults['dns_configured'] = $dnsResult['success'];
            if (!$dnsResult['success']) {
                $setupResults['errors'][] = $dnsResult['error'];
            }

            // Set up CDN rules and optimizations
            $this->configureCDNRules($domain);

        } catch (Exception $e) {
            $setupResults['errors'][] = "CDN setup failed: " . $e->getMessage();
        }

        return $setupResults;
    }

    /**
     * Monitor CDN health and performance
     */
    public function monitorCDNHealth(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'primary_cdn' => $this->checkCDNHealth($this->config['primary_cdn']),
            'fallback_cdn' => null,
            'performance_metrics' => $this->getPerformanceMetrics(),
            'alerts' => [],
            'last_checked' => date('Y-m-d H:i:s')
        ];

        // Check fallback CDN if configured
        if ($this->config['fallback_cdn']) {
            $health['fallback_cdn'] = $this->checkCDNHealth($this->config['fallback_cdn']);
        }

        // Determine overall status
        if ($health['primary_cdn']['status'] !== 'healthy') {
            $health['overall_status'] = 'degraded';
        }

        // Generate alerts based on metrics
        $health['alerts'] = $this->generateCDNAlerts($health);

        return $health;
    }

    /**
     * Generate HTML for lazy loading images
     */
    public function generateLazyImage(string $imagePath, array $options = []): string
    {
        $options = array_merge([
            'alt' => '',
            'class' => '',
            'sizes' => null,
            'srcset' => null,
            'loading' => 'lazy',
            'decoding' => 'async'
        ], $options);

        $cdnUrl = $this->getAssetUrl($imagePath, ['lazy' => true]);
        $attributes = [];

        // Basic attributes
        $attributes[] = "src=\"{$cdnUrl}\"";
        $attributes[] = "alt=\"{$options['alt']}\"";
        $attributes[] = "loading=\"{$options['loading']}\"";
        $attributes[] = "decoding=\"{$options['decoding']}\"";

        // Optional attributes
        if (!empty($options['class'])) {
            $attributes[] = "class=\"{$options['class']}\"";
        }

        if ($options['sizes']) {
            $attributes[] = "sizes=\"{$options['sizes']}\"";
        }

        if ($options['srcset']) {
            $attributes[] = "srcset=\"{$options['srcset']}\"";
        }

        return "<img " . implode(' ', $attributes) . ">";
    }

    /**
     * Get CDN base URL
     */
    private function getCDNBaseUrl(): string
    {
        // This would return the actual CDN URL based on configuration
        // For now, return a placeholder
        return "https://cdn.tpt-gov.com/";
    }

    /**
     * Optimize asset path for CDN delivery
     */
    private function optimizeAssetPath(string $path, array $options): string
    {
        $optimizedPath = $path;

        // Add optimization parameters
        if ($options['optimize']) {
            $params = [];

            if (isset($options['quality'])) {
                $params[] = "q={$options['quality']}";
            }

            if ($options['webp']) {
                $params[] = "f=webp";
            }

            if (!empty($params)) {
                $optimizedPath .= '?' . implode('&', $params);
            }
        }

        return $optimizedPath;
    }

    /**
     * Optimize image path with responsive parameters
     */
    private function optimizeImagePath(string $path, array $options): string
    {
        $params = [];

        if (isset($options['width'])) {
            $params[] = "w={$options['width']}";
        }

        if (isset($options['height'])) {
            $params[] = "h={$options['height']}";
        }

        if (isset($options['quality'])) {
            $params[] = "q={$options['quality']}";
        }

        if ($options['webp']) {
            $params[] = "f=webp";
        }

        return $path . '?' . implode('&', $params);
    }

    /**
     * Get default critical assets for preloading
     */
    private function getDefaultCriticalAssets(): array
    {
        return [
            ['path' => '/css/main.css', 'type' => 'style'],
            ['path' => '/js/app.js', 'type' => 'script'],
            ['path' => '/images/logo.svg', 'type' => 'image'],
            ['path' => '/fonts/main.woff2', 'type' => 'font']
        ];
    }

    /**
     * Get asset type for preload
     */
    private function getAssetType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match($extension) {
            'css' => 'style',
            'js' => 'script',
            'woff', 'woff2', 'ttf', 'eot' => 'font',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => 'image',
            default => 'fetch'
        };
    }

    /**
     * Check if asset needs crossorigin attribute
     */
    private function needsCrossorigin(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, ['woff', 'woff2', 'ttf', 'eot']);
    }

    /**
     * Purge cache from specific CDN provider
     */
    private function purgeFromCDN(string $provider, array $assets, ?string $pattern): array
    {
        // Implementation would vary by CDN provider
        // This is a placeholder for the actual implementation
        return [
            'provider' => $provider,
            'assets_purged' => count($assets),
            'pattern_purged' => $pattern ? true : false,
            'status' => 'success'
        ];
    }

    /**
     * Log purge operation
     */
    private function logPurgeOperation(array $assets, ?string $pattern, array $results): void
    {
        // Implementation would log to database
        error_log("CDN purge operation: " . json_encode([
            'assets' => $assets,
            'pattern' => $pattern,
            'results' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    /**
     * Get default asset groups for optimization
     */
    private function getDefaultAssetGroups(): array
    {
        return [
            [
                'name' => 'css',
                'pattern' => '/css/*.css',
                'optimization' => 'minify'
            ],
            [
                'name' => 'js',
                'pattern' => '/js/*.js',
                'optimization' => 'minify'
            ],
            [
                'name' => 'images',
                'pattern' => '/images/*.{jpg,jpeg,png,gif}',
                'optimization' => 'compress'
            ]
        ];
    }

    /**
     * Optimize asset group
     */
    private function optimizeAssetGroup(array $group): array
    {
        // Implementation would optimize assets based on group configuration
        // This is a placeholder
        return [];
    }

    /**
     * Configure SSL for domain
     */
    private function configureSSL(string $domain, array $config): array
    {
        // Implementation would configure SSL certificate
        return ['success' => true];
    }

    /**
     * Configure DNS for CDN
     */
    private function configureDNS(string $domain): array
    {
        // Implementation would configure DNS records
        return ['success' => true];
    }

    /**
     * Configure CDN rules
     */
    private function configureCDNRules(string $domain): void
    {
        // Implementation would set up CDN rules and optimizations
    }

    /**
     * Check CDN health
     */
    private function checkCDNHealth(string $provider): array
    {
        // Implementation would check CDN provider health
        return ['status' => 'healthy', 'response_time' => 50];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        // Implementation would get real performance metrics
        return ['cache_hit_rate' => 95.5, 'avg_response_time' => 45];
    }

    /**
     * Generate CDN alerts
     */
    private function generateCDNAlerts(array $health): array
    {
        $alerts = [];

        if ($health['primary_cdn']['status'] !== 'healthy') {
            $alerts[] = [
                'level' => 'critical',
                'message' => 'Primary CDN is not healthy',
                'provider' => $this->config['primary_cdn']
            ];
        }

        return $alerts;
    }

    /**
     * Get supported CDN providers
     */
    public function getSupportedProviders(): array
    {
        return $this->cdnProviders;
    }

    /**
     * Get CDN configuration recommendations
     */
    public function getOptimizationRecommendations(): array
    {
        return [
            'enable_compression' => [
                'recommendation' => 'Enable gzip/brotli compression for text-based assets',
                'impact' => 'high',
                'savings' => '60-80% reduction in file size'
            ],
            'webp_conversion' => [
                'recommendation' => 'Convert images to WebP format',
                'impact' => 'high',
                'savings' => '25-35% reduction in image size'
            ],
            'browser_caching' => [
                'recommendation' => 'Set appropriate cache headers for static assets',
                'impact' => 'medium',
                'savings' => 'Reduced server load and faster page loads'
            ],
            'cdn_edge_locations' => [
                'recommendation' => 'Use CDN with global edge locations',
                'impact' => 'high',
                'savings' => '50-80% improvement in global response times'
            ]
        ];
    }
}
