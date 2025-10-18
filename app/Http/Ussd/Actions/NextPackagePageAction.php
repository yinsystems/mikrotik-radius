<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\SelectPackageState;
use App\Models\Package;
use Sparors\Ussd\Action;

class NextPackagePageAction extends Action
{
    public function run(): string
    {
        $currentPage = $this->record->get('packages_page', 1);
        $totalPages = $this->record->get('total_pages', 1);
        $packagesPerPage = $this->record->get('packages_per_page', 3);
        
        // Move to next page if possible
        if ($currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
            
            // Load packages for next page
            $packages = Package::where('is_active', true)
                ->where('is_trial', false)
                ->orderBy('price')
                ->skip(($nextPage - 1) * $packagesPerPage)
                ->take($packagesPerPage)
                ->get();
            
            $this->record->setMultiple([
                'packages' => $packages,
                'packages_page' => $nextPage
            ]);
        }
        
        return SelectPackageState::class;
    }
}