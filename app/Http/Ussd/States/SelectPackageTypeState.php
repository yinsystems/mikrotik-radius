<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\SelectPackageAction;
use App\Http\Ussd\Actions\WifiWelcomeAction;
use App\Http\Ussd\Actions\NextPackageTypePageAction;
use App\Http\Ussd\Actions\PrevPackageTypePageAction;
use App\Models\Package;
use Sparors\Ussd\State;

class SelectPackageTypeState extends State
{
    protected function beforeRendering(): void
    {
        $currentTypes = $this->record->get('current_package_types', []);
        $currentPage = $this->record->get('package_types_page', 1);
        $totalPages = $this->record->get('total_type_pages', 1);

        $this->menu->line('Select Package Type:');

        if (!empty($currentTypes)) {
            foreach ($currentTypes as $index => $packageType) {
                // Get count of packages for this type
                $packageCount = $this->getPackageCountForType($packageType);
                
                $this->menu->line(sprintf('%d) %s (%d packages)',
                    $index + 1,
                    $packageType,
                    $packageCount
                ));
            }
        } else {
            $this->menu->line('No package types available');
        }

        $this->menu->lineBreak();

        // Show navigation options
        $optionNumber = count($currentTypes) + 1;

        if ($totalPages > 1) {
            if ($currentPage < $totalPages) {
                $this->menu->line("{$optionNumber}) More");
                $optionNumber++;
            }

            if ($currentPage > 1) {
                $this->menu->line("{$optionNumber}) Back");
                $optionNumber++;
            }
        }

        $this->menu->line('0) Main Menu');
    }

    protected function afterRendering(string $argument): void
    {
        $currentTypes = $this->record->get('current_package_types', []);
        $typeCount = count($currentTypes);
        $currentPage = $this->record->get('package_types_page', 1);
        $totalPages = $this->record->get('total_type_pages', 1);

        // Store selected package type if valid selection
        if ($argument >= 1 && $argument <= $typeCount) {
            $selectedType = $currentTypes[$argument - 1];
            $this->record->set('selected_package_type', $selectedType);
            
            $this->decision->between(1, $typeCount, SelectPackageAction::class);
            return;
        }

        $optionNumber = $typeCount + 1;

        // Handle pagination
        if ($totalPages > 1) {
            if ($currentPage < $totalPages && $argument == $optionNumber) {
                $this->decision->equal($optionNumber, NextPackageTypePageAction::class);
                return;
            }

            if ($currentPage < $totalPages) {
                $optionNumber++;
            }

            if ($currentPage > 1 && $argument == $optionNumber) {
                $this->decision->equal($optionNumber, PrevPackageTypePageAction::class);
                return;
            }
        }

        // Go back to main menu
        $this->decision->equal('0', WifiWelcomeAction::class);
    }

    private function getPackageCountForType(string $packageType): int
    {
        if ($packageType === 'General') {
            return Package::where('is_active', true)
                ->where('is_trial', false)
                ->where(function($query) {
                    $query->whereNull('package_type')->orWhere('package_type', '');
                })
                ->count();
        }

        return Package::where('is_active', true)
            ->where('is_trial', false)
            ->whereRaw('LOWER(package_type) = ?', [strtolower($packageType)])
            ->count();
    }
}