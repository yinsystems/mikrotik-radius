<?php

namespace App\Filament\Resources\RadGroupReplyResource\Pages;

use App\Filament\Resources\RadGroupReplyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRadGroupReply extends EditRecord
{
    protected static string $resource = RadGroupReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
