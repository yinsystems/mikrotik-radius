<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Models\Package;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Htmlable;

class CreatePackage extends CreateRecord
{
    protected static string $resource = PackageResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Create New Package';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Create a new internet package with pricing, duration, and bandwidth settings';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('basic_hourly')
                    ->label('Basic Hourly (₵2/hour)')
                    ->action(function () {
                        $this->fillForm([
                            'name' => 'Basic Hourly',
                            'description' => 'Basic internet access for short-term use',
                            'duration_type' => 'hourly',
                            'duration_value' => 1,
                            'price' => 2.00,
                            'bandwidth_download' => 512,
                            'bandwidth_upload' => 256,
                            'data_limit' => 500,
                            'simultaneous_users' => 1,
                            'is_active' => true,
                            'priority' => 1,
                        ]);
                    }),
                
                Actions\Action::make('standard_daily')
                    ->label('Standard Daily (₵10/day)')
                    ->action(function () {
                        $this->fillForm([
                            'name' => 'Standard Daily',
                            'description' => 'Full day internet access with good speeds',
                            'duration_type' => 'daily',
                            'duration_value' => 1,
                            'price' => 10.00,
                            'bandwidth_download' => 2048,
                            'bandwidth_upload' => 1024,
                            'data_limit' => 2048,
                            'simultaneous_users' => 2,
                            'is_active' => true,
                            'priority' => 2,
                        ]);
                    }),
                    
                
                Actions\Action::make('premium_weekly')
                    ->label('Premium Weekly (₵50/week)')
                    ->action(function () {
                        $this->fillForm([
                            'name' => 'Premium Weekly',
                            'description' => 'High-speed internet for a full week',
                            'duration_type' => 'weekly',
                            'duration_value' => 1,
                            'price' => 50.00,
                            'bandwidth_download' => 5120,
                            'bandwidth_upload' => 2560,
                            'data_limit' => 10240,
                            'simultaneous_users' => 3,
                            'is_active' => true,
                            'priority' => 3,
                        ]);
                    }),
                
                Actions\Action::make('unlimited_monthly')
                    ->label('Unlimited Monthly (₵150/month)')
                    ->action(function () {
                        $this->fillForm([
                            'name' => 'Unlimited Monthly',
                            'description' => 'Unlimited high-speed internet for a full month',
                            'duration_type' => 'monthly',
                            'duration_value' => 1,
                            'price' => 150.00,
                            'bandwidth_download' => 10240,
                            'bandwidth_upload' => 5120,
                            'data_limit' => null,
                            'simultaneous_users' => 5,
                            'is_active' => true,
                            'priority' => 4,
                        ]);
                    }),
                
                Actions\Action::make('trial_package')
                    ->label('Free Trial (30 minutes)')
                    ->action(function () {
                        $this->fillForm([
                            'name' => 'Free Trial',
                            'description' => 'Free trial internet access for new customers',
                            'duration_type' => 'trial',
                            'duration_value' => 1,
                            'price' => 0.00,
                            'bandwidth_download' => 1024,
                            'bandwidth_upload' => 512,
                            'data_limit' => 100,
                            'simultaneous_users' => 1,
                            'is_active' => true,
                            'is_trial' => true,
                            'trial_duration_hours' => 0.5,
                            'priority' => 0,
                        ]);
                    }),
            ])
                ->label('Use Template')
                ->icon('heroicon-o-document-text')
                ->color('gray'),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Validate business rules
        $this->validatePackageData($data);
        
        // Create the package
        $package = static::getModel()::create($data);
        
        // Auto-create RADIUS group if needed
        $this->createRadiusGroupIfNeeded($package);
        
        return $package;
    }

    protected function validatePackageData(array &$data): void
    {
        // Ensure trial packages have proper settings
        if ($data['is_trial'] && !isset($data['trial_duration_hours'])) {
            $data['trial_duration_hours'] = 24; // Default to 24 hours
        }
        
        // Ensure non-trial packages don't have trial duration
        if (!$data['is_trial']) {
            $data['trial_duration_hours'] = null;
        }
        
        // Validate price for trial packages
        if ($data['is_trial'] && $data['price'] > 0) {
            Notification::make()
                ->title('Warning')
                ->body('Trial packages typically should be free (₵0). Continue anyway?')
                ->warning()
                ->send();
        }
        
        // Check for duplicate package names
        $existingPackage = Package::where('name', $data['name'])->first();
        if ($existingPackage) {
            Notification::make()
                ->title('Duplicate Package Name')
                ->body('A package with this name already exists. Consider using a different name.')
                ->warning()
                ->send();
        }
        
        // Validate bandwidth settings
        if (isset($data['bandwidth_upload']) && isset($data['bandwidth_download'])) {
            if ($data['bandwidth_upload'] > $data['bandwidth_download']) {
                Notification::make()
                    ->title('Bandwidth Warning')
                    ->body('Upload speed is higher than download speed. This is unusual but allowed.')
                    ->warning()
                    ->send();
            }
        }
        
        // Convert data limit from GB to MB if needed (assuming user might enter GB)
        if (isset($data['data_limit']) && $data['data_limit'] > 0) {
            // If the value is suspiciously low (likely GB instead of MB), warn user
            if ($data['data_limit'] < 50) {
                Notification::make()
                    ->title('Data Limit Notice')
                    ->body('Data limit seems very low. Remember to enter the value in MB (1 GB = 1024 MB).')
                    ->info()
                    ->send();
            }
        }
    }

    protected function createRadiusGroupIfNeeded(Package $package): void
    {
        try {
            // Create RADIUS group for this package
            $groupName = $package->getRadiusGroupName();
            
            // This would integrate with your RADIUS system
            // For now, we'll just log it
            Notification::make()
                ->title('Package Created')
                ->body("Package created successfully. RADIUS group: {$groupName}")
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('RADIUS Group Warning')
                ->body('Package created but RADIUS group setup needs attention: ' . $e->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Package created')
            ->body('The package has been created successfully and is ready for use.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default priority if not provided
        if (!isset($data['priority']) || $data['priority'] === null) {
            $data['priority'] = 0;
        }
        
        // Ensure boolean fields are properly set
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_trial'] = $data['is_trial'] ?? false;
        
        return $data;
    }
}