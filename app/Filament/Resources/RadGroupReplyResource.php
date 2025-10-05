<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadGroupReplyResource\Pages;
use App\Filament\Resources\RadGroupReplyResource\RelationManagers;
use App\Models\RadGroupReply;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RadGroupReplyResource extends Resource
{
    protected static ?string $model = RadGroupReply::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected static ?string $navigationLabel = 'Group Reply';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('groupname')
                    ->required()
                    ->maxLength(64)
                    ->default(''),
                Forms\Components\TextInput::make('attribute')
                    ->required()
                    ->maxLength(64)
                    ->default(''),
                Forms\Components\TextInput::make('op')
                    ->required()
                    ->maxLength(2)
                    ->default('='),
                Forms\Components\TextInput::make('value')
                    ->required()
                    ->maxLength(253)
                    ->default(''),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('groupname')
                    ->searchable(),
                Tables\Columns\TextColumn::make('attribute')
                    ->searchable(),
                Tables\Columns\TextColumn::make('op')
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRadGroupReplies::route('/'),
            'create' => Pages\CreateRadGroupReply::route('/create'),
            'edit' => Pages\EditRadGroupReply::route('/{record}/edit'),
        ];
    }
}
