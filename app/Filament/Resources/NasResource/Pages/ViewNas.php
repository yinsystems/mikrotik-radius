<?php

namespace App\Filament\Resources\NasResource\Pages;

use App\Filament\Resources\NasResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNas extends ViewRecord
{
    protected static string $resource = NasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-m-pencil-square'),
            Actions\DeleteAction::make()
                ->icon('heroicon-m-trash'),
        ];
    }
}