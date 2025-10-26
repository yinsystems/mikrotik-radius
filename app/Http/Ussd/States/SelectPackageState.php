<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\ConfirmPackageAction;
use App\Http\Ussd\Actions\WifiWelcomeAction;
use App\Http\Ussd\Actions\NextPackagePageAction;
use App\Http\Ussd\Actions\PrevPackagePageAction;
use Sparors\Ussd\State;

class SelectPackageState extends State
{
    protected function beforeRendering(): void
    {
        $packages = $this->record->get('packages');
        $currentPage = $this->record->get('packages_page', 1);
        $totalPages = $this->record->get('total_pages', 1);

        $this->menu->line('Select:');
//        $this->menu->line("Page {$currentPage} of {$totalPages}");
//        $this->menu->lineBreak();

        if ($packages && $packages->count() > 0) {
            foreach ($packages as $index => $package) {
                // Format duration more concisely
                $duration = match($package->duration_type) {
                    'minutely' => $package->duration_value . 'min',
                    'hourly' => $package->duration_value . 'hr',
                    'daily' => $package->duration_value . 'd',
                    'weekly' => $package->duration_value . 'wk',
                    'monthly' => $package->duration_value . 'mo',
                    default => $package->duration_value . ' ' . $package->duration_type
                };

                // Format data limit
                $dataLimit = $package->data_limit ?
                    ($package->data_limit >= 1024 ?
                        number_format($package->data_limit / 1024, 1) . ' GB' :
                        $package->data_limit . ' MB') :
                    'Unlimited';

                // Format price with appropriate decimal places
                $priceDisplay = $package->price == floor($package->price) ?
                    number_format($package->price, 0) :
                    number_format($package->price, 2);

                $this->menu->line(sprintf('%d) %s - GHS %s',
                    $index + 1,
                    $package->name,
                    $priceDisplay
                ));
                $this->menu->line(sprintf('   %s, %d user(s)',
                    $dataLimit,
//                    $duration,
                    $package->simultaneous_users
                ));
            }
        } else {
            $this->menu->line('No packages available');
        }

        $this->menu->lineBreak();

        // Show navigation options
        $optionNumber = $packages->count() + 1;

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
        $packages = $this->record->get('packages');
        $packageCount = $packages ? $packages->count() : 0;
        $currentPage = $this->record->get('packages_page', 1);
        $totalPages = $this->record->get('total_pages', 1);

        // Store selected package if valid selection
        if ($argument >= 1 && $argument <= $packageCount) {
            $selectedPackage = $packages[$argument - 1];

            // Format duration concisely
            $duration = match($selectedPackage->duration_type) {
                'minutely' => $selectedPackage->duration_value . 'min',
                'hourly' => $selectedPackage->duration_value . 'hr',
                'daily' => $selectedPackage->duration_value . 'd',
                'weekly' => $selectedPackage->duration_value . 'wk',
                'monthly' => $selectedPackage->duration_value . 'mo',
                default => $selectedPackage->duration_value . ' ' . $selectedPackage->duration_type
            };

            // Format data limit
            $dataLimit = $selectedPackage->data_limit ?
                ($selectedPackage->data_limit >= 1024 ?
                    number_format($selectedPackage->data_limit / 1024, 1) . 'GB' :
                    $selectedPackage->data_limit . 'MB') :
                'Unlimited';

            $this->record->setMultiple([
                'selected_package_id' => $selectedPackage->id,
                'selected_package_name' => $selectedPackage->name,
                'selected_package_price' => $selectedPackage->price,
                'selected_package_duration' => $duration,
                'selected_package_data' => $dataLimit,
                'selected_package_users' => $selectedPackage->simultaneous_users
            ]);
        }

        // Decision routing for package selection
        $this->decision->between(1, $packageCount, ConfirmPackageAction::class);

        // Navigation routing
        $optionNumber = $packageCount + 1;

        if ($totalPages > 1) {
            if ($currentPage < $totalPages) {
                $this->decision->equal((string)$optionNumber, NextPackagePageAction::class);
                $optionNumber++;
            }

            if ($currentPage > 1) {
                $this->decision->equal((string)$optionNumber, PrevPackagePageAction::class);
                $optionNumber++;
            }
        }

        // Back to main menu
        $this->decision->equal('0', WifiWelcomeAction::class);
    }
}
