<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Models\Package;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewPackage extends ViewRecord
{
    protected static string $resource = PackageResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Package: ' . $this->getRecord()->name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $package = $this->getRecord();
        return $package->description ?? 'Package details and subscription information';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_subscriptions')
                ->label('View Subscriptions')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.subscriptions.index', [
                    'tableFilters[package_id][value]' => $this->getRecord()->id
                ]))
                ->openUrlInNewTab(),

            Actions\Action::make('create_subscription')
                ->label('New Subscription')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->url(fn () => route('filament.admin.resources.subscriptions.create', [
                    'package_id' => $this->getRecord()->id
                ]))
                ->visible(fn () => $this->getRecord()->is_active),


            Actions\EditAction::make()
                ->color('warning'),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->required()
                        ->default(fn () => $this->getRecord()->name . ' (Copy)')
                        ->helperText('Name for the duplicated package'),
                ])
                ->action(function (array $data) {
                    $newPackage = $this->getRecord()->replicate();
                    $newPackage->name = $data['name'];
                    $newPackage->is_active = false;
                    $newPackage->save();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $newPackage]));
                }),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->before(function () {
                    if ($this->getRecord()->subscriptions()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete package')
                            ->body('This package has associated subscriptions.')
                            ->danger()
                            ->send();

                        $this->halt();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Package Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('priority')
                                    ->badge()
                                    ->color('gray'),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('No description provided')
                            ->columnSpanFull(),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                Infolists\Components\IconEntry::make('is_trial')
                                    ->label('Trial Package')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-beaker')
                                    ->falseIcon('heroicon-o-minus-circle')
                                    ->trueColor('warning')
                                    ->falseColor('gray')
,
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime()
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Pricing & Duration')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('price')
                                    ->money('GHS')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('duration_display')
                                    ->label('Duration')
                                    ->badge()
                                    ->color(function (Package $record): string {
                                        return match ($record->duration_type) {
                                            'hourly' => 'info',
                                            'daily' => 'success',
                                            'weekly' => 'primary',
                                            'monthly' => 'danger',
                                            'trial' => 'warning',
                                            default => 'gray',
                                        };
                                    }),

                                Infolists\Components\TextEntry::make('trial_duration_hours')
                                    ->label('Trial Duration')
                                    ->suffix(' hours')
                                    ->placeholder('N/A')
                                    ->visible(fn (Package $record): bool => $record->is_trial),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Technical Specifications')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('bandwidth_download')
                                    ->label('Download Speed')
                                    ->formatStateUsing(function (?int $state): string {
                                        if (!$state) return 'Unlimited';

                                        if ($state >= 1024) {
                                            return number_format($state / 1024, 1) . ' Mbps';
                                        }

                                        return number_format($state) . ' Kbps';
                                    })
                                    ->badge()
                                    ->color(fn (?int $state): string => $state ? 'info' : 'success'),

                                Infolists\Components\TextEntry::make('bandwidth_upload')
                                    ->label('Upload Speed')
                                    ->formatStateUsing(function (?int $state): string {
                                        if (!$state) return 'Unlimited';

                                        if ($state >= 1024) {
                                            return number_format($state / 1024, 1) . ' Mbps';
                                        }

                                        return number_format($state) . ' Kbps';
                                    })
                                    ->badge()
                                    ->color(fn (?int $state): string => $state ? 'info' : 'success'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('data_limit')
                                    ->label('Data Limit')
                                    ->formatStateUsing(function (?int $state): string {
                                        if (!$state) return 'Unlimited';

                                        if ($state >= 1024) {
                                            return number_format($state / 1024, 1) . ' GB';
                                        }

                                        return number_format($state) . ' MB';
                                    })
                                    ->badge()
                                    ->color(fn (?int $state): string => $state ? 'warning' : 'success'),

                                Infolists\Components\TextEntry::make('simultaneous_users')
                                    ->label('Max Concurrent Users')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('vlan_id')
                                    ->label('VLAN ID')
                                    ->placeholder('Not set')
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_subscriptions')
                                    ->label('Total Subscriptions')
                                    ->state(fn (Package $record): int => $record->subscriptions()->count())
                                    ->badge()
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('active_subscriptions')
                                    ->label('Active Subscriptions')
                                    ->state(fn (Package $record): int => $record->activeSubscriptions()->count())
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('total_revenue')
                                    ->label('Total Revenue')
                                    ->state(function (Package $record): string {
                                        $total = $record->subscriptions()
                                            ->join('payments', 'subscriptions.id', '=', 'payments.subscription_id')
                                            ->where('payments.status', 'completed')
                                            ->sum('payments.amount');

                                        return '₵' . number_format($total, 2);
                                    })
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('monthly_revenue')
                                    ->label('This Month Revenue')
                                    ->state(function (Package $record): string {
                                        $total = $record->subscriptions()
                                            ->join('payments', 'subscriptions.id', '=', 'payments.subscription_id')
                                            ->where('payments.status', 'completed')
                                            ->whereMonth('payments.created_at', now()->month)
                                            ->whereYear('payments.created_at', now()->year)
                                            ->sum('payments.amount');

                                        return '₵' . number_format($total, 2);
                                    })
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('RADIUS Integration')
                    ->schema([
                        Infolists\Components\TextEntry::make('radius_group_name')
                            ->label('RADIUS Group')
                            ->state(fn (Package $record): string => $record->getRadiusGroupName())
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('radius_attributes')
                            ->label('RADIUS Attributes')
                            ->state(function (Package $record): string {
                                $attributes = [];

                                if ($record->bandwidth_download) {
                                    $attributes[] = "Max-Downstream: {$record->bandwidth_download} Kbps";
                                }

                                if ($record->bandwidth_upload) {
                                    $attributes[] = "Max-Upstream: {$record->bandwidth_upload} Kbps";
                                }

                                if ($record->data_limit) {
                                    $attributes[] = "Data-Limit: {$record->data_limit} MB";
                                }

                                if ($record->simultaneous_users > 1) {
                                    $attributes[] = "Simultaneous-Use: {$record->simultaneous_users}";
                                }

                                return $attributes ? implode(', ', $attributes) : 'No specific attributes';
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PackageResource\Widgets\PackageStats::class,
        ];
    }
}
