<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadReplyResource\Pages;
use App\Filament\Resources\RadReplyResource\RelationManagers;
use App\Models\RadReply;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RadReplyResource extends Resource
{
    protected static ?string $model = RadReply::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    
    protected static ?string $navigationLabel = 'User Reply';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
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
                Tables\Columns\TextColumn::make('username')
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
            'index' => Pages\ListRadReplies::route('/'),
            'create' => Pages\CreateRadReply::route('/create'),
            'edit' => Pages\EditRadReply::route('/{record}/edit'),
        ];
    }
}
