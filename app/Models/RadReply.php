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
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit'],
            ['op' => ':=', 'value' => "{$uploadKbps}k/{$downloadKbps}k"]
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
