<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\SelectPackageTypeState;
use App\Models\Package;
use App\Settings\GeneralSettings;
use Sparors\Ussd\Action;

class SelectPackageTypeAction extends Action
{
    public function run(): string
    {
        // Initialize pagination if not already set
        if (!$this->record->get('package_types_page')) {
            $packageTypesPerPage = config('ussd.package_types_per_page', 3);
            $settings = app(GeneralSettings::class);
            
            // Get package types that actually have packages
            $dbPackageTypes = Package::where('is_active', true)
                ->where('is_trial', false)
                ->whereNotNull('package_type')
                ->where('package_type', '!=', '')
                ->distinct()
                ->pluck('package_type')
                ->toArray();

            // Get available package types from settings (case insensitive match)
            $availablePackageTypes = [];
            foreach ($settings->package_types as $settingsType) {
                foreach ($dbPackageTypes as $dbType) {
                    if (strtolower($settingsType) === strtolower($dbType)) {
                        $availablePackageTypes[] = $settingsType; // Use the settings version for consistency
                        break;
                    }
                }
            }

            // Also add any package types from DB that aren't in settings but have packages
            foreach ($dbPackageTypes as $dbType) {
                $found = false;
                foreach ($availablePackageTypes as $availableType) {
                    if (strtolower($availableType) === strtolower($dbType)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $availablePackageTypes[] = $dbType;
                }
            }

            // Add option for packages without specific type
            $packagesWithoutType = Package::where('is_active', true)
                ->where('is_trial', false)
                ->where(function($query) {
                    $query->whereNull('package_type')->orWhere('package_type', '');
                })
                ->exists();

            if ($packagesWithoutType) {
                $availablePackageTypes[] = 'General';
            }

            // Paginate the package types
            $totalTypes = count($availablePackageTypes);
            $totalPages = ceil($totalTypes / $packageTypesPerPage);
            
            $currentPageTypes = array_slice($availablePackageTypes, 0, $packageTypesPerPage);

            $this->record->setMultiple([
                'available_package_types' => $availablePackageTypes,
                'current_package_types' => $currentPageTypes,
                'package_types_page' => 1,
                'total_package_types' => $totalTypes,
                'total_type_pages' => $totalPages,
                'package_types_per_page' => $packageTypesPerPage
            ]);
        }

        return SelectPackageTypeState::class;
    }
}