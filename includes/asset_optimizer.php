<?php
/**
 * Asset Optimization and Bundling System
 * Minifies and combines CSS/JS files for better performance
 */

class AssetOptimizer {
    private $base_path;
    private $cache_path;
    private $version;
    
    public function __construct($base_path = '..') {
        $this->base_path = $base_path;
        $this->cache_path = $base_path . '/cache/assets/';
        $this->version = date('YmdHis'); // Version based on current time
        $this->ensureDirectories();
    }
    
    private function ensureDirectories() {
        if (!file_exists($this->cache_path)) {
            mkdir($this->cache_path, 0755, true);
        }
    }
    
    /**
     * Advanced CSS Minification
     */
    public function minifyCSS($css) {
        // Remove CSS comments (/* ... */)
        $css = preg_replace('/\/\*.*?\*\//', '', $css);
        
        // Remove unnecessary whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove spaces around specific characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove trailing semicolon before closing brace
        $css = preg_replace('/;+}/', '}', $css);
        
        // Remove empty rules
        $css = preg_replace('/[^{}]*\{\s*\}/', '', $css);
        
        // Optimize hex colors (#ffffff -> #fff)
        $css = preg_replace('/#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3/i', '#$1$2$3', $css);
        
        // Remove unnecessary quotes from URLs
        $css = preg_replace('/url\((["\'])([^"\']*)\1\)/', 'url($2)', $css);
        
        // Remove unnecessary units from zero values
        $css = preg_replace('/\b0(?:px|em|ex|%|in|cm|mm|pt|pc)\b/', '0', $css);
        
        return trim($css);
    }
    
    /**
     * Advanced JavaScript Minification
     */
    public function minifyJS($js) {
        // Remove single-line comments (but preserve URLs)
        $js = preg_replace('/(?<!:)\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        
        // Remove extra whitespace and line breaks
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove spaces around operators and punctuation
        $js = preg_replace('/\s*([=+\-*\/{}();,<>!&|?:])\s*/', '$1', $js);
        
        // Remove unnecessary semicolons
        $js = preg_replace('/;+/', ';', $js);
        
        return trim($js);
    }
    
    /**
     * Extract and combine CSS from multiple sources
     */
    public function bundleCSS($sources = []) {
        $combined_css = "/* Optimized CSS Bundle - Generated " . date('Y-m-d H:i:s') . " */\n";
        
        // Default sources
        if (empty($sources)) {
            $sources = [
                $this->base_path . '/layouts/header.php',
                $this->base_path . '/HRMS/index.php',
                $this->base_path . '/HRMS/ui_ux_enhancements.php'
            ];
        }
        
        foreach ($sources as $source_file) {
            if (file_exists($source_file)) {
                $content = file_get_contents($source_file);
                
                // Extract CSS from <style> tags
                preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $content, $matches);
                
                foreach ($matches[1] as $css_block) {
                    $combined_css .= $this->minifyCSS($css_block) . "\n";
                }
            }
        }
        
        // Add performance-optimized CSS
        $combined_css .= $this->getPerformanceCSS();
        
        return $combined_css;
    }
    
    /**
     * Extract and combine JavaScript from multiple sources
     */
    public function bundleJS($sources = []) {
        $combined_js = "/* Optimized JS Bundle - Generated " . date('Y-m-d H:i:s') . " */\n";
        
        // Default sources
        if (empty($sources)) {
            $sources = [
                $this->base_path . '/HRMS/index.php',
                $this->base_path . '/HRMS/ui_ux_enhancements.php'
            ];
        }
        
        foreach ($sources as $source_file) {
            if (file_exists($source_file)) {
                $content = file_get_contents($source_file);
                
                // Extract JavaScript from <script> tags
                preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $content, $matches);
                
                foreach ($matches[1] as $js_block) {
                    // Skip empty blocks and external scripts
                    if (trim($js_block) && !preg_match('/src\s*=/i', $js_block)) {
                        $combined_js .= $this->minifyJS($js_block) . "\n";
                    }
                }
            }
        }
        
        // Add performance-optimized JavaScript
        $combined_js .= $this->getPerformanceJS();
        
        return $combined_js;
    }
    
    /**
     * Generate optimized CSS for performance
     */
    private function getPerformanceCSS() {
        return $this->minifyCSS("
            /* Performance Optimizations */
            .perf-optimized { will-change: transform; }
            .gpu-accelerated { transform: translateZ(0); backface-visibility: hidden; }
            .no-select { user-select: none; -webkit-touch-callout: none; }
            .preload { opacity: 0; transition: opacity 0.3s ease; }
            .loaded { opacity: 1; }
            
            /* Critical Above-the-fold Styles */
            .critical-content { display: block; }
            .non-critical { display: none; }
            .non-critical.loaded { display: block; }
            
            /* Loading Optimizations */
            .lazy-load { opacity: 0; transform: translateY(20px); transition: all 0.3s ease; }
            .lazy-load.visible { opacity: 1; transform: translateY(0); }
            
            /* Hardware Acceleration for Animations */
            .animate { will-change: transform, opacity; }
            .animate:hover { transform: translateZ(0); }
            
            /* Memory-efficient Gradients */
            .gradient-optimized { background-image: linear-gradient(135deg, var(--start-color), var(--end-color)); }
            
            /* Print Optimization */
            @media print {
                .no-print { display: none !important; }
                .print-only { display: block !important; }
            }
            
            /* Reduced Motion Support */
            @media (prefers-reduced-motion: reduce) {
                *, *::before, *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }
        ");
    }
    
    /**
     * Generate optimized JavaScript for performance
     */
    private function getPerformanceJS() {
        return $this->minifyJS("
            // Performance Monitoring and Optimization
            (function() {
                'use strict';
                
                // Intersection Observer for Lazy Loading
                if ('IntersectionObserver' in window) {
                    const lazyElements = document.querySelectorAll('.lazy-load');
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('visible');
                                observer.unobserve(entry.target);
                            }
                        });
                    });
                    
                    lazyElements.forEach(el => observer.observe(el));
                }
                
                // Critical Resource Preloading
                function preloadCriticalResources() {
                    const criticalCSS = document.createElement('link');
                    criticalCSS.rel = 'preload';
                    criticalCSS.as = 'style';
                    criticalCSS.href = 'cache/assets/optimized.css';
                    document.head.appendChild(criticalCSS);
                }
                
                // Memory Cleanup
                function optimizeMemory() {
                    // Remove event listeners from hidden elements
                    document.querySelectorAll('[style*=\"display: none\"]').forEach(el => {
                        el.replaceWith(el.cloneNode(true));
                    });
                }
                
                // Performance Monitoring
                function trackPerformance() {
                    if ('performance' in window && 'getEntriesByType' in performance) {
                        const navigation = performance.getEntriesByType('navigation')[0];
                        const loadTime = navigation.loadEventEnd - navigation.loadEventStart;
                        
                        // Send performance data to console for monitoring
                        console.log('Performance Metrics:', {
                            loadTime: loadTime + 'ms',
                            domContentLoaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart + 'ms',
                            firstPaint: performance.getEntriesByType('paint')[0]?.startTime + 'ms'
                        });
                    }
                }
                
                // Initialize optimizations
                document.addEventListener('DOMContentLoaded', function() {
                    preloadCriticalResources();
                    trackPerformance();
                    
                    // Cleanup memory after 30 seconds
                    setTimeout(optimizeMemory, 30000);
                });
                
                // Page Visibility API for performance
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden) {
                        // Pause non-critical operations
                        console.log('Page hidden - pausing non-critical operations');
                    } else {
                        // Resume operations
                        console.log('Page visible - resuming operations');
                    }
                });
                
            })();
        ");
    }
    
    /**
     * Generate optimized bundles and save to cache
     */
    public function generateBundles() {
        $results = [];
        
        try {
            // Generate CSS bundle
            $css_bundle = $this->bundleCSS();
            $css_file = $this->cache_path . 'optimized.css';
            $css_size_before = strlen($css_bundle);
            
            file_put_contents($css_file, $css_bundle);
            $css_size_after = filesize($css_file);
            
            $results['css'] = [
                'file' => $css_file,
                'size_before' => $this->formatBytes($css_size_before),
                'size_after' => $this->formatBytes($css_size_after),
                'compression' => round((($css_size_before - $css_size_after) / $css_size_before) * 100, 1) . '%'
            ];
            
            // Generate JS bundle
            $js_bundle = $this->bundleJS();
            $js_file = $this->cache_path . 'optimized.js';
            $js_size_before = strlen($js_bundle);
            
            file_put_contents($js_file, $js_bundle);
            $js_size_after = filesize($js_file);
            
            $results['js'] = [
                'file' => $js_file,
                'size_before' => $this->formatBytes($js_size_before),
                'size_after' => $this->formatBytes($js_size_after),
                'compression' => round((($js_size_before - $js_size_after) / $js_size_before) * 100, 1) . '%'
            ];
            
            // Generate version manifest
            $manifest = [
                'version' => $this->version,
                'generated' => date('Y-m-d H:i:s'),
                'files' => [
                    'css' => 'optimized.css',
                    'js' => 'optimized.js'
                ]
            ];
            
            file_put_contents($this->cache_path . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            
            $results['manifest'] = $manifest;
            
        } catch (Exception $e) {
            $results['error'] = 'Bundle generation failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        $stats = [
            'cache_dir' => $this->cache_path,
            'is_writable' => is_writable($this->cache_path),
            'total_size' => 0,
            'file_count' => 0,
            'files' => []
        ];
        
        if (is_dir($this->cache_path)) {
            $files = glob($this->cache_path . '*');
            $stats['file_count'] = count($files);
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size = filesize($file);
                    $stats['total_size'] += $size;
                    $stats['files'][] = [
                        'name' => basename($file),
                        'size' => $this->formatBytes($size),
                        'modified' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
            }
        }
        
        $stats['total_size'] = $this->formatBytes($stats['total_size']);
        
        return $stats;
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . $units[$i];
    }
}

// Usage example when called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: application/json');
    
    $optimizer = new AssetOptimizer();
    
    $action = $_GET['action'] ?? 'stats';
    
    switch ($action) {
        case 'generate':
            echo json_encode($optimizer->generateBundles());
            break;
        case 'stats':
            echo json_encode($optimizer->getCacheStats());
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>
