<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadUserGroup extends Model
{
    protected $table = 'radusergroup';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'groupname',
        'priority'
    ];

    protected $casts = [
        'id' => 'integer',
        'priority' => 'integer'
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

    public function radGroupCheck()
    {
        return $this->hasMany(RadGroupCheck::class, 'groupname', 'groupname');
    }

    public function radGroupReply()
    {
        return $this->hasMany(RadGroupReply::class, 'groupname', 'groupname');
    }

    // Helper methods
    public function isPackageGroup()
    {
        return str_starts_with($this->groupname, 'package_');
    }

    public function getPackageId()
    {
        if ($this->isPackageGroup()) {
            return (int) str_replace('package_', '', $this->groupname);
        }
        return null;
    }

    public function getPackage()
    {
        if ($packageId = $this->getPackageId()) {
            return Package::find($packageId);
        }
        return null;
    }

    // Static methods for group management
    public static function assignToPackageGroup($username, $packageId, $priority = 1)
    {
        return self::updateOrCreate(
            ['username' => $username, 'groupname' => "package_{$packageId}"],
            ['priority' => $priority]
        );
    }

    public static function assignToGroup($username, $groupname, $priority = 1)
    {
        return self::updateOrCreate(
            ['username' => $username, 'groupname' => $groupname],
            ['priority' => $priority]
        );
    }

    public static function removeFromGroup($username, $groupname)
    {
        return self::where('username', $username)
                  ->where('groupname', $groupname)
                  ->delete();
    }

    public static function removeFromAllGroups($username)
    {
        return self::where('username', $username)->delete();
    }

    public static function removeAllUsersFromGroup($groupname)
    {
        return self::where('groupname', $groupname)->delete();
    }

    public static function removeAllUsersFromPackageGroup($packageId)
    {
        return self::removeAllUsersFromGroup("package_{$packageId}");
    }

    public static function removeFromPackageGroups($username)
    {
        return self::where('username', $username)
                  ->where('groupname', 'like', 'package_%')
                  ->delete();
    }

    public static function getUserGroups($username)
    {
        return self::where('username', $username)
                  ->orderBy('priority')
                  ->get();
    }

    public static function getGroupUsers($groupname)
    {
        return self::where('groupname', $groupname)
                  ->orderBy('priority')
                  ->get();
    }

    public static function getUserPackageGroups($username)
    {
        return self::where('username', $username)
                  ->where('groupname', 'like', 'package_%')
                  ->get();
    }

    public static function changeUserPackage($username, $oldPackageId, $newPackageId, $priority = 1)
    {
        // Remove from old package group
        self::removeFromGroup($username, "package_{$oldPackageId}");

        // Add to new package group
        return self::assignToPackageGroup($username, $newPackageId, $priority);
    }

    public static function transferUsersToGroup($fromGroupname, $toGroupname, $priority = 1)
    {
        $users = self::where('groupname', $fromGroupname)->get();
        $transferCount = 0;

        foreach ($users as $userGroup) {
            // Add to new group
            self::assignToGroup($userGroup->username, $toGroupname, $priority);
            
            // Remove from old group
            $userGroup->delete();
            
            $transferCount++;
        }

        return $transferCount;
    }

    public static function bulkAssignToGroup($usernames, $groupname, $priority = 1)
    {
        $assigned = 0;
        
        foreach ($usernames as $username) {
            self::assignToGroup($username, $groupname, $priority);
            $assigned++;
        }

        return $assigned;
    }

    public static function bulkRemoveFromGroup($usernames, $groupname)
    {
        return self::whereIn('username', $usernames)
                  ->where('groupname', $groupname)
                  ->delete();
    }

    public static function getPackageGroupStats()
    {
        return self::where('groupname', 'like', 'package_%')
                  ->groupBy('groupname')
                  ->selectRaw('groupname, COUNT(*) as user_count')
                  ->get()
                  ->map(function ($item) {
                      $packageId = str_replace('package_', '', $item->groupname);
                      $package = Package::find($packageId);

                      return [
                          'package_id' => $packageId,
                          'package_name' => $package->name ?? 'Unknown Package',
                          'user_count' => $item->user_count,
                          'groupname' => $item->groupname
                      ];
                  });
    }

    public static function getGroupStats($groupname = null)
    {
        $query = self::query();

        if ($groupname) {
            $query->where('groupname', $groupname);
        }

        return $query->groupBy('groupname')
                    ->selectRaw('groupname, COUNT(*) as user_count, MIN(priority) as min_priority, MAX(priority) as max_priority')
                    ->get();
    }

    public static function cleanupOrphanedGroups()
    {
        // Remove users from package groups where the package no longer exists
        $packageGroups = self::where('groupname', 'like', 'package_%')->get();
        $removedCount = 0;

        foreach ($packageGroups as $userGroup) {
            $packageId = str_replace('package_', '', $userGroup->groupname);
            if (!Package::find($packageId)) {
                $userGroup->delete();
                $removedCount++;
            }
        }

        return $removedCount;
    }

    // Scopes
    public function scopeByUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeByGroupname($query, $groupname)
    {
        return $query->where('groupname', $groupname);
    }

    public function scopePackageGroups($query)
    {
        return $query->where('groupname', 'like', 'package_%');
    }

    public function scopeCustomGroups($query)
    {
        return $query->where('groupname', 'not like', 'package_%');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', 1);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority');
    }
}
