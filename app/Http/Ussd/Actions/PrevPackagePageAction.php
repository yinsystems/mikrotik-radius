<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\SelectPackageState;
use App\Models\Package;
use Sparors\Ussd\Action;

class PrevPackagePageAction extends Action
{
    public function run(): string
    {
        $currentPage = $this->record->get('packages_page', 1);
        $packagesPerPage = $this->record->get('packages_per_page', 3);
        $selectedPackageType = $this->record->get('selected_package_type');
        
        // Move to previous page if possible
        if ($currentPage > 1) {
            $prevPage = $currentPage - 1;
            
            // Build query based on selected package type
            $query = Package::where('is_active', true)
                ->where('is_trial', false);

            if ($selectedPackageType === 'General') {
                $query->where(function($q) {
                    $q->whereNull('package_type')->orWhere('package_type', '');
                });
            } elseif ($selectedPackageType) {
                $query->whereRaw('LOWER(package_type) = ?', [strtolower($selectedPackageType)]);
            }
            
            // Load packages for previous page
            $packages = $query->orderBy('priority', 'asc')
                ->orderBy('price')
                ->skip(($prevPage - 1) * $packagesPerPage)
                ->take($packagesPerPage)
                ->get();
            
            $this->record->setMultiple([
                'packages' => $packages,
                'packages_page' => $prevPage
            ]);
        }
        
        return SelectPackageState::class;
    }
}