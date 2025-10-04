<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadGroupCheck extends Model
{
    protected $table = 'radgroupcheck';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'groupname',
        'attribute',
        'op',
        'value'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    // Relationships
    public function radUserGroups()
    {
        return $this->hasMany(RadUserGroup::class, 'groupname', 'groupname');
    }

    public function package()
    {
        if (str_starts_with($this->groupname, 'package_')) {
            $packageId = str_replace('package_', '', $this->groupname);
            return Package::find($packageId);
        }
        return null;
    }

    // Helper methods for common group attributes
    public static function setGroupSimultaneousUse($groupname, $limit)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Simultaneous-Use'],
            ['op' => ':=', 'value' => $limit]
        );
    }

    public static function setGroupLoginTime($groupname, $timeRestriction)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Login-Time'],
            ['op' => ':=', 'value' => $timeRestriction]
        );
    }

    public static function setGroupAuthType($groupname, $authType)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Auth-Type'],
            ['op' => ':=', 'value' => $authType]
        );
    }

    public static function setGroupCallingStationId($groupname, $macPattern)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Calling-Station-Id'],
            ['op' => ':=', 'value' => $macPattern]
        );
    }

    public static function setGroupExpiration($groupname, $date)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Expiration'],
            ['op' => ':=', 'value' => $date]
        );
    }

    public static function setGroupBandwidth($groupname, $downloadKbps = null, $uploadKbps = null)
    {
        // For MikroTik, we should use Mikrotik-Rate-Limit instead of WISPr attributes
        if ($uploadKbps !== null && $downloadKbps !== null) {
            // Convert Kbps to Mbps for proper MikroTik format
            $uploadMbps = round($uploadKbps / 1000, 1);
            $downloadMbps = round($downloadKbps / 1000, 1);
            
            return self::updateOrCreate(
                ['groupname' => $groupname, 'attribute' => 'Mikrotik-Rate-Limit'],
                ['op' => ':=', 'value' => "{$uploadMbps}M/{$downloadMbps}M"]
            );
        }

        // If only one value provided, we can't set Mikrotik-Rate-Limit (needs both)
        return null;
    }

    public static function setGroupDataLimit($groupname, $limitMB)
    {
        if ($limitMB !== null) {
            return self::updateOrCreate(
                ['groupname' => $groupname, 'attribute' => 'ChilliSpot-Max-Total-Octets'],
                ['op' => ':=', 'value' => $limitMB * 1024 * 1024] // Convert to bytes
            );
        }

        // Remove data limit if null
        return self::where('groupname', $groupname)
                  ->where('attribute', 'ChilliSpot-Max-Total-Octets')
                  ->delete();
    }

    public static function setGroupSessionTimeout($groupname, $timeoutSeconds)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Session-Timeout'],
            ['op' => ':=', 'value' => $timeoutSeconds]
        );
    }

    public static function setGroupIdleTimeout($groupname, $idleTimeoutSeconds)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Idle-Timeout'],
            ['op' => ':=', 'value' => $idleTimeoutSeconds]
        );
    }

    public static function setGroupServiceType($groupname, $serviceType = 'Framed-User')
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Service-Type'],
            ['op' => ':=', 'value' => $serviceType]
        );
    }

    // Package-specific group methods
    public static function setPackageGroupAttributes($packageId, $attributes)
    {
        $groupname = "package_{$packageId}";

        foreach ($attributes as $attribute => $value) {
            self::updateOrCreate(
                ['groupname' => $groupname, 'attribute' => $attribute],
                ['op' => ':=', 'value' => $value]
            );
        }
    }

    public static function setupPackageGroup($packageId, $options = [])
    {
        $groupname = "package_{$packageId}";

        // Set bandwidth limits if provided
        if (isset($options['bandwidth_download']) || isset($options['bandwidth_upload'])) {
            self::setGroupBandwidth(
                $groupname,
                $options['bandwidth_download'] ?? null,
                $options['bandwidth_upload'] ?? null
            );
        }

        // Set data limit if provided
        if (isset($options['data_limit'])) {
            self::setGroupDataLimit($groupname, $options['data_limit']);
        }

        // Set simultaneous users limit
        if (isset($options['simultaneous_users'])) {
            self::setGroupSimultaneousUse($groupname, $options['simultaneous_users']);
        }

        // Set session timeout if provided
        if (isset($options['session_timeout'])) {
            self::setGroupSessionTimeout($groupname, $options['session_timeout']);
        }

        // Set idle timeout if provided
        if (isset($options['idle_timeout'])) {
            self::setGroupIdleTimeout($groupname, $options['idle_timeout']);
        }

        // Set VLAN if provided
        if (isset($options['vlan_id'])) {
            self::updateOrCreate(
                ['groupname' => $groupname, 'attribute' => 'Tunnel-Type'],
                ['op' => ':=', 'value' => 'VLAN']
            );
            
            self::updateOrCreate(
                ['groupname' => $groupname, 'attribute' => 'Tunnel-Medium-Type'],
                ['op' => ':=', 'value' => 'IEEE-802']
            );
            
            self::updateOrCreate(
                ['groupname' => $groupname, 'attribute' => 'Tunnel-Private-Group-Id'],
                ['op' => ':=', 'value' => $options['vlan_id']]
            );
        }

        return $groupname;
    }

    public static function removePackageGroupAttribute($packageId, $attribute)
    {
        $groupname = "package_{$packageId}";
        return self::where('groupname', $groupname)
                  ->where('attribute', $attribute)
                  ->delete();
    }

    // Utility methods
    public static function getGroupAttributes($groupname)
    {
        return self::where('groupname', $groupname)->get()->pluck('value', 'attribute');
    }

    public static function getPackageGroupAttributes($packageId)
    {
        $groupname = "package_{$packageId}";
        return self::getGroupAttributes($groupname);
    }

    public static function cleanupGroup($groupname)
    {
        return self::where('groupname', $groupname)->delete();
    }

    public static function cleanupPackageGroup($packageId)
    {
        $groupname = "package_{$packageId}";
        return self::cleanupGroup($groupname);
    }

    public static function getGroupsWithAttribute($attribute)
    {
        return self::where('attribute', $attribute)
                  ->get()
                  ->groupBy('groupname')
                  ->map(function ($items) {
                      return $items->pluck('value', 'op');
                  });
    }

    public static function getPackageGroups()
    {
        return self::where('groupname', 'like', 'package_%')
                  ->get()
                  ->groupBy('groupname');
    }

    // Group management for business logic
    public static function setupTrialGroup()
    {
        $groupname = 'trial_users';

        // Set login time restriction (example: 9 AM to 9 PM)
        self::setGroupLoginTime($groupname, 'Al0900-2100');

        // Set simultaneous use limit
        self::setGroupSimultaneousUse($groupname, 1);

        return $groupname;
    }

    public static function setupPremiumGroup()
    {
        $groupname = 'premium_users';

        // No time restrictions for premium users
        self::setGroupLoginTime($groupname, 'Al0000-2359');

        // Higher simultaneous use limit
        self::setGroupSimultaneousUse($groupname, 3);

        return $groupname;
    }

    public static function setupStudentGroup()
    {
        $groupname = 'student_users';

        // Restricted hours (example: 6 AM to 10 PM)
        self::setGroupLoginTime($groupname, 'Al0600-2200');

        // Limited simultaneous use
        self::setGroupSimultaneousUse($groupname, 1);

        return $groupname;
    }

    public static function getGroupStats()
    {
        return self::groupBy('groupname')
                  ->selectRaw('groupname, COUNT(*) as attribute_count')
                  ->get();
    }

    // Scopes
    public function scopeByGroupname($query, $groupname)
    {
        return $query->where('groupname', $groupname);
    }

    public function scopeByAttribute($query, $attribute)
    {
        return $query->where('attribute', $attribute);
    }

    public function scopePackageGroups($query)
    {
        return $query->where('groupname', 'like', 'package_%');
    }

    public function scopeCustomGroups($query)
    {
        return $query->where('groupname', 'not like', 'package_%');
    }

    public function scopeSimultaneousUse($query)
    {
        return $query->where('attribute', 'Simultaneous-Use');
    }

    public function scopeLoginTime($query)
    {
        return $query->where('attribute', 'Login-Time');
    }

    public function scopeAuthType($query)
    {
        return $query->where('attribute', 'Auth-Type');
    }
}
