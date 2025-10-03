<?php

namespace App\Filament\Resources\RadCheckResource\Pages;

use App\Filament\Resources\RadCheckResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRadCheck extends EditRecord
{
    protected static string $resource = RadCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
