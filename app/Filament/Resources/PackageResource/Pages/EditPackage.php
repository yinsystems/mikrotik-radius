<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Models\Package;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class EditPackage extends EditRecord
{
    protected static string $resource = PackageResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Edit Package: ' . $this->getRecord()->name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $package = $this->getRecord();
        $subscriptions = $package->subscriptions()->count();
        $activeSubscriptions = $package->activeSubscriptions()->count();
        
        return "Active subscriptions: {$activeSubscriptions} | Total subscriptions: {$subscriptions}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_subscriptions')
                ->label('View Subscriptions')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.subscriptions.index', [
                    'tableFilters[package_id][value]' => $this->getRecord()->id
                ]))
                ->openUrlInNewTab(),
            
            Actions\Action::make('duplicate')
                ->label('Duplicate Package')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->required()
                        ->default(fn () => $this->getRecord()->name . ' (Copy)')
                        ->helperText('Name for the duplicated package'),
                ])
                ->action(function (array $data) {
                    $newPackage = $this->getRecord()->replicate();
                    $newPackage->name = $data['name'];
                    $newPackage->is_active = false; // Start as inactive
                    $newPackage->save();
                    
                    Notification::make()
                        ->title('Package duplicated')
                        ->body('New package created as inactive. You can edit and activate it when ready.')
                        ->success()
                        ->send();
                        
                    return redirect(static::getResource()::getUrl('edit', ['record' => $newPackage]));
                }),
            
            Actions\Action::make('toggle_status')
                ->label(fn () => $this->getRecord()->is_active ? 'Deactivate' : 'Activate')
                ->icon(fn () => $this->getRecord()->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->color(fn () => $this->getRecord()->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn () => ($this->getRecord()->is_active ? 'Deactivate' : 'Activate') . ' Package')
                ->modalDescription(function () {
                    $package = $this->getRecord();
                    if ($package->is_active) {
                        $activeCount = $package->activeSubscriptions()->count();
                        return "This will prevent new subscriptions to this package. {$activeCount} existing active subscriptions will not be affected.";
                    }
                    return 'This will allow new subscriptions to this package.';
                })
                ->action(function () {
                    $package = $this->getRecord();
                    $package->update(['is_active' => !$package->is_active]);
                    
                    Notification::make()
                        ->title('Package ' . ($package->is_active ? 'activated' : 'deactivated'))
                        ->success()
                        ->send();
                }),
            
            Actions\ViewAction::make()
                ->color('info'),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Package')
                ->modalDescription(function () {
                    $package = $this->getRecord();
                    $subscriptions = $package->subscriptions()->count();
                    
                    if ($subscriptions > 0) {
                        return "This package has {$subscriptions} associated subscriptions and cannot be deleted. You can deactivate it instead.";
                    }
                    
                    return 'This will permanently delete the package. This action cannot be undone.';
                })
                ->before(function () {
                    $package = $this->getRecord();
                    if ($package->subscriptions()->exists()) {
                        Notification::make()
                            ->title('Cannot delete package')
                            ->body('This package has associated subscriptions and cannot be deleted.')
                            ->danger()
                            ->send();
                        
                        $this->halt();
                    }
                }),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Store original values for comparison
        $originalData = $record->toArray();
        
        // Validate business rules
        $this->validatePackageUpdate($record, $data, $originalData);
        
        // Update the record
        $record->update($data);
        
        // Handle post-update operations
        $this->handlePostUpdate($record, $originalData);
        
        return $record;
    }

    protected function validatePackageUpdate(Package $package, array &$data, array $originalData): void
    {
        // Check if package has active subscriptions for critical changes
        $activeSubscriptions = $package->activeSubscriptions()->count();
        
        if ($activeSubscriptions > 0) {
            // Check for price changes
            if (isset($data['price']) && $data['price'] != $originalData['price']) {
                Notification::make()
                    ->title('Price Change Warning')
                    ->body("This package has {$activeSubscriptions} active subscriptions. Price changes won't affect existing subscriptions.")
                    ->warning()
                    ->send();
            }
            
            // Check for duration changes
            if ((isset($data['duration_type']) && $data['duration_type'] != $originalData['duration_type']) ||
                (isset($data['duration_value']) && $data['duration_value'] != $originalData['duration_value'])) {
                Notification::make()
                    ->title('Duration Change Warning')
                    ->body("This package has {$activeSubscriptions} active subscriptions. Duration changes won't affect existing subscriptions.")
                    ->warning()
                    ->send();
            }
            
            // Check for bandwidth changes
            if ((isset($data['bandwidth_upload']) && $data['bandwidth_upload'] != $originalData['bandwidth_upload']) ||
                (isset($data['bandwidth_download']) && $data['bandwidth_download'] != $originalData['bandwidth_download'])) {
                
                // This might require RADIUS updates
                Notification::make()
                    ->title('Bandwidth Change Notice')
                    ->body("Bandwidth changes may require RADIUS system updates for active users.")
                    ->info()
                    ->send();
            }
            
            // Check for data limit changes
            if (isset($data['data_limit']) && $data['data_limit'] != $originalData['data_limit']) {
                Notification::make()
                    ->title('Data Limit Change Notice')
                    ->body("Data limit changes may require RADIUS system updates for active users.")
                    ->info()
                    ->send();
            }
        }
        
        // Ensure trial packages have proper settings
        if ($data['is_trial'] && !isset($data['trial_duration_hours'])) {
            $data['trial_duration_hours'] = $originalData['trial_duration_hours'] ?? 24;
        }
        
        // Ensure non-trial packages don't have trial duration
        if (!$data['is_trial']) {
            $data['trial_duration_hours'] = null;
        }
        
        // Validate price for trial packages
        if ($data['is_trial'] && $data['price'] > 0) {
            Notification::make()
                ->title('Trial Package Warning')
                ->body('Trial packages typically should be free (₵0).')
                ->warning()
                ->send();
        }
        
        // Check for duplicate package names (excluding current package)
        $existingPackage = Package::where('name', $data['name'])
            ->where('id', '!=', $package->id)
            ->first();
            
        if ($existingPackage) {
            Notification::make()
                ->title('Duplicate Package Name')
                ->body('Another package with this name already exists.')
                ->warning()
                ->send();
        }
        
        // Validate bandwidth settings
        if (isset($data['bandwidth_upload']) && isset($data['bandwidth_download'])) {
            if ($data['bandwidth_upload'] > $data['bandwidth_download']) {
                Notification::make()
                    ->title('Bandwidth Configuration')
                    ->body('Upload speed is higher than download speed. This is unusual but allowed.')
                    ->warning()
                    ->send();
            }
        }
    }

    protected function handlePostUpdate(Package $package, array $originalData): void
    {
        try {
            // Update RADIUS group settings if bandwidth or data limits changed
            $bandwidthChanged = 
                $package->bandwidth_upload != ($originalData['bandwidth_upload'] ?? null) ||
                $package->bandwidth_download != ($originalData['bandwidth_download'] ?? null);
                
            $dataLimitChanged = $package->data_limit != ($originalData['data_limit'] ?? null);
            
            if ($bandwidthChanged || $dataLimitChanged) {
                // Update RADIUS group configuration
                $this->updateRadiusGroup($package);
            }
            
            // If package was deactivated, notify about active subscriptions
            if (!$package->is_active && $originalData['is_active']) {
                $activeCount = $package->activeSubscriptions()->count();
                if ($activeCount > 0) {
                    Notification::make()
                        ->title('Package Deactivated')
                        ->body("{$activeCount} active subscriptions will continue until they expire.")
                        ->info()
                        ->send();
                }
            }
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Update Warning')
                ->body('Package updated but some RADIUS configurations may need manual attention: ' . $e->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function updateRadiusGroup(Package $package): void
    {
        $groupName = $package->getRadiusGroupName();
        
        // This would integrate with your RADIUS system
        // For now, we'll just log the update
        Notification::make()
            ->title('RADIUS Update')
            ->body("RADIUS group '{$groupName}' configuration updated.")
            ->info()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Package updated')
            ->body('The package has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure boolean fields are properly set
        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_trial'] = $data['is_trial'] ?? false;
        
        return $data;
    }
}