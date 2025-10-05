<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'duration_type', // 'hourly', 'daily', 'weekly', 'monthly', 'trial'
        'duration_value', // number of hours/days/weeks/months
        'price',
        'bandwidth_upload', // in Kbps
        'bandwidth_download', // in Kbps
        'data_limit', // in MB (null for unlimited)
        'simultaneous_users',
        'is_active',
        'is_trial',
        'trial_duration_hours',
        'vlan_id', // for network segmentation
        'priority' // for package ordering
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_trial' => 'boolean',
        'duration_value' => 'integer',
        'simultaneous_users' => 'integer',
        'bandwidth_upload' => 'integer',
        'bandwidth_download' => 'integer',
        'data_limit' => 'integer',
        'trial_duration_hours' => 'integer',
        'vlan_id' => 'integer',
        'priority' => 'integer'
    ];

    // Relationships
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Helper methods
    public function getDurationDisplayAttribute()
    {
        return match($this->duration_type) {
            'hourly' => $this->duration_value . ' Hour' . ($this->duration_value > 1 ? 's' : ''),
            'daily' => $this->duration_value . ' Day' . ($this->duration_value > 1 ? 's' : ''),
            'weekly' => $this->duration_value . ' Week' . ($this->duration_value > 1 ? 's' : ''),
            'monthly' => $this->duration_value . ' Month' . ($this->duration_value > 1 ? 's' : ''),
            'trial' => 'Trial (' . $this->trial_duration_hours . ' hours)',
            default => 'Unknown'
        };
    }

    public function getBandwidthDisplayAttribute()
    {
        if ($this->bandwidth_upload === $this->bandwidth_download) {
            return $this->formatBandwidth($this->bandwidth_upload);
        }
        return $this->formatBandwidth($this->bandwidth_upload) . '/' . $this->formatBandwidth($this->bandwidth_download);
    }

    public function getDataLimitDisplayAttribute()
    {
        if (is_null($this->data_limit)) {
            return 'Unlimited';
        }

        if ($this->data_limit >= 1024) {
            return number_format($this->data_limit / 1024, 1) . ' GB';
        }

        return $this->data_limit . ' MB';
    }

    private function formatBandwidth($kbps)
    {
        if ($kbps >= 1024) {
            return number_format($kbps / 1024, 1) . ' Mbps';
        }
        return $kbps . ' Kbps';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTrial($query)
    {
        return $query->where('is_trial', true);
    }

    public function scopeRegular($query)
    {
        return $query->where('is_trial', false);
    }

    public function scopeAvailableTrials($query)
    {
        return $query->where('is_trial', true)->where('is_active', true);
    }

    // Static methods for trial package management
    public static function getDefaultTrialPackage()
    {
        return self::availableTrials()->first();
    }

    public static function hasAvailableTrialPackages()
    {
        return self::availableTrials()->exists();
    }

    // RADIUS Group Management Methods
    public function setupRadiusGroup()
    {
        $groupname = $this->getGroupName();
        
        // Clean up any existing entries first
        RadGroupCheck::cleanupGroup($groupname);
        RadGroupReply::cleanupGroup($groupname);
        
        // Setup group restrictions (RadGroupCheck)
        $this->addGroupCheckAttributes($groupname);
        
        // Setup group attributes (RadGroupReply)
        $this->addGroupReplyAttributes($groupname);

        return $this;
    }

    /**
     * Add RadGroupCheck attributes (restrictions/checks)
     */
    private function addGroupCheckAttributes($groupname)
    {
        // 1. Simultaneous-Use: Concurrent session limit
        $simultaneousUsers = $this->simultaneous_users ?: 1;
        RadGroupCheck::create([
            'groupname' => $groupname,
            'attribute' => 'Simultaneous-Use',
            'op' => ':=',
            'value' => (string)$simultaneousUsers
        ]);

        // 2. Max-All-Session: Maximum total session time (in seconds)
        if ($this->isTimeBased()) {
            $maxAllSession = $this->getDurationInSeconds();
            RadGroupCheck::create([
                'groupname' => $groupname,
                'attribute' => 'Max-All-Session',
                'op' => ':=',
                'value' => (string)$maxAllSession
            ]);
        }

        // 3. Session-Timeout: Per-session timeout (same as Max-All-Session for simplicity)
        if ($this->isTimeBased()) {
            $sessionTimeout = $this->getDurationInSeconds();
            RadGroupCheck::create([
                'groupname' => $groupname,
                'attribute' => 'Session-Timeout',
                'op' => ':=',
                'value' => (string)$sessionTimeout
            ]);
        }

        // 4. Service-Type: Always Login-User for internet access
        RadGroupCheck::create([
            'groupname' => $groupname,
            'attribute' => 'Service-Type',
            'op' => ':=',
            'value' => 'Login-User'
        ]);

        // 5. Data limit check (if package has data limit)
        if ($this->data_limit) {
            // Convert data limit to bytes
            $dataLimitBytes = $this->getDataLimitInBytes();
            RadGroupCheck::create([
                'groupname' => $groupname,
                'attribute' => 'ChilliSpot-Max-Total-Octets',
                'op' => ':=',
                'value' => (string)$dataLimitBytes
            ]);
        }
    }

    /**
     * Add RadGroupReply attributes (response/configuration)
     */
    private function addGroupReplyAttributes($groupname)
    {
        $uploadKbps = $this->bandwidth_upload ?: 2000;   // Default 2M upload
        $downloadKbps = $this->bandwidth_download ?: 5000; // Default 5M download
        
        // Convert Kbps to bps for WISPr attributes
        $uploadBps = $uploadKbps * 1000;
        $downloadBps = $downloadKbps * 1000;

        // 1. ChilliSpot-Max-Total-Octets: Data limit in bytes (if applicable)
        if ($this->data_limit) {
            $dataLimitBytes = $this->getDataLimitInBytes();
            RadGroupReply::create([
                'groupname' => $groupname,
                'attribute' => 'ChilliSpot-Max-Total-Octets',
                'op' => ':=',
                'value' => (string)$dataLimitBytes
            ]);
        }

        // 2. WISPr-Bandwidth-Max-Up: Upload speed in bits per second
        RadGroupReply::create([
            'groupname' => $groupname,
            'attribute' => 'WISPr-Bandwidth-Max-Up',
            'op' => ':=',
            'value' => (string)$uploadBps
        ]);

        // 3. WISPr-Bandwidth-Max-Down: Download speed in bits per second
        RadGroupReply::create([
            'groupname' => $groupname,
            'attribute' => 'WISPr-Bandwidth-Max-Down',
            'op' => ':=',
            'value' => (string)$downloadBps
        ]);

        // 4. Mikrotik-Rate-Limit: MikroTik specific rate limit
        $mikrotikRateLimit = "{$uploadKbps}K/{$downloadKbps}K";
        RadGroupReply::create([
            'groupname' => $groupname,
            'attribute' => 'Mikrotik-Rate-Limit',
            'op' => ':=',
            'value' => $mikrotikRateLimit
        ]);

        // 5. Idle-Timeout: 5 minutes (300 seconds) default
        RadGroupReply::create([
            'groupname' => $groupname,
            'attribute' => 'Idle-Timeout',
            'op' => ':=',
            'value' => '300'
        ]);

        // 6. Mikrotik-Address-List: Traffic management (based on package type)
        $addressList = $this->is_trial ? 'trial_users' : 'paid_users';
        RadGroupReply::create([
            'groupname' => $groupname,
            'attribute' => 'Mikrotik-Address-List',
            'op' => ':=',
            'value' => $addressList
        ]);

        // 7. Reply-Message: Welcome message
        RadGroupReply::create([
            'groupname' => $groupname,
            'attribute' => 'Reply-Message',
            'op' => ':=',
            'value' => "Welcome to {$this->name} package, %{User-Name}!"
        ]);

        // 8. Mikrotik-Group: Group name for MikroTik management
        RadGroupReply::create([
            'groupname' => $groupname,
            'attribute' => 'Mikrotik-Group',
            'op' => ':=',
            'value' => $groupname
        ]);
    }

    /**
     * Get data limit in bytes
     */
    private function getDataLimitInBytes()
    {
        if (!$this->data_limit) {
            return 0;
        }

        // Assuming data_limit is stored in MB
        return $this->data_limit * 1024 * 1024;
    }

    /**
     * Check if package is time-based (has duration)
     */
    private function isTimeBased()
    {
        return !empty($this->duration_type) && !empty($this->duration_value);
    }

    /**
     * Get package duration in seconds
     */
    private function getDurationInSeconds()
    {
        if (!$this->isTimeBased()) {
            return 0;
        }

        $value = $this->duration_value;
        
        switch ($this->duration_type) {
            case 'hourly':
                return $value * 3600;
            case 'daily':
                return $value * 86400;
            case 'weekly':
                return $value * 604800;
            case 'monthly':
                return $value * 2592000; // 30 days
            case 'yearly':
                return $value * 31536000; // 365 days
            default:
                return 0;
        }
    }

    public function updateRadiusGroup()
    {
        $this->setupRadiusGroup();

        // Update all existing users in this group
        $this->subscriptions->each(function ($subscription) {
            $subscription->updateRadiusUser();
        });

        return $this;
    }

    public function deleteRadiusGroup()
    {
        $groupName = $this->getGroupName();

        // Remove all users from this group first
        RadUserGroup::removeAllUsersFromGroup($groupName);

        // Clean up group attributes
        RadGroupCheck::cleanupGroup($groupName);
        RadGroupReply::cleanupGroup($groupName);

        return $this;
    }

    public function getGroupName()
    {
        return "package_{$this->id}";
    }

    public function getRadiusGroupName()
    {
        return $this->getGroupName();
    }

    public function syncAllSubscriptions()
    {
        $this->subscriptions->each(function ($subscription) {
            $subscription->updateRadiusUser();
            $subscription->syncRadiusStatus();
        });

        return $this;
    }

    public function hasActiveSubscriptions()
    {
        return $this->subscriptions()->where('status', 'active')->count() > 0;
    }
    public function ActiveSubscriptions()
    {
        return $this->subscriptions()->where('status', 'active');
    }
    public function getActiveUserCount()
    {
        return RadUserGroup::getGroupActiveUsers($this->getGroupName());
    }

    public function getTotalDataUsage()
    {
        return $this->subscriptions->sum(function ($subscription) {
            return $subscription->data_usage ?? 0;
        });
    }

    // Static method to setup RADIUS groups for all existing packages
    public static function setupAllRadiusGroups()
    {
        $packages = self::all();
        $setupCount = 0;
        
        foreach ($packages as $package) {
            $package->setupRadiusGroup();
            $setupCount++;
        }
        
        return $setupCount;
    }

    public function scopeByDurationType($query, $type)
    {
        return $query->where('duration_type', $type);
    }
}
