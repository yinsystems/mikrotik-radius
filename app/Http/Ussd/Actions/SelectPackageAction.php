<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\SelectPackageState;
use App\Models\Package;
use Sparors\Ussd\Action;

class SelectPackageAction extends Action
{
    public function run(): string
    {
        $selectedPackageType = $this->record->get('selected_package_type');
        
        // Always reset packages when coming from package type selection
        // to ensure fresh data based on selected type
        $packagesPerPage = config('ussd.packages_per_page', 3);

        // Build query based on selected package type
        $query = Package::where('is_active', true)
            ->where('is_trial', false);

        if ($selectedPackageType === 'General') {
            // Show packages without specific type
            $query->where(function($q) {
                $q->whereNull('package_type')->orWhere('package_type', '');
            });
        } elseif ($selectedPackageType) {
            // Show packages of specific type (case insensitive)
            $query->whereRaw('LOWER(package_type) = ?', [strtolower($selectedPackageType)]);
        }

        // Load first page of packages
        $packages = $query->orderBy('priority', 'asc')
            ->orderBy('price')
            ->take($packagesPerPage)
            ->get();

        // Get total count for pagination
        $totalPackagesQuery = Package::where('is_active', true)
            ->where('is_trial', false);
            
        if ($selectedPackageType === 'General') {
            $totalPackagesQuery->where(function($q) {
                $q->whereNull('package_type')->orWhere('package_type', '');
            });
        } elseif ($selectedPackageType) {
            $totalPackagesQuery->whereRaw('LOWER(package_type) = ?', [strtolower($selectedPackageType)]);
        }

        $totalPackages = $totalPackagesQuery->count();
        $totalPages = ceil($totalPackages / $packagesPerPage);

        $this->record->setMultiple([
            'packages' => $packages,
            'packages_page' => 1,
            'total_packages' => $totalPackages,
            'total_pages' => $totalPages,
            'packages_per_page' => $packagesPerPage
        ]);

        return SelectPackageState::class;
    }
}
