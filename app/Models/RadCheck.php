<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadCheck extends Model
{
    protected $table = 'radcheck';
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
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'username', 'username');
    }

    public function subscription()
    {
        return $this->hasOneThrough(Subscription::class, Customer::class, 'username', 'customer_id', 'username', 'id');
    }

    // Helper methods for common attributes
    public static function setPassword($username, $password)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Cleartext-Password'],
            ['op' => ':=', 'value' => $password]
        );
    }

    public static function setExpiration($username, $date)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Expiration'],
            ['op' => ':=', 'value' => $date]
        );
    }

    public static function setSessionTimeout($username, $seconds)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Session-Timeout'],
            ['op' => ':=', 'value' => $seconds]
        );
    }

    public static function removeExpiration($username)
    {
        return self::where('username', $username)
                  ->where('attribute', 'Expiration')
                  ->delete();
    }

    public static function setSimultaneousUse($username, $limit)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Simultaneous-Use'],
            ['op' => ':=', 'value' => $limit]
        );
    }

    public static function blockUser($username, $reason = null)
    {
        // Set Auth-Type to Reject
        $result = self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Auth-Type'],
            ['op' => ':=', 'value' => 'Reject']
        );
        
        // Add Reply-Message if reason provided
        if ($reason) {
            \App\Models\RadReply::updateOrCreate(
                ['username' => $username, 'attribute' => 'Reply-Message'],
                ['op' => ':=', 'value' => $reason]
            );
        }
        
        return $result;
    }

    public static function blockUserForExpiration($username)
    {
        return self::blockUser(
            $username, 
            'Your package has expired. Please login to your account and subscribe to a new package to continue.'
        );
    }

    public static function blockUserForSuspension($username)
    {
        return self::blockUser(
            $username, 
            'Your account has been suspended. Please contact support for assistance.'
        );
    }

    public static function redirectUserForExpiration($username)
    {
        // For expired users, use portal-only mode instead of allowing full access
        // This will place them in the portal-only-users firewall group for 5 minutes only
        return self::setPortalOnlyMode($username);
    }

    /**
     * Set portal-only mode for unsubscribed users (REJECT authentication)
     * Portal access is handled by MikroTik firewall rules, not RADIUS
     * 
     * How it works:
     * 1. RADIUS rejects authentication (Auth-Type Reject)
     * 2. User cannot establish internet connection via RADIUS
     * 3. MikroTik firewall detects unauthenticated user
     * 4. Firewall places them in "portal-only-users" address list
     * 5. Firewall rules allow only portal access for this address list
     */
    public static function setPortalOnlyMode($username)
    {
        // Set Auth-Type to Reject - they cannot authenticate via RADIUS
        self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Auth-Type'],
            ['op' => ':=', 'value' => 'Reject']
        );
        
        // Set portal-only access message
        \App\Models\RadReply::updateOrCreate(
            ['username' => $username, 'attribute' => 'Reply-Message'],
            ['op' => ':=', 'value' => 'Please renew your subscription to continue using the internet.']
        );
        
        // Place user in portal-only MikroTik address list (via RadReply for firewall rules)
        \App\Models\RadReply::setPortalOnlyAccess($username);
        
        return true;
    }

    /**
     * Set full access mode for subscribed users
     */
    public static function setFullAccessMode($username, $sessionTimeoutSeconds = null)
    {
        // Remove any existing blocking
        self::where('username', $username)
            ->where('attribute', 'Auth-Type')
            ->where('value', 'Reject')
            ->delete();
        
        // Remove portal-only session timeout if no specific timeout provided
        if ($sessionTimeoutSeconds) {
            self::setSessionTimeout($username, $sessionTimeoutSeconds);
        } else {
            // Remove session timeout for unlimited sessions
            self::where('username', $username)
                ->where('attribute', 'Session-Timeout')
                ->delete();
        }
        
        // Set full internet access via RadReply
        \App\Models\RadReply::setFullInternetAccess($username);
        
        return true;
    }

    /**
     * Check if user is in portal-only mode
     */
    public static function isInPortalOnlyMode($username)
    {
        // Portal-only mode is indicated by Auth-Type Reject with portal-only filter
        $hasAuthReject = self::where('username', $username)
            ->where('attribute', 'Auth-Type')
            ->where('value', 'Reject')
            ->exists();
            
        $hasPortalFilter = \App\Models\RadReply::where('username', $username)
            ->where('attribute', 'Filter-Id')
            ->where('value', 'portal-only-filter')
            ->exists();
            
        return $hasAuthReject && $hasPortalFilter;
    }

    public static function unblockUser($username)
    {
        // Remove Auth-Type Reject
        $result = self::where('username', $username)
                  ->where('attribute', 'Auth-Type')
                  ->where('value', 'Reject')
                  ->delete();
        
        // Remove any restrictive Reply attributes for expired users
        \App\Models\RadReply::where('username', $username)
               ->whereIn('attribute', [
                   'Reply-Message',
                   'WISPr-Redirection-URL',
                   'WISPr-Bandwidth-Max-Down',
                   'WISPr-Bandwidth-Max-Up',
                   'Session-Timeout'
               ])
               ->delete();
        
        return $result;
    }

    public static function setLoginTime($username, $timeRestriction)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Login-Time'],
            ['op' => ':=', 'value' => $timeRestriction]
        );
    }

    public static function setCallingStationId($username, $macAddress)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Calling-Station-Id'],
            ['op' => ':=', 'value' => $macAddress]
        );
    }

    // Utility methods
    public static function getUserAttributes($username)
    {
        return self::where('username', $username)->get()->pluck('value', 'attribute');
    }

    public static function isUserBlocked($username)
    {
        return self::where('username', $username)
                  ->where('attribute', 'Auth-Type')
                  ->where('value', 'Reject')
                  ->exists();
    }

    public static function getUserExpiration($username)
    {
        $expiration = self::where('username', $username)
                         ->where('attribute', 'Expiration')
                         ->value('value');
        
        return $expiration ? \Carbon\Carbon::createFromFormat('M d Y H:i', $expiration) : null;
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

    public function scopeBlocked($query)
    {
        return $query->where('attribute', 'Auth-Type')->where('value', 'Reject');
    }

    public function scopeExpired($query)
    {
        return $query->where('attribute', 'Expiration')
                    ->whereRaw("STR_TO_DATE(value, '%b %d %Y %H:%i') < NOW()");
    }
}
