<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Models\Package;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;

class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Packages & Plans';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $totalPackages = Package::count();
        $activePackages = Package::where('is_active', true)->count();
        $trialPackages = Package::where('is_trial', true)->count();

        return "Total: {$totalPackages} | Active: {$activePackages} | Trial: {$trialPackages}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Package')
                ->icon('heroicon-o-plus-circle')
                ->createAnother(false),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Packages')
                ->badge(Package::count())
                ->badgeColor('gray'),

            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
                ->badge(Package::where('is_active', true)->count())
                ->badgeColor('success'),

            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false))
                ->badge(Package::where('is_active', false)->count())
                ->badgeColor('danger'),

            'trial' => Tab::make('Trial')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_trial', true))
                ->badge(Package::where('is_trial', true)->count())
                ->badgeColor('warning'),

            'hourly' => Tab::make('Hourly')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('duration_type', 'hourly'))
                ->badge(Package::where('duration_type', 'hourly')->count())
                ->badgeColor('info'),

            'daily' => Tab::make('Daily')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('duration_type', 'daily'))
                ->badge(Package::where('duration_type', 'daily')->count())
                ->badgeColor('success'),

            'weekly' => Tab::make('Weekly')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('duration_type', 'weekly'))
                ->badge(Package::where('duration_type', 'weekly')->count())
                ->badgeColor('primary'),

            'monthly' => Tab::make('Monthly')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('duration_type', 'monthly'))
                ->badge(Package::where('duration_type', 'monthly')->count())
                ->badgeColor('danger'),

            'unlimited_data' => Tab::make('Unlimited Data')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('data_limit'))
                ->badge(Package::whereNull('data_limit')->count())
                ->badgeColor('success'),

            'limited_data' => Tab::make('Data Limited')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('data_limit'))
                ->badge(Package::whereNotNull('data_limit')->count())
                ->badgeColor('warning'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PackageResource\Widgets\PackageOverview::class,
        ];
    }
}
