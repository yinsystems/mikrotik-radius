<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadGroupReply extends Model
{
    protected $table = 'radgroupreply';
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

    // Helper methods for common group reply attributes
    public static function setGroupBandwidth($groupname, $uploadKbps, $downloadKbps)
    {
        // Convert Kbps to Mbps for proper MikroTik format
        $uploadMbps = round($uploadKbps / 1000, 1);
        $downloadMbps = round($downloadKbps / 1000, 1);
        
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Mikrotik-Rate-Limit'],
            ['op' => '=', 'value' => "{$uploadMbps}M/{$downloadMbps}M"]
        );
    }

    public static function setGroupDataLimit($groupname, $limitBytes)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Mikrotik-Total-Limit'],
            ['op' => '=', 'value' => $limitBytes]
        );
    }

    public static function setGroupSessionTimeout($groupname, $seconds)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Session-Timeout'],
            ['op' => '=', 'value' => $seconds]
        );
    }

    public static function setGroupIdleTimeout($groupname, $seconds)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Idle-Timeout'],
            ['op' => '=', 'value' => $seconds]
        );
    }

    public static function setGroupVlan($groupname, $vlanId)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Tunnel-Private-Group-Id'],
            ['op' => ':=', 'value' => $vlanId]
        );
    }

    public static function setGroupFilterId($groupname, $filterId)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Filter-Id'],
            ['op' => ':=', 'value' => $filterId]
        );
    }

    public static function setGroupFramedIpPool($groupname, $poolName)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Framed-Pool'],
            ['op' => ':=', 'value' => $poolName]
        );
    }

    public static function setGroupMikrotikAddressList($groupname, $listName)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Mikrotik-Address-List'],
            ['op' => ':=', 'value' => $listName]
        );
    }

    // Cisco-specific attributes
    public static function setGroupCiscoAvPair($groupname, $avPair)
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Cisco-AVPair'],
            ['op' => ':=', 'value' => $avPair]
        );
    }

    public static function setGroupServiceType($groupname, $serviceType = 'Framed-User')
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Service-Type'],
            ['op' => '=', 'value' => $serviceType]
        );
    }

    public static function setGroupFramedProtocol($groupname, $protocol = 'PPP')
    {
        return self::updateOrCreate(
            ['groupname' => $groupname, 'attribute' => 'Framed-Protocol'],
            ['op' => '=', 'value' => $protocol]
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

    public static function setupPackageGroupFromPackage(Package $package)
    {
        $groupname = "package_{$package->id}";

        // Set bandwidth limits
        if ($package->bandwidth_upload && $package->bandwidth_download) {
            self::setGroupBandwidth($groupname, $package->bandwidth_upload, $package->bandwidth_download);
        }

        // Set data limits
        if ($package->data_limit) {
            self::setGroupDataLimit($groupname, $package->data_limit * 1024 * 1024); // Convert MB to bytes
        }

        // Set session timeout for hourly packages
        if ($package->duration_type === 'hourly') {
            self::setGroupSessionTimeout($groupname, $package->duration_value * 3600);
        }

        // Set VLAN if specified
        if ($package->vlan_id) {
            self::setGroupVlan($groupname, $package->vlan_id);
        }

        // Set essential MikroTik authentication attributes
        self::setGroupServiceType($groupname, 'Framed-User');
        self::setGroupFramedProtocol($groupname, 'PPP');

        // Set Mikrotik address list for package categorization
        self::setGroupMikrotikAddressList($groupname, "package_{$package->id}_users");

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

    public static function getGroupBandwidthLimit($groupname)
    {
        $rateLimit = self::where('groupname', $groupname)
                        ->where('attribute', 'Mikrotik-Rate-Limit')
                        ->value('value');

        if ($rateLimit && preg_match('/(\d+)k\/(\d+)k/', $rateLimit, $matches)) {
            return [
                'upload' => (int)$matches[1],
                'download' => (int)$matches[2]
            ];
        }

        return null;
    }

    public static function getGroupDataLimit($groupname)
    {
        return self::where('groupname', $groupname)
                  ->where('attribute', 'Mikrotik-Total-Limit')
                  ->value('value');
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

    // Preset group configurations
    public static function setupBasicGroup($groupname)
    {
        // Basic package: 1 Mbps, 1 GB, 4 hours session
        self::setGroupBandwidth($groupname, 1024, 1024);
        self::setGroupDataLimit($groupname, 1024 * 1024 * 1024); // 1 GB
        self::setGroupSessionTimeout($groupname, 4 * 3600); // 4 hours
        self::setGroupIdleTimeout($groupname, 30 * 60); // 30 minutes

        return $groupname;
    }

    public static function setupPremiumGroup($groupname)
    {
        // Premium package: 10 Mbps, unlimited data, no session timeout
        self::setGroupBandwidth($groupname, 10240, 10240);
        // No data limit for premium
        self::setGroupIdleTimeout($groupname, 60 * 60); // 1 hour idle

        return $groupname;
    }

    public static function setupTrialGroup($groupname)
    {
        // Trial package: 512 Kbps, 100 MB, 1 hour session
        self::setGroupBandwidth($groupname, 512, 512);
        self::setGroupDataLimit($groupname, 100 * 1024 * 1024); // 100 MB
        self::setGroupSessionTimeout($groupname, 1 * 3600); // 1 hour
        self::setGroupIdleTimeout($groupname, 15 * 60); // 15 minutes

        return $groupname;
    }

    public static function getGroupStats()
    {
        return self::groupBy('groupname')
                  ->selectRaw('groupname, COUNT(*) as attribute_count')
                  ->get();
    }

    public static function getPackageGroups()
    {
        return self::where('groupname', 'like', 'package_%')
                  ->get()
                  ->groupBy('groupname');
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

    public function scopeBandwidthLimits($query)
    {
        return $query->where('attribute', 'Mikrotik-Rate-Limit');
    }

    public function scopeDataLimits($query)
    {
        return $query->where('attribute', 'Mikrotik-Total-Limit');
    }

    public function scopeSessionTimeouts($query)
    {
        return $query->where('attribute', 'Session-Timeout');
    }

    public function scopeVlanSettings($query)
    {
        return $query->where('attribute', 'Tunnel-Private-Group-Id');
    }
}
