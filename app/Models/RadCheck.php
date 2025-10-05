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

    public static function setMaxAllSession($username, $seconds)
    {
        return self::updateOrCreate(
            ['username' => $username, 'attribute' => 'Max-All-Session'],
            ['op' => ':=', 'value' => $seconds]
        );
    }

    public static function setUserMaxAllSession($username, $seconds)
    {
        return self::setMaxAllSession($username, $seconds);
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
        // Remove any existing blocking
        self::where('username', $username)
            ->where('attribute', 'Auth-Type')
            ->where('value', 'Reject')
            ->delete();
        
        // Set very restrictive bandwidth (1 kbps) to allow minimal access
        \App\Models\RadReply::updateOrCreate(
            ['username' => $username, 'attribute' => 'WISPr-Bandwidth-Max-Down'],
            ['op' => ':=', 'value' => '1024'] // 1 kbps
        );
        
        \App\Models\RadReply::updateOrCreate(
            ['username' => $username, 'attribute' => 'WISPr-Bandwidth-Max-Up'],
            ['op' => ':=', 'value' => '1024'] // 1 kbps
        );
        
        // Set short session timeout (5 minutes)
        \App\Models\RadReply::updateOrCreate(
            ['username' => $username, 'attribute' => 'Session-Timeout'],
            ['op' => ':=', 'value' => '300'] // 5 minutes
        );
        
        // Set redirection URL to payment portal
        \App\Models\RadReply::updateOrCreate(
            ['username' => $username, 'attribute' => 'WISPr-Redirection-URL'],
            ['op' => ':=', 'value' => 'https://jaynet.vasgh.com/portal']
        );
        
        // Set informative message
        \App\Models\RadReply::updateOrCreate(
            ['username' => $username, 'attribute' => 'Reply-Message'],
            ['op' => ':=', 'value' => 'Your package has expired. You will be redirected to renew your subscription.']
        );
        
        return true;
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
