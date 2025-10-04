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
        
        // Setup basic group checks
        RadGroupCheck::setGroupBandwidth($groupname, $this->bandwidth_upload, $this->bandwidth_download);
        RadGroupCheck::setGroupDataLimit($groupname, $this->data_limit);
        RadGroupCheck::setGroupSimultaneousUse($groupname, $this->simultaneous_users);
        
        // Setup essential authentication attributes
        RadGroupCheck::setGroupAuthType($groupname, 'Local');
        RadGroupCheck::setGroupServiceType($groupname, 'Framed-User');

        // Setup group replies
        RadGroupReply::setupPackageGroupFromPackage($this);

        return $this;
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
