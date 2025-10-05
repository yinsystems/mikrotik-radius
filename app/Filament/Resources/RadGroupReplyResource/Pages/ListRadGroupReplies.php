<?php

namespace App\Filament\Resources\RadGroupReplyResource\Pages;

use App\Filament\Resources\RadGroupReplyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRadGroupReplies extends ListRecords
{
    protected static string $resource = RadGroupReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
