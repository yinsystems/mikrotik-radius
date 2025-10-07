<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RadReply Model - RADIUS Reply Attributes
 * 
 * REQUIRED MIKROTIK CONFIGURATION:
 * To implement portal-only access, add these firewall rules to your MikroTik router:
 * 
 * /ip firewall filter
 * add action=accept chain=forward src-address-list=portal-only-users dst-address=158.220.97.239 comment="Allow portal access"
 * add action=accept chain=forward src-address-list=portal-only-users protocol=udp dst-port=53 comment="Allow DNS"
 * add action=drop chain=forward src-address-list=portal-only-users comment="Block other traffic for portal-only users"
 * add action=accept chain=forward src-address-list=subscribed-users comment="Allow full access for subscribers"
 * 
 * Address lists are managed automatically by RADIUS:
 * - portal-only-users: Unsubscribed users (limited to portal + DNS)
 * - subscribed-users: Active subscribers (full internet access)
 */
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

    public static function setReplyMessage($username, $message)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Reply-Message'],
            ['op' => ':=', 'value' => $message]
        );
    }

    public static function setRedirectionURL($username, $url)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'WISPr-Redirection-URL'],
            ['op' => ':=', 'value' => $url]
        );
    }

    /**
     * Set portal-only access for unsubscribed users
     */
    public static function setPortalOnlyAccess($username, $portalIP = '158.220.97.239')
    {
        // Add user to portal-only address list
        self::setMikrotikAddressList($username, 'portal-only-users');
        
        // Set filter for portal-only access
        self::setFilterId($username, 'portal-only-filter');
        
        // Set reduced bandwidth for portal access
        self::setBandwidthLimit($username, 512, 512); // 512 Kbps up/down
        
        // Set informative message
        self::setReplyMessage($username, 'Limited access: Portal only. Subscribe to a package for full internet access.');
        
        return true;
    }

    /**
     * Set full internet access for subscribed users
     */
    public static function setFullInternetAccess($username, $uploadKbps = null, $downloadKbps = null)
    {
        // Add user to subscribed users list
        self::setMikrotikAddressList($username, 'subscribed-users');
        
        // Remove portal-only filter
        self::where('username', $username)
            ->where('attribute', 'Filter-Id')
            ->where('value', 'portal-only-filter')
            ->delete();
            
        // Set bandwidth limits if provided
        if ($uploadKbps && $downloadKbps) {
            self::setBandwidthLimit($username, $uploadKbps, $downloadKbps);
        }
        
        // Remove restrictive reply message
        self::where('username', $username)
            ->where('attribute', 'Reply-Message')
            ->delete();
            
        return true;
    }

    /**
     * Remove user from all access lists (for cleanup)
     */
    public static function removeFromAllAccessLists($username)
    {
        // Remove from address lists
        self::where('username', $username)
            ->where('attribute', 'Mikrotik-Address-List')
            ->delete();
            
        // Remove filter restrictions
        self::where('username', $username)
            ->where('attribute', 'Filter-Id')
            ->delete();
            
        return true;
    }

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
