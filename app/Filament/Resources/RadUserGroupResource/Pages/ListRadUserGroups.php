<?php

namespace App\Filament\Resources\RadUserGroupResource\Pages;

use App\Filament\Resources\RadUserGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRadUserGroups extends ListRecords
{
    protected static string $resource = RadUserGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
