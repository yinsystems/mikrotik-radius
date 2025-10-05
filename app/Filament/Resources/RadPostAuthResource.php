<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadPostAuthResource\Pages;
use App\Filament\Resources\RadPostAuthResource\RelationManagers;
use App\Models\RadPostAuth;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RadPostAuthResource extends Resource
{
    protected static ?string $model = RadPostAuth::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    
    protected static ?string $navigationLabel = 'Post Auth';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(64)
                    ->default(''),
                Forms\Components\TextInput::make('pass')
                    ->required()
                    ->maxLength(64)
                    ->default(''),
                Forms\Components\TextInput::make('reply')
                    ->required()
                    ->maxLength(32)
                    ->default(''),
                Forms\Components\DateTimePicker::make('authdate')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pass')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reply')
                    ->searchable(),
                Tables\Columns\TextColumn::make('authdate')
                    ->dateTime()
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
            'index' => Pages\ListRadPostAuths::route('/'),
            'create' => Pages\CreateRadPostAuth::route('/create'),
            'edit' => Pages\EditRadPostAuth::route('/{record}/edit'),
        ];
    }
}
