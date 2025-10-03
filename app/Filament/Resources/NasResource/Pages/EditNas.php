<?php

namespace App\Filament\Resources\NasResource\Pages;

use App\Filament\Resources\NasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditNas extends EditRecord
{
    protected static string $resource = NasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon('heroicon-m-eye'),
            Actions\DeleteAction::make()
                ->icon('heroicon-m-trash'),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('NAS Device Updated')
            ->body('The NAS device has been updated successfully.');
    }
}