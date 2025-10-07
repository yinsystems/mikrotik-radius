<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadReply extends Model
{
    protected $table = 'radreply';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'attribute',
        'op',
        'value'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    // Relationships
    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'username', 'username');
    }

    public function customer()
    {
        return $this->hasOneThrough(Customer::class, Subscription::class, 'username', 'id', 'username', 'customer_id');
    }

    // Helper methods for Mikrotik attributes
    public static function setBandwidthLimit($username, $uploadKbps, $downloadKbps)
    {
        // Convert Kbps to Mbps for proper MikroTik format
        $uploadMbps = round($uploadKbps / 1000, 1);
        $downloadMbps = round($downloadKbps / 1000, 1);
        
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit'],
            ['op' => ':=', 'value' => "{$uploadMbps}M/{$downloadMbps}M"]
        );
    }

    public static function setDataLimit($username, $limitBytes)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Mikrotik-Total-Limit'],
            ['op' => ':=', 'value' => $limitBytes]
        );
    }

    public static function setSessionTimeout($username, $seconds)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Session-Timeout'],
            ['op' => ':=', 'value' => $seconds]
        );
    }

    public static function setIdleTimeout($username, $seconds)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Idle-Timeout'],
            ['op' => ':=', 'value' => $seconds]
        );
    }

    public static function setVlan($username, $vlanId)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Tunnel-Private-Group-Id'],
            ['op' => ':=', 'value' => $vlanId]
        );
    }

    public static function setFramedIpAddress($username, $ipAddress)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Framed-IP-Address'],
            ['op' => ':=', 'value' => $ipAddress]
        );
    }

    public static function setFilterId($username, $filterId)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Filter-Id'],
            ['op' => ':=', 'value' => $filterId]
        );
    }

    public static function setMikrotikAddressList($username, $listName)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Mikrotik-Address-List'],
            ['op' => ':=', 'value' => $listName]
        );
    }

    // Address List Management for DNS Hijacking
    public static function addToDataExhaustedList($username)
    {
        return self::setMikrotikAddressList($username, 'data-exhausted-users');
    }

    public static function addToSubscribedUsersList($username)
    {
        return self::setMikrotikAddressList($username, 'subscribed-users');
    }

    public static function removeFromAddressList($username, $listName = null)
    {
        $query = self::where('username', $username)
                    ->where('attribute', 'Mikrotik-Address-List');
        
        if ($listName) {
            $query->where('value', $listName);
        }
        
        return $query->delete();
    }

    public static function getUserAddressList($username)
    {
        return self::where('username', $username)
                  ->where('attribute', 'Mikrotik-Address-List')
                  ->value('value');
    }

    /*
     * MikroTik DNS Hijacking Configuration
     * 
     * Execute these commands on your MikroTik router to enable DNS hijacking for data-exhausted users:
     * 
     * 1. Create address lists:
     * /ip firewall address-list
     * add list=data-exhausted-users comment="Users with expired/no data"
     * add list=subscribed-users comment="Users with active subscriptions"
     * 
     * 2. Allow portal domain to resolve normally (prevents redirect loops):
     * /ip dns static
     * add name="jaynet.vasgh.com" address=158.220.97.239 ttl=5m comment="Portal domain - no hijacking"
     * 
     * 3. Hijack DNS for data-exhausted users only:
     * /ip dns static
     * add name=".*" address=158.220.97.239 ttl=30s comment="Hijack DNS for data exhausted users"
     * 
     * 4. Configure firewall rules for selective DNS hijacking:
     * /ip firewall nat
     * add chain=dstnat src-address-list=data-exhausted-users protocol=udp dst-port=53 \
     *     action=redirect to-addresses=158.220.97.239 to-ports=53 \
     *     comment="Redirect DNS queries for data exhausted users"
     * 
     * 5. Allow normal internet for subscribed users:
     * /ip firewall filter
     * add chain=forward src-address-list=subscribed-users action=accept \
     *     comment="Allow full internet for subscribed users"
     * 
     * 6. Block non-portal traffic for data-exhausted users:
     * /ip firewall filter
     * add chain=forward src-address-list=data-exhausted-users \
     *     dst-address=!158.220.97.239 protocol=tcp dst-port=!80,443 action=drop \
     *     comment="Block non-web traffic for data exhausted users"
     * 
     * 7. Allow portal access for data-exhausted users:
     * /ip firewall filter
     * add chain=forward src-address-list=data-exhausted-users \
     *     dst-address=158.220.97.239 action=accept \
     *     comment="Allow portal access for data exhausted users"
     * 
     * How it works:
     * - Subscribed users: Normal internet access, no DNS hijacking
     * - Data-exhausted users: DNS hijacked to portal, limited web access only
     * - Portal domain (jaynet.vasgh.com) resolves normally to prevent loops
     * - All other domains resolve to portal IP for data-exhausted users
     */

    // Cisco/Generic attributes
    public static function setCiscoAvPair($username, $avPair)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Cisco-AVPair'],
            ['op' => ':=', 'value' => $avPair]
        );
    }

    // Utility methods
    public static function getUserAttributes($username)
    {
        return self::where('username', $username)->get()->pluck('value', 'attribute');
    }

    public static function getUserBandwidthLimit($username)
    {
        $rateLimit = self::where('username', $username)
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

    public static function getUserDataLimit($username)
    {
        return self::where('username', $username)
                  ->where('attribute', 'Mikrotik-Total-Limit')
                  ->value('value');
    }

    public static function getUserSessionTimeout($username)
    {
        return self::where('username', $username)
                  ->where('attribute', 'Session-Timeout')
                  ->value('value');
    }

    public static function cleanupUser($username)
    {
        return self::where('username', $username)->delete();
    }

    // Scopes
    public function scopeByUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeByAttribute($query, $attribute)
    {
        return $query->where('attribute', $attribute);
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
}
