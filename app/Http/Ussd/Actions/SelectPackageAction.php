<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\SelectPackageState;
use App\Models\Package;
use Sparors\Ussd\Action;

class SelectPackageAction extends Action
{
    public function run(): string
    {
        // Initialize pagination if not already set
        if (!$this->record->get('packages_page')) {
            $packagesPerPage = config('ussd.packages_per_page', 3);

            // Load first page of packages
            $packages = Package::where('is_active', true)
                ->where('is_trial', false)
                ->orderBy('price')
                ->take($packagesPerPage)
                ->get();

            // Get total count for pagination
            $totalPackages = Package::where('is_active', true)->orderBy('priority', 'asc')
                ->where('is_trial', false)
                ->count();

            $totalPages = ceil($totalPackages / $packagesPerPage);

            $this->record->setMultiple([
                'packages' => $packages,
                'packages_page' => 1,
                'total_packages' => $totalPackages,
                'total_pages' => $totalPages,
                'packages_per_page' => $packagesPerPage
            ]);
        }

        return SelectPackageState::class;
    }
}
