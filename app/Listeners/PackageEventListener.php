<?php

namespace App\Listeners;

use App\Models\Package;

class PackageEventListener
{
    public function created(Package $package)
    {
        // Setup RADIUS group for new package
        $package->setupRadiusGroup();
    }

    public function updated(Package $package)
    {
        // Check what changed
        $dirty = $package->getDirty();
        
        // If any RADIUS-related attributes changed, update group
        $radiusAttributes = [
            'bandwidth_upload',
            'bandwidth_download', 
            'data_limit',
            'simultaneous_users',
            'vlan_id',
            'idle_timeout'
        ];
        
        if (array_intersect(array_keys($dirty), $radiusAttributes)) {
            $package->updateRadiusGroup();
        }
    }

    public function deleting(Package $package)
    {
        // Don't allow deletion if there are active subscriptions
        if ($package->hasActiveSubscriptions()) {
            throw new \Exception('Cannot delete package with active subscriptions');
        }
        
        // Clean up RADIUS group
        $package->deleteRadiusGroup();
    }
}