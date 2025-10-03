<?php

namespace App\Services;

use App\Services\MikroTikService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class NetworkService
{
    protected $mikrotik;

    public function __construct(MikroTikService $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    /**
     * Monitor network health and performance
     */
    public function getNetworkHealth()
    {
        return Cache::remember('network_health', 300, function () {
            try {
                $mikrotikHealth = $this->getMikroTikHealth();
                $internetConnectivity = $this->checkInternetConnectivity();
                $interfaceStats = $this->getInterfaceStatistics();
                $bandwidthUsage = $this->getBandwidthUsage();

                $overallHealth = $this->calculateOverallHealth([
                    'mikrotik' => $mikrotikHealth['status'],
                    'internet' => $internetConnectivity['status'],
                    'interfaces' => $interfaceStats['status'],
                ]);

                return [
                    'overall_health' => $overallHealth,
                    'mikrotik' => $mikrotikHealth,
                    'internet_connectivity' => $internetConnectivity,
                    'interfaces' => $interfaceStats,
                    'bandwidth_usage' => $bandwidthUsage,
                    'last_updated' => now(),
                ];

            } catch (\Exception $e) {
                Log::error('Network health check failed', [
                    'error' => $e->getMessage()
                ]);

                return [
                    'overall_health' => 'critical',
                    'error' => $e->getMessage(),
                    'last_updated' => now(),
                ];
            }
        });
    }

    /**
     * Check MikroTik router health
     */
    public function getMikroTikHealth()
    {
        try {
            $connectionStatus = $this->mikrotik->getConnectionStatus();

            if (!$connectionStatus['connected']) {
                return [
                    'status' => 'critical',
                    'message' => 'MikroTik router is not accessible',
                    'details' => $connectionStatus,
                ];
            }

            $systemResources = $connectionStatus['router_info'];

            $cpuLoad = $systemResources['cpu_load'] ?? 0;
            $memoryUsage = $systemResources['free_memory'] && $systemResources['total_memory']
                ? (($systemResources['total_memory'] - $systemResources['free_memory']) / $systemResources['total_memory']) * 100
                : 0;

            $diskUsage = $systemResources['free_hdd_space'] && $systemResources['total_hdd_space']
                ? (($systemResources['total_hdd_space'] - $systemResources['free_hdd_space']) / $systemResources['total_hdd_space']) * 100
                : 0;

            $status = 'healthy';
            $warnings = [];

            if ($cpuLoad > 80) {
                $status = 'warning';
                $warnings[] = 'High CPU usage';
            } elseif ($cpuLoad > 95) {
                $status = 'critical';
                $warnings[] = 'Critical CPU usage';
            }

            if ($memoryUsage > 85) {
                $status = 'warning';
                $warnings[] = 'High memory usage';
            } elseif ($memoryUsage > 95) {
                $status = 'critical';
                $warnings[] = 'Critical memory usage';
            }

            if ($diskUsage > 90) {
                $status = 'warning';
                $warnings[] = 'High disk usage';
            } elseif ($diskUsage > 95) {
                $status = 'critical';
                $warnings[] = 'Critical disk usage';
            }

            return [
                'status' => $status,
                'message' => empty($warnings) ? 'Router is healthy' : implode(', ', $warnings),
                'metrics' => [
                    'cpu_load' => $cpuLoad,
                    'memory_usage' => round($memoryUsage, 2),
                    'disk_usage' => round($diskUsage, 2),
                    'uptime' => $systemResources['uptime'] ?? 'Unknown',
                    'version' => $systemResources['version'] ?? 'Unknown',
                ],
                'raw_data' => $systemResources,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Failed to check MikroTik health: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check internet connectivity
     */
    public function checkInternetConnectivity()
    {
        $testHosts = [
            'google.com' => 'https://www.google.com',
            'cloudflare.com' => 'https://1.1.1.1',
            'opendns.com' => 'https://208.67.222.222',
        ];

        $results = [];
        $successCount = 0;

        foreach ($testHosts as $name => $url) {
            try {
                $start = microtime(true);
                $response = Http::timeout(10)->get($url);
                $responseTime = round((microtime(true) - $start) * 1000, 2);

                $success = $response->successful();
                if ($success) $successCount++;

                $results[$name] = [
                    'success' => $success,
                    'response_time_ms' => $responseTime,
                    'status_code' => $response->status(),
                ];

            } catch (\Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $connectivity = $successCount / count($testHosts);
        $status = match (true) {
            $connectivity >= 0.8 => 'healthy',
            $connectivity >= 0.5 => 'warning',
            default => 'critical'
        };

        return [
            'status' => $status,
            'connectivity_percentage' => round($connectivity * 100, 2),
            'successful_tests' => $successCount,
            'total_tests' => count($testHosts),
            'test_results' => $results,
        ];
    }

    /**
     * Get interface statistics
     */
    public function getInterfaceStatistics()
    {
        try {
            $interfaces = $this->mikrotik->getInterfaceStats();

            $healthyCount = 0;
            $totalCount = count($interfaces);
            $interfaceDetails = [];

            foreach ($interfaces as $interface) {
                $isHealthy = !$interface['disabled'] && $interface['running'];
                if ($isHealthy) $healthyCount++;

                $interfaceDetails[] = [
                    'name' => $interface['name'],
                    'type' => $interface['type'],
                    'status' => $isHealthy ? 'up' : 'down',
                    'rx_bytes' => $interface['rx_byte'],
                    'tx_bytes' => $interface['tx_byte'],
                    'rx_errors' => $interface['rx_error'],
                    'tx_errors' => $interface['tx_error'],
                    'rx_drops' => $interface['rx_drop'],
                    'tx_drops' => $interface['tx_drop'],
                ];
            }

            $healthPercentage = $totalCount > 0 ? ($healthyCount / $totalCount) * 100 : 100;

            $status = match (true) {
                $healthPercentage >= 90 => 'healthy',
                $healthPercentage >= 70 => 'warning',
                default => 'critical'
            };

            return [
                'status' => $status,
                'health_percentage' => round($healthPercentage, 2),
                'healthy_interfaces' => $healthyCount,
                'total_interfaces' => $totalCount,
                'interfaces' => $interfaceDetails,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get current bandwidth usage
     */
    public function getBandwidthUsage()
    {
        try {
            $activeSessions = $this->mikrotik->getActiveSessions();

            $totalBytesIn = 0;
            $totalBytesOut = 0;
            $sessionCount = count($activeSessions);

            foreach ($activeSessions as $session) {
                $totalBytesIn += $session['bytes_in'];
                $totalBytesOut += $session['bytes_out'];
            }

            $totalBytes = $totalBytesIn + $totalBytesOut;
            $totalMB = round($totalBytes / (1024 * 1024), 2);
            $totalGB = round($totalBytes / (1024 * 1024 * 1024), 2);

            return [
                'active_sessions' => $sessionCount,
                'total_bytes' => $totalBytes,
                'total_mb' => $totalMB,
                'total_gb' => $totalGB,
                'bytes_in' => $totalBytesIn,
                'bytes_out' => $totalBytesOut,
                'upload_mb' => round($totalBytesIn / (1024 * 1024), 2),
                'download_mb' => round($totalBytesOut / (1024 * 1024), 2),
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'active_sessions' => 0,
                'total_bytes' => 0,
            ];
        }
    }

    /**
     * Monitor bandwidth trends
     */
    public function getBandwidthTrends($hours = 24)
    {
        return Cache::remember("bandwidth_trends_{$hours}h", 300, function () use ($hours) {
            // This would typically require historical data collection
            // For now, return current usage as a trend point
            $currentUsage = $this->getBandwidthUsage();

            return [
                'period_hours' => $hours,
                'data_points' => [
                    [
                        'timestamp' => now(),
                        'bytes_in' => $currentUsage['bytes_in'] ?? 0,
                        'bytes_out' => $currentUsage['bytes_out'] ?? 0,
                        'active_sessions' => $currentUsage['active_sessions'] ?? 0,
                    ]
                ],
                'peak_usage' => $currentUsage,
                'average_usage' => $currentUsage,
            ];
        });
    }

    /**
     * Get network security alerts
     */
    public function getSecurityAlerts()
    {
        $alerts = [];

        try {
            // Check for suspicious activity patterns
            $activeSessions = $this->mikrotik->getActiveSessions();

            // Group sessions by IP to detect multiple simultaneous logins
            $sessionsByIP = [];
            foreach ($activeSessions as $session) {
                $ip = $session['address'];
                if (!isset($sessionsByIP[$ip])) {
                    $sessionsByIP[$ip] = [];
                }
                $sessionsByIP[$ip][] = $session;
            }

            foreach ($sessionsByIP as $ip => $sessions) {
                if (count($sessions) > 3) {
                    $alerts[] = [
                        'type' => 'multiple_sessions',
                        'severity' => 'warning',
                        'message' => "Multiple sessions detected from IP: {$ip}",
                        'details' => [
                            'ip_address' => $ip,
                            'session_count' => count($sessions),
                            'users' => array_column($sessions, 'user'),
                        ],
                    ];
                }
            }

            // Check for high bandwidth usage by single user
            foreach ($activeSessions as $session) {
                $totalUsage = $session['bytes_in'] + $session['bytes_out'];
                $usageGB = $totalUsage / (1024 * 1024 * 1024);

                if ($usageGB > 1) { // Alert for users with >1GB usage
                    $alerts[] = [
                        'type' => 'high_bandwidth_usage',
                        'severity' => 'info',
                        'message' => "High bandwidth usage by user: {$session['user']}",
                        'details' => [
                            'username' => $session['user'],
                            'ip_address' => $session['address'],
                            'usage_gb' => round($usageGB, 2),
                        ],
                    ];
                }
            }

        } catch (\Exception $e) {
            $alerts[] = [
                'type' => 'monitoring_error',
                'severity' => 'warning',
                'message' => 'Failed to check security alerts: ' . $e->getMessage(),
            ];
        }

        return $alerts;
    }


    /**
     * Calculate overall health status
     */
    private function calculateOverallHealth($components)
    {
        $scores = [
            'healthy' => 100,
            'warning' => 60,
            'critical' => 0,
        ];

        $totalScore = 0;
        $componentCount = 0;

        foreach ($components as $component => $status) {
            $totalScore += $scores[$status] ?? 0;
            $componentCount++;
        }

        if ($componentCount === 0) {
            return 'unknown';
        }

        $averageScore = $totalScore / $componentCount;

        return match (true) {
            $averageScore >= 90 => 'healthy',
            $averageScore >= 60 => 'warning',
            default => 'critical'
        };
    }}
