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
        
        // Move to previous page if possible
        if ($currentPage > 1) {
            $prevPage = $currentPage - 1;
            
            // Load packages for previous page
            $packages = Package::where('is_active', true)
                ->where('is_trial', false)
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