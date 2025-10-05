<?php

namespace App\Filament\Resources\RadPostAuthResource\Pages;

use App\Filament\Resources\RadPostAuthResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRadPostAuth extends EditRecord
{
    protected static string $resource = RadPostAuthResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
