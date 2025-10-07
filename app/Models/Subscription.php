<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\MikroTikService;
use Illuminate\Support\Facades\Log;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'package_id',
        'status', // 'active', 'expired', 'suspended', 'pending', 'blocked'
        'starts_at',
        'expires_at',
        'data_used', // in MB
        'sessions_used',
        'is_trial',
        'auto_renew',
        'renewal_package_id', // package to renew with
        'notes',
        'expiry_warning_sent_at',
        'expiry_notification_sent'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'expiry_warning_sent_at' => 'datetime',
        'is_trial' => 'boolean',
        'auto_renew' => 'boolean',
        'expiry_notification_sent' => 'boolean',
        'data_used' => 'integer',
        'sessions_used' => 'integer'
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function renewalPackage()
    {
        return $this->belongsTo(Package::class, 'renewal_package_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class)->latest();
    }

    public function dataUsage()
    {
        return $this->hasMany(DataUsage::class);
    }

    public function radAcct()
    {
        return $this->hasMany(RadAcct::class, 'username', 'username');
    }

    public function radCheck()
    {
        return $this->hasMany(RadCheck::class, 'username', 'username');
    }

    public function radReply()
    {
        return $this->hasMany(RadReply::class, 'username', 'username');
    }

    public function radUserGroup()
    {
        return $this->hasMany(RadUserGroup::class, 'username', 'username');
    }

    // Helper methods
    public function getUsernameAttribute()
    {
        return $this->customer->username ?? $this->customer->phone;
    }

    public function getPasswordAttribute()
    {
        return $this->customer->password;
    }

    public function isExpired()
    {
        return $this->expires_at < now();
    }

    public function isActive()
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function canRenew()
    {
        return in_array($this->status, ['active', 'expired']) && !$this->is_trial;
    }

    public function getDaysUntilExpiryAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }
        
        return now()->diffInDays($this->expires_at);
    }

    public function getHoursUntilExpiryAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }
        
        return now()->diffInHours($this->expires_at);
    }

    public function getTimeRemainingAttribute()
    {
        if ($this->isExpired()) {
            return 'Expired';
        }
        
        $diff = now()->diff($this->expires_at);
        
        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ', ' . $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ', ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        } else {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    }

    public function getDataUsagePercentageAttribute()
    {
        if (!$this->package->data_limit) {
            return 0; // Unlimited
        }
        
        return min(100, ($this->data_used / $this->package->data_limit) * 100);
    }

    public function hasDataLimitExceeded()
    {
        if (!$this->package->data_limit) {
            return false; // Unlimited
        }
        
        return $this->data_used >= $this->package->data_limit;
    }

    public function getRemainingDataAttribute()
    {
        if (!$this->package->data_limit) {
            return 'Unlimited';
        }
        
        $remaining = $this->package->data_limit - $this->data_used;
        
        if ($remaining <= 0) {
            return '0 MB';
        }
        
        if ($remaining >= 1024) {
            return number_format($remaining / 1024, 2) . ' GB';
        }
        
        return $remaining . ' MB';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeExpiringSoon($query, $hours = 24)
    {
        return $query->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->where('expires_at', '<', now()->addHours($hours));
    }

    public function scopeTrial($query)
    {
        return $query->where('is_trial', true);
    }

    public function scopeAutoRenew($query)
    {
        return $query->where('auto_renew', true);
    }

    public function scopeByPackage($query, $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    public function scopeDataLimitExceeded($query)
    {
        return $query->whereHas('package', function($q) {
            $q->whereNotNull('data_limit');
        })->whereRaw('data_used >= (SELECT data_limit FROM packages WHERE packages.id = subscriptions.package_id)');
    }

    // RADIUS Integration Methods
    public function createRadiusUser()
    {
        // Create authentication entry
        RadCheck::setPassword($this->username, $this->password);
        
        // Set Session-Timeout for time-based packages (individual user level)
        if ($this->package->isTimeBased()) {
            $this->setUserSessionTimeout();
        }
        
        // Set individual user replies
        if ($this->package->bandwidth_upload && $this->package->bandwidth_download) {
            RadReply::setBandwidthLimit($this->username, $this->package->bandwidth_upload, $this->package->bandwidth_download);
        }
        
        if ($this->package->data_limit) {
            RadReply::setDataLimit($this->username, $this->package->data_limit * 1024 * 1024);
        }
        
        // Set Session-Timeout based on package duration type
        if ($this->package->isTimeBased()) {
            $this->setUserSessionTimeout();
        }
        
        if ($this->package->vlan_id) {
            RadReply::setVlan($this->username, $this->package->vlan_id);
        }
        
        // Assign to package group (remove from any previous package groups first)
        RadUserGroup::removeFromPackageGroups($this->username);
        RadUserGroup::assignToPackageGroup($this->username, $this->package->id);
        
        // Ensure package group is properly set up with all required attributes
        $this->package->setupRadiusGroup();
        
        return $this;
    }
    
    public function updateRadiusUser()
    {
        // Remove individual expiration
        RadCheck::removeExpiration($this->username);
        
        // Remove any old session timeout
        RadCheck::where('username', $this->username)
                ->where('attribute', 'Session-Timeout')
                ->delete();
        
        if ($this->package->isTimeBased()) {
            $this->setUserSessionTimeout();
        }
        
        // Update password if changed
        RadCheck::setPassword($this->username, $this->password);
        
        // Update bandwidth limits
        if ($this->package->bandwidth_upload && $this->package->bandwidth_download) {
            RadReply::setBandwidthLimit($this->username, $this->package->bandwidth_upload, $this->package->bandwidth_download);
        }
        
        // Update data limit
        if ($this->package->data_limit) {
            RadReply::setDataLimit($this->username, $this->package->data_limit * 1024 * 1024);
        }
        
        // Update group assignment
        RadUserGroup::removeFromPackageGroups($this->username);
        RadUserGroup::assignToPackageGroup($this->username, $this->package->id);
        
        return $this;
    }
    
    public function deleteRadiusUser()
    {
        // Terminate active sessions
        RadAcct::terminateUserSessions($this->username, 'Subscription Deleted');
        
        // Remove from all groups
        RadUserGroup::removeFromAllGroups($this->username);
        
        // Remove user attributes
        RadCheck::cleanupUser($this->username);
        RadReply::cleanupUser($this->username);
        
        return $this;
    }
    
    public function syncRadiusStatus()
    {
        switch ($this->status) {
            case 'active':
                if (!$this->isExpired()) {
                    // Set subscribed mode for active users
                    RadCheck::setSubscribedMode($this->username, $this->getSessionTimeoutSeconds());
                    
                    // Apply package-specific bandwidth limits if defined
                    if ($this->package && ($this->package->upload_speed || $this->package->download_speed)) {
                        RadReply::setBandwidthLimit(
                            $this->username, 
                            $this->package->upload_speed ?? 1024, 
                            $this->package->download_speed ?? 1024
                        );
                    }
                } else {
                    // Put expired users in data exhausted mode
                    RadCheck::setDataExhaustedMode($this->username);
                    
                    // Force disconnect active sessions
                    RadCheck::forceDisconnectUser($this->username);
                    $this->disconnectActiveSessions();
                }
                break;
                
            case 'suspended':
            case 'blocked':
                RadCheck::blockUserForSuspension($this->username);
                
                // Disconnect active sessions for suspended/blocked users
                RadCheck::forceDisconnectUser($this->username);
                $this->disconnectActiveSessions();
                break;
                
            case 'expired':
                // Put expired users in data exhausted mode
                RadCheck::setDataExhaustedMode($this->username);
                
                // Force disconnect active sessions
                RadCheck::forceDisconnectUser($this->username);
                $this->disconnectActiveSessions();
                break;
                
            case 'pending':
                RadCheck::blockUser($this->username, 'Your account is pending activation. Please wait for approval.');
                break;
        }
        
        return $this;
    }
    
    public function updateDataUsageFromRadius()
    {
        $totalUsage = RadAcct::getTotalUsage($this->username);
        $this->update(['data_used' => $totalUsage]);
        
        // Check if data limit exceeded
        if ($this->hasDataLimitExceeded()) {
            $this->suspend('Data limit exceeded');
        }
        
        return $this;
    }

    // Status management with RADIUS integration
    public function activate()
    {
        // Remove user from data-exhausted list when activating
        RadReply::removeFromAddressList($this->username, 'data-exhausted-users');
        
        $this->update(['status' => 'active']);
        $this->syncRadiusStatus();
        
        return $this;
    }

    public function suspend($reason = null)
    {
        $this->update(['status' => 'suspended']);
        $this->syncRadiusStatus();
        
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Suspended: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }
        
        return $this;
    }

    public function expire()
    {
        $this->update(['status' => 'expired']);
        $this->syncRadiusStatus();
        
        return $this;
    }

    public function block($reason = null)
    {
        $this->update(['status' => 'blocked']);
        $this->syncRadiusStatus();
        
        // Terminate active sessions immediately
        RadAcct::terminateUserSessions($this->username, $reason ?? 'Subscription Blocked');
        
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Blocked: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }
        
        return $this;
    }

    public function unblock($reason = 'Subscription Unblocked')
    {
        // Only unblock if not expired
        if (!$this->isExpired()) {
            $this->update(['status' => 'active']);
            $this->syncRadiusStatus();
        } else {
            $this->update(['status' => 'expired']);
        }
        
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Unblocked: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }
        
        return $this;
    }

    public function resume($reason = 'Subscription Resumed')
    {
        // Only resume if not expired
        if (!$this->isExpired()) {
            $this->update(['status' => 'active']);
            $this->syncRadiusStatus();
        } else {
            $this->update(['status' => 'expired']);
        }
        
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Resumed: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }
        
        return $this;
    }

    // Enhanced status check methods
    public function isSuspended()
    {
        return $this->status === 'suspended';
    }

    public function isBlocked()
    {
        return $this->status === 'blocked';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function canAccessInternet()
    {
        return $this->isActive() && !$this->hasDataLimitExceeded();
    }

    public function getActiveSessionCount()
    {
        return RadAcct::getActiveSessionCount($this->username);
    }

    public function terminateActiveSessions($reason = 'Admin Action')
    {
        RadAcct::terminateUserSessions($this->username, $reason);
        
        return $this;
    }
    
    public function renew($newPackage = null, $paymentId = null)
    {
        $package = $newPackage ?? $this->package;
        
        // Calculate new expiration
        $newExpiration = $this->calculateExpiration($package, $this->expires_at);
        
        $this->update([
            'package_id' => $package->id,
            'expires_at' => $newExpiration,
            'status' => 'active'
        ]);
        
        // Update RADIUS settings
        $this->updateRadiusUser();
        $this->syncRadiusStatus();
        
        return $this;
    }

    // Session Management Methods
    public function getActiveSessions()
    {
        return RadAcct::where('username', $this->username)
                     ->whereNull('acctstoptime')
                     ->orderBy('acctstarttime', 'desc')
                     ->get()
                     ->map(function($session) {
                         return [
                             'session_id' => $session->acctsessionid,
                             'nas_ip' => $session->nasipaddress,
                             'nas_port' => $session->nasportid,
                             'framed_ip' => $session->framedipaddress,
                             'calling_station' => $session->callingstationid,
                             'called_station' => $session->calledstationid,
                             'start_time' => $session->acctstarttime,
                             'session_time' => $session->acctsessiontime,
                             'input_octets' => $session->acctinputoctets,
                             'output_octets' => $session->acctoutputoctets,
                             'input_packets' => $session->acctinputpackets,
                             'output_packets' => $session->acctoutputpackets,
                             'terminate_cause' => $session->acctterminatecause,
                             'service_type' => $session->servicetype,
                             'framed_protocol' => $session->framedprotocol,
                             'connect_info' => $session->connectinfo_start
                         ];
                     });
    }

    public function disconnectAllSessions($reason = 'Admin Disconnect')
    {
        $activeSessions = $this->getActiveSessions();
        
        if ($activeSessions->isEmpty()) {
            return ['success' => true, 'message' => 'No active sessions found', 'disconnected' => 0];
        }

        $disconnectedCount = 0;
        
        foreach ($activeSessions as $session) {
            try {
                // Send RADIUS Disconnect Message (requires RADIUS client setup)
                $this->sendRadiusDisconnect($session['session_id'], $session['nas_ip'], $reason);
                
                // Update RadAcct table to mark session as terminated
                RadAcct::where('acctsessionid', $session['session_id'])
                       ->where('username', $this->username)
                       ->whereNull('acctstoptime')
                       ->update([
                           'acctstoptime' => now(),
                           'acctterminatecause' => $reason,
                           'acctsessiontime' => now()->diffInSeconds($session['start_time'])
                       ]);
                
                $disconnectedCount++;
                
            } catch (\Exception $e) {
                \Log::error("Failed to disconnect session {$session['session_id']} for user {$this->username}: " . $e->getMessage());
            }
        }

        return [
            'success' => $disconnectedCount > 0,
            'message' => "Disconnected {$disconnectedCount} of " . $activeSessions->count() . " sessions",
            'disconnected' => $disconnectedCount,
            'total_sessions' => $activeSessions->count()
        ];
    }

    public function getSessionHistory($limit = 50, $days = 30)
    {
        $startDate = now()->subDays($days);
        
        return RadAcct::where('username', $this->username)
                     ->where('acctstarttime', '>=', $startDate)
                     ->orderBy('acctstarttime', 'desc')
                     ->limit($limit)
                     ->get()
                     ->map(function($session) {
                         return [
                             'session_id' => $session->acctsessionid,
                             'nas_ip' => $session->nasipaddress,
                             'nas_port' => $session->nasportid,
                             'framed_ip' => $session->framedipaddress,
                             'calling_station' => $session->callingstationid,
                             'start_time' => $session->acctstarttime,
                             'stop_time' => $session->acctstoptime,
                             'session_time' => $session->acctsessiontime,
                             'session_time_formatted' => $this->formatSessionTime($session->acctsessiontime),
                             'input_octets' => $session->acctinputoctets,
                             'output_octets' => $session->acctoutputoctets,
                             'total_bytes' => ($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0),
                             'total_mb' => round((($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0)) / (1024 * 1024), 2),
                             'terminate_cause' => $session->acctterminatecause,
                             'is_active' => is_null($session->acctstoptime)
                         ];
                     });
    }

    public function getSessionStatistics($days = 30)
    {
        $startDate = now()->subDays($days);
        
        $sessions = RadAcct::where('username', $this->username)
                          ->where('acctstarttime', '>=', $startDate)
                          ->get();

        $totalSessions = $sessions->count();
        $activeSessions = $sessions->whereNull('acctstoptime')->count();
        $totalBytes = $sessions->sum(function($session) {
            return ($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0);
        });
        $totalTime = $sessions->sum('acctsessiontime');
        $avgSessionTime = $totalSessions > 0 ? $totalTime / $totalSessions : 0;

        return [
            'period_days' => $days,
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'completed_sessions' => $totalSessions - $activeSessions,
            'total_data_mb' => round($totalBytes / (1024 * 1024), 2),
            'total_data_gb' => round($totalBytes / (1024 * 1024 * 1024), 3),
            'total_time_seconds' => $totalTime,
            'total_time_hours' => round($totalTime / 3600, 2),
            'avg_session_time_seconds' => round($avgSessionTime),
            'avg_session_time_formatted' => $this->formatSessionTime($avgSessionTime),
            'avg_data_per_session_mb' => $totalSessions > 0 ? round($totalBytes / $totalSessions / (1024 * 1024), 2) : 0
        ];
    }

    public function getCurrentSessionCount()
    {
        return RadAcct::where('username', $this->username)
                     ->whereNull('acctstoptime')
                     ->count();
    }

    public function getSessionsByDate($date)
    {
        return RadAcct::where('username', $this->username)
                     ->whereDate('acctstarttime', $date)
                     ->orderBy('acctstarttime', 'desc')
                     ->get();
    }

    public function getTodaysUsage()
    {
        $todaySessions = $this->getSessionsByDate(today());
        
        $totalBytes = $todaySessions->sum(function($session) {
            return ($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0);
        });
        
        $totalTime = $todaySessions->sum('acctsessiontime');
        
        return [
            'date' => today()->format('Y-m-d'),
            'sessions' => $todaySessions->count(),
            'active_sessions' => $todaySessions->whereNull('acctstoptime')->count(),
            'total_mb' => round($totalBytes / (1024 * 1024), 2),
            'total_time_seconds' => $totalTime,
            'total_time_formatted' => $this->formatSessionTime($totalTime)
        ];
    }

    // Helper method to send RADIUS disconnect message
    private function sendRadiusDisconnect($sessionId, $nasIp, $reason = 'Admin Disconnect')
    {
        // This would require a RADIUS client library
        // For now, we'll just log the disconnect attempt
        \Log::info("RADIUS Disconnect sent for session {$sessionId} on NAS {$nasIp}, reason: {$reason}");
        
        // You would implement actual RADIUS disconnect here using a library like:
        // - freeradius-client PHP extension
        // - Custom UDP socket communication
        // - External RADIUS client command
        
        return true;
    }

    // Static methods for subscription management
    public static function expireOldSubscriptions()
    {
        // Find all subscriptions that have expired
        $expiredSubscriptions = self::where('status', 'active')
                                  ->where('expires_at', '<', now())
                                  ->get();

        $expiredCount = 0;
        foreach ($expiredSubscriptions as $subscription) {
            try {
                // Disconnect all active sessions
                $subscription->disconnectAllSessions('Subscription Expired');
                
                // Update status to expired
                $subscription->update(['status' => 'expired']);
                
                // Sync RADIUS status (this will remove/disable RADIUS user)
                $subscription->syncRadiusStatus();
                
                $expiredCount++;
                
                \Log::info("Expired subscription {$subscription->id} for user {$subscription->username}");
                
            } catch (\Exception $e) {
                \Log::error("Failed to expire subscription {$subscription->id}: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'expired_count' => $expiredCount,
            'total_found' => $expiredSubscriptions->count(),
            'message' => "Expired {$expiredCount} subscriptions"
        ];
    }

    public static function autoRenewSubscriptions()
    {
        // Find subscriptions that are set for auto-renewal and are about to expire (within 24 hours)
        $subscriptionsToRenew = self::where('status', 'active')
                                  ->where('auto_renew', true)
                                  ->where('expires_at', '<=', now()->addHours(24))
                                  ->where('expires_at', '>', now())
                                  ->with(['customer', 'package'])
                                  ->get();

        $renewedCount = 0;
        $failedCount = 0;

        foreach ($subscriptionsToRenew as $subscription) {
            try {
                $customer = $subscription->customer;
                $renewalPackage = $subscription->renewal_package_id 
                                ? Package::find($subscription->renewal_package_id) 
                                : $subscription->package;

                if (!$renewalPackage) {
                    \Log::warning("No renewal package found for subscription {$subscription->id}");
                    $failedCount++;
                    continue;
                }

                // Create new subscription for renewal
                $newSubscription = $customer->createSubscription($renewalPackage->id);
                
                // Mark old subscription as expired
                $subscription->update(['status' => 'expired']);
                $subscription->syncRadiusStatus();
                
                $renewedCount++;
                
                \Log::info("Auto-renewed subscription {$subscription->id} with new subscription {$newSubscription->id}");
                
                // You could trigger payment processing here if needed
                // event(new SubscriptionAutoRenewed($subscription, $newSubscription));
                
            } catch (\Exception $e) {
                \Log::error("Failed to auto-renew subscription {$subscription->id}: " . $e->getMessage());
                $failedCount++;
            }
        }

        return [
            'success' => true,
            'renewed_count' => $renewedCount,
            'failed_count' => $failedCount,
            'total_found' => $subscriptionsToRenew->count(),
            'message' => "Auto-renewed {$renewedCount} subscriptions, {$failedCount} failed"
        ];
    }

    public static function sendExpirationNotices()
    {
        // Dynamic warning time based on package duration type
        $notificationsSent = 0;
        $notificationService = app(\App\Services\NotificationService::class);
        
        // Get active subscriptions with package info that haven't been notified yet
        $activeSubscriptions = self::where('status', 'active')
                                 ->where('expiry_notification_sent', false)
                                 ->with(['customer', 'package'])
                                 ->get();

        $expiringSubscriptions = collect();

        foreach ($activeSubscriptions as $subscription) {
            if (!$subscription->customer || !$subscription->package || $subscription->expires_at->isPast()) {
                continue;
            }

            $package = $subscription->package;
            $hoursUntilExpiry = now()->diffInHours($subscription->expires_at, false);
            $minutesUntilExpiry = now()->diffInMinutes($subscription->expires_at, false);
            
            // Determine if we should send notification based on package type
            $shouldNotify = false;
            
            if ($package->duration_type === 'minutely') {
                // For minute packages: notify once when 20% of time remains (but not less than 1 minute)
                $warningMinutes = max(1, $package->duration_value * 0.2);
                $shouldNotify = $minutesUntilExpiry <= $warningMinutes && $minutesUntilExpiry > 0;
            } elseif ($package->duration_type === 'hourly') {
                // For hourly packages: notify once when 1 hour before or 25% remains (whichever is less)
                $warningHours = min(1, $package->duration_value * 0.25);
                $shouldNotify = $hoursUntilExpiry <= $warningHours && $hoursUntilExpiry > 0;
            } else {
                // For daily/weekly/monthly packages: notify 24 hours before (original logic)
                $warningHours = config('notification.types.expiration_warning.warning_hours', 24);
                $shouldNotify = $hoursUntilExpiry <= $warningHours && $hoursUntilExpiry > 0;
            }

            if ($shouldNotify) {
                $expiringSubscriptions->push($subscription);
            }
        }

        foreach ($expiringSubscriptions as $subscription) {
            try {
                $timeRemaining = $subscription->expires_at->diffForHumans();
                $minutesRemaining = now()->diffInMinutes($subscription->expires_at, false);
                $hoursRemaining = now()->diffInHours($subscription->expires_at, false);
                
                $notificationService->sendExpirationWarning([
                    'name' => $subscription->customer->name,
                    'email' => $subscription->customer->email,
                    'phone' => $subscription->customer->phone,
                ], [
                    'package_name' => $subscription->package->name,
                    'expires_at' => $subscription->expires_at->format('Y-m-d H:i:s'),
                    'hours_remaining' => $hoursRemaining,
                    'minutes_remaining' => $minutesRemaining,
                    'time_remaining' => $timeRemaining,
                    'duration_type' => $subscription->package->duration_type,
                ]);

                // Mark notification as sent to prevent duplicates
                $subscription->update([
                    'expiry_notification_sent' => true,
                    'expiry_warning_sent_at' => now()
                ]);

                $notificationsSent++;
                
                \Log::info("Expiration warning sent for subscription {$subscription->id} ({$subscription->package->duration_type} package, {$timeRemaining} remaining)");
            } catch (\Exception $e) {
                \Log::error("Failed to send expiration warning for subscription {$subscription->id}: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'notifications_sent' => [
                'total' => $notificationsSent,
            ],
            'subscription_ids' => $expiringSubscriptions->pluck('id')->toArray(),
            'message' => "Sent {$notificationsSent} expiration warning notifications"
        ];
    }

    public static function cleanupExpiredSessions()
    {
        // Clean up old RADIUS sessions for expired subscriptions
        $expiredSubscriptions = self::where('status', 'expired')
                                  ->where('expires_at', '<', now()->subDays(7))
                                  ->get();

        $cleanedSessions = 0;
        foreach ($expiredSubscriptions as $subscription) {
            try {
                // Close any remaining open sessions
                $openSessions = RadAcct::where('username', $subscription->username)
                                      ->whereNull('acctstoptime')
                                      ->get();

                foreach ($openSessions as $session) {
                    $session->update([
                        'acctstoptime' => $session->acctstarttime,
                        'acctterminatecause' => 'Cleanup-Expired-Subscription',
                        'acctsessiontime' => 0
                    ]);
                    $cleanedSessions++;
                }

            } catch (\Exception $e) {
                \Log::error("Failed to cleanup sessions for expired subscription {$subscription->id}: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'cleaned_sessions' => $cleanedSessions,
            'message' => "Cleaned up {$cleanedSessions} expired sessions"
        ];
    }
    
    private function formatSessionTime($seconds)
    {
        if ($seconds >= 3600) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;
            return sprintf('%dh %dm %ds', $hours, $minutes, $remainingSeconds);
        } elseif ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return sprintf('%dm %ds', $minutes, $remainingSeconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }
    
    /**
     * Set Session-Timeout for time-based packages
     */
    private function setUserSessionTimeout()
    {
        // Calculate session duration in seconds based on package
        $totalSeconds = match($this->package->duration_type) {
            'minutely' => $this->package->duration_value * 60,
            'hourly' => $this->package->duration_value * 3600,
            'daily' => $this->package->duration_value * 24 * 3600,
            'weekly' => $this->package->duration_value * 7 * 24 * 3600,
            'monthly' => $this->package->duration_value * 30 * 24 * 3600,
            'trial' => $this->package->trial_duration_hours * 3600,
            default => 24 * 3600 // Default to 1 day
        };
        
        // Set Session-Timeout for this user (per session limit)
        RadCheck::setSessionTimeout($this->username, $totalSeconds);
    }

    /**
     * Disconnect active MikroTik sessions for this user
     */
    private function disconnectActiveSessions()
    {
        try {
            // Initialize MikroTik service
            $mikrotikService = new MikroTikService();
            
            // Disconnect all active sessions for this user
            $disconnectedSessions = $mikrotikService->disconnectUserByUsername($this->username);
            
            Log::info('Disconnected active sessions for subscription', [
                'subscription_id' => $this->id,
                'username' => $this->username,
                'status' => $this->status,
                'sessions_disconnected' => $disconnectedSessions
            ]);
            
            return $disconnectedSessions;
            
        } catch (\Exception $e) {
            Log::error('Failed to disconnect active sessions for subscription', [
                'subscription_id' => $this->id,
                'username' => $this->username,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
            
            // Don't throw the exception as this is not a critical failure
            // The RADIUS blocking will still prevent new sessions
            return 0;
        }
    }
    
    private function calculateExpiration($package, $startFrom = null)
    {
        $start = $startFrom ?? now();
        
        return match($package->duration_type) {
            'minutely' => $start->addMinutes($package->duration_value),
            'hourly' => $start->addHours($package->duration_value),
            'daily' => $start->addDays($package->duration_value),
            'weekly' => $start->addWeeks($package->duration_value),
            'monthly' => $start->addMonths($package->duration_value),
            'trial' => $start->addHours($package->trial_duration_hours),
            default => $start->addDays(1)
        };
    }
}