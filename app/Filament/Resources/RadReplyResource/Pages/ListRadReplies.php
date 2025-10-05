<?php

namespace App\Filament\Resources\RadReplyResource\Pages;

use App\Filament\Resources\RadReplyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRadReplies extends ListRecords
{
    protected static string $resource = RadReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
