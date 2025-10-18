<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class RadiusDisconnectService
{
    private $nasServers;
    private $sharedSecret;
    private $timeout;
    private $retries;
    
    public function __construct()
    {
        $this->nasServers = config('radius.nas_servers', []);
        $this->sharedSecret = config('radius.shared_secret', 'testing123');
        $this->timeout = config('radius.disconnect_timeout', 5);
        $this->retries = config('radius.disconnect_retries', 3);
    }

    /**
     * Send RADIUS Disconnect-Request (CoA) to terminate a session
     */
    public function disconnectSession($sessionId, $nasIp, $username, $reason = 'Admin Disconnect')
    {
        try {
            // Attempt multiple disconnect methods for reliability
            $results = [];
            
            // Method 1: RADIUS CoA Disconnect-Request
            if ($this->isRadiusCoAEnabled()) {
                $results['radius_coa'] = $this->sendRadiusDisconnectRequest($sessionId, $nasIp, $username, $reason);
            }
            
            // Method 2: Router API disconnect (fallback)
            if ($this->isMikroTikApiEnabled() && (!isset($results['radius_coa']) || !$results['radius_coa']['success'])) {
                $results['router_api'] = $this->sendMikroTikDisconnect($username, $reason);
            }
            
            // Method 3: SNMP disconnect (if configured)
            if ($this->isSnmpEnabled() && $this->shouldTrySnmp($results)) {
                $results['snmp'] = $this->sendSnmpDisconnect($sessionId, $nasIp, $reason);
            }
            
            // Determine overall success
            $success = collect($results)->some(fn($result) => $result['success'] ?? false);
            
            // Log comprehensive results
            Log::info('Session disconnect attempt completed', [
                'session_id' => $sessionId,
                'nas_ip' => $nasIp,
                'username' => $username,
                'reason' => $reason,
                'methods_tried' => array_keys($results),
                'overall_success' => $success,
                'results' => $results
            ]);
            
            return [
                'success' => $success,
                'session_id' => $sessionId,
                'nas_ip' => $nasIp,
                'username' => $username,
                'reason' => $reason,
                'methods' => $results,
                'primary_method' => $this->getPrimarySuccessfulMethod($results),
                'timestamp' => now()
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to disconnect session', [
                'session_id' => $sessionId,
                'nas_ip' => $nasIp,
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'timestamp' => now()
            ];
        }
    }

    /**
     * Send RADIUS Disconnect-Request using external radclient or custom implementation
     */
    private function sendRadiusDisconnectRequest($sessionId, $nasIp, $username, $reason)
    {
        try {
            // Check if radclient is available (FreeRADIUS client)
            if ($this->isRadclientAvailable()) {
                return $this->sendDisconnectViaRadclient($sessionId, $nasIp, $username, $reason);
            }
            
            // Fallback to custom UDP RADIUS implementation
            return $this->sendDisconnectViaCustomRadius($sessionId, $nasIp, $username, $reason);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'method' => 'radius_coa',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send disconnect using radclient command-line tool
     */
    private function sendDisconnectViaRadclient($sessionId, $nasIp, $username, $reason)
    {
        // Create temporary file with disconnect attributes
        $tempFile = tempnam(sys_get_temp_dir(), 'radius_disconnect_');
        
        $attributes = [
            "Acct-Session-Id = \"{$sessionId}\"",
            "User-Name = \"{$username}\"",
            "NAS-IP-Address = {$nasIp}",
            "Acct-Terminate-Cause = Admin-Disconnect"
        ];
        
        file_put_contents($tempFile, implode("\n", $attributes));
        
        try {
            // Execute radclient command
            $command = sprintf(
                'radclient -f %s -x %s:3799 disconnect %s 2>&1',
                escapeshellarg($tempFile),
                escapeshellarg($nasIp),
                escapeshellarg($this->sharedSecret)
            );
            
            $output = shell_exec($command);
            $success = strpos($output, 'Received Access-Accept') !== false;
            
            return [
                'success' => $success,
                'method' => 'radclient',
                'output' => $output,
                'command' => $command
            ];
            
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Send disconnect using custom UDP RADIUS implementation
     */
    private function sendDisconnectViaCustomRadius($sessionId, $nasIp, $username, $reason)
    {
        // This would require implementing RADIUS packet format
        // For now, return a placeholder that logs the attempt
        
        Log::info('Custom RADIUS disconnect attempted', [
            'session_id' => $sessionId,
            'nas_ip' => $nasIp,
            'username' => $username,
            'note' => 'Custom RADIUS implementation needed - using MikroTik API fallback'
        ]);
        
        return [
            'success' => false,
            'method' => 'custom_radius',
            'error' => 'Custom RADIUS implementation not available - install radclient or implement UDP RADIUS client'
        ];
    }

    /**
     * Send disconnect via MikroTik Router API
     */
    private function sendMikroTikDisconnect($username, $reason)
    {
        try {
            $mikrotikService = app(\App\Services\MikroTikService::class);
            $disconnectedCount = $mikrotikService->disconnectUserByUsername($username);
            
            return [
                'success' => $disconnectedCount > 0,
                'method' => 'mikrotik_api',
                'disconnected_sessions' => $disconnectedCount,
                'reason' => $reason
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'method' => 'mikrotik_api',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send disconnect via SNMP (if NAS supports it)
     */
    private function sendSnmpDisconnect($sessionId, $nasIp, $reason)
    {
        // Placeholder for SNMP-based disconnect
        // Different NAS vendors have different SNMP MIBs for session management
        
        return [
            'success' => false,
            'method' => 'snmp',
            'error' => 'SNMP disconnect not implemented'
        ];
    }

    /**
     * Disconnect multiple sessions efficiently
     */
    public function disconnectMultipleSessions($sessions, $reason = 'Admin Disconnect')
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        foreach ($sessions as $session) {
            $result = $this->disconnectSession(
                $session['session_id'],
                $session['nas_ip'],
                $session['username'],
                $reason
            );
            
            $results[] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
            
            // Rate limiting: small delay between disconnects
            if (count($sessions) > 10) {
                usleep(100000); // 100ms delay for large batches
            }
        }
        
        return [
            'total_sessions' => count($sessions),
            'successful_disconnects' => $successCount,
            'failed_disconnects' => $failCount,
            'success_rate' => count($sessions) > 0 ? ($successCount / count($sessions)) * 100 : 0,
            'results' => $results,
            'timestamp' => now()
        ];
    }

    /**
     * Check if radclient is available on system
     */
    private function isRadclientAvailable()
    {
        // Check if radclient is available in PATH
        $output = shell_exec('which radclient 2>/dev/null');
        return !empty(trim($output));
    }

    /**
     * Check configuration flags
     */
    private function isRadiusCoAEnabled()
    {
        return config('radius.coa_enabled', true);
    }

    private function isMikroTikApiEnabled()
    {
        return config('radius.mikrotik_api_fallback', true);
    }

    private function isSnmpEnabled()
    {
        return config('radius.snmp_enabled', false);
    }

    private function shouldTrySnmp($previousResults)
    {
        return collect($previousResults)->every(fn($result) => !($result['success'] ?? false));
    }

    private function getPrimarySuccessfulMethod($results)
    {
        $successfulMethod = collect($results)->first(fn($result) => $result['success'] ?? false);
        return $successfulMethod['method'] ?? null;
    }

    /**
     * Health check for disconnect capabilities
     */
    public function healthCheck()
    {
        $capabilities = [];
        
        // Check RADIUS CoA capability
        $capabilities['radius_coa'] = [
            'available' => $this->isRadclientAvailable(),
            'method' => $this->isRadclientAvailable() ? 'radclient' : 'custom_implementation_needed'
        ];
        
        // Check MikroTik API capability
        try {
            $mikrotikService = app(\App\Services\MikroTikService::class);
            $capabilities['mikrotik_api'] = [
                'available' => true,
                'service_loaded' => true
            ];
        } catch (Exception $e) {
            $capabilities['mikrotik_api'] = [
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Check SNMP capability
        $capabilities['snmp'] = [
            'available' => extension_loaded('snmp'),
            'enabled' => $this->isSnmpEnabled()
        ];
        
        return $capabilities;
    }
}