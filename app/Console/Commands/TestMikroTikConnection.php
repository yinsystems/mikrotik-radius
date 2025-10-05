<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MikroTikService;
use Exception;

class TestMikroTikConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:test-connection {environment?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to MikroTik router using RouterOS API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $environment = $this->argument('environment') ?? config('mikrotik.default', 'local');
        
        $this->info("Testing MikroTik connection for environment: {$environment}");
        $this->line('');

        try {
            // Initialize MikroTik service
            $mikrotik = new MikroTikService($environment);
            
            $this->line('📡 Initializing connection...');
            
            // Test connection
            $mikrotik->connect();
            
            $this->info('✅ Connection established successfully!');
            $this->line('');
            
            // Get connection status
            $status = $mikrotik->getConnectionStatus();
            $this->line('📊 Connection Details:');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Host', $status['host']],
                    ['Port', $status['port']],
                    ['SSL', $status['ssl'] ? 'Yes' : 'No'],
                    ['User', $status['user']],
                    ['Connected', $status['connected'] ? 'Yes' : 'No']
                ]
            );
            
            // Test basic functionality
            $this->line('🔍 Testing basic functionality...');
            
            // Get system identity
            $identity = $mikrotik->getSystemIdentity();
            $this->info("Router Identity: " . ($identity['name'] ?? 'Unknown'));
            
            // Get system resources
            $resources = $mikrotik->getSystemResources();
            if (isset($resources['version'])) {
                $this->info("RouterOS Version: " . $resources['version']);
                $this->info("Uptime: " . ($resources['uptime'] ?? 'Unknown'));
                $this->info("CPU Load: " . ($resources['cpu-load'] ?? 'Unknown') . '%');
                $this->info("Free Memory: " . $this->formatBytes($resources['free-memory'] ?? 0));
            }
            
            // Test hotspot functionality
            $this->line('');
            $this->line('🌐 Testing hotspot functionality...');
            
            $activeSessions = $mikrotik->getActiveSessions();
            $sessionCount = count($activeSessions);
            $this->info("Active Hotspot Sessions: {$sessionCount}");
            
            if ($sessionCount > 0) {
                $this->line('📋 Recent Active Sessions:');
                $sessionData = [];
                foreach (array_slice($activeSessions, 0, 5) as $session) {
                    $sessionData[] = [
                        $session['user'] ?? 'Unknown',
                        $session['address'] ?? 'Unknown',
                        $session['uptime'] ?? 'Unknown',
                        $this->formatBytes(($session['bytes-in'] ?? 0) + ($session['bytes-out'] ?? 0))
                    ];
                }
                $this->table(['User', 'IP Address', 'Uptime', 'Data Used'], $sessionData);
            }
            
            // Test user profiles
            $profiles = $mikrotik->getUserProfiles();
            $this->info("Available User Profiles: " . count($profiles));
            
            $this->line('');
            $this->info('🎉 All tests completed successfully!');
            $this->info('The MikroTik RouterOS connection is working properly.');
            
        } catch (Exception $e) {
            $this->line('');
            $this->error('❌ Connection test failed!');
            $this->error('Error: ' . $e->getMessage());
            $this->line('');
            $this->warn('🔧 Troubleshooting tips:');
            $this->line('• Check if the MikroTik router is accessible');
            $this->line('• Verify the API service is enabled: /ip service enable api');
            $this->line('• Check firewall rules for API port access');
            $this->line('• Verify credentials in your .env file');
            $this->line('• Ensure the user has API permissions');
            
            return 1;
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes)
    {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes, 1024));
        
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}
