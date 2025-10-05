<?php

namespace App\Filament\Resources\NasResource\Pages;

use App\Filament\Resources\NasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNases extends ListRecords
{
    protected static string $resource = NasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add NAS Device')
                ->icon('heroicon-m-plus'),
        ];
    }
}