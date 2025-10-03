<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadUserGroupResource\Pages;
use App\Filament\Resources\RadUserGroupResource\RelationManagers;
use App\Models\RadUserGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RadUserGroupResource extends Resource
{
    protected static ?string $model = RadUserGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    
    protected static ?string $navigationLabel = 'User Groups';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(64)
                    ->default(''),
                Forms\Components\TextInput::make('groupname')
                    ->required()
                    ->maxLength(64)
                    ->default(''),
                Forms\Components\TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('groupname')
                    ->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListRadUserGroups::route('/'),
            'create' => Pages\CreateRadUserGroup::route('/create'),
            'edit' => Pages\EditRadUserGroup::route('/{record}/edit'),
        ];
    }
}
