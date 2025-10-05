<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Payment: ' . $this->getRecord()->internal_reference;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $payment = $this->getRecord();
        return "₵{$payment->amount} - {$payment->customer?->name} - " . ucfirst($payment->status);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve_payment')
                ->label('Approve Payment')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->getRecord()->isPending())
                ->action(function () {
                    $this->getRecord()->markAsCompleted();

                    \Filament\Notifications\Notification::make()
                        ->title('Payment approved')
                        ->body('Payment has been marked as completed.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('process_refund')
                ->label('Process Refund')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->getRecord()->canBeRefunded())
                ->form([
                    \Filament\Forms\Components\Select::make('refund_type')
                        ->required()
                        ->options([
                            'full' => 'Full Refund',
                            'partial' => 'Partial Refund',
                        ])
                        ->reactive(),

                    \Filament\Forms\Components\TextInput::make('refund_amount')
                        ->numeric()
                        ->prefix('₵')
                        ->required()
                        ->visible(fn (callable $get) => $get('refund_type') === 'partial')
                        ->rules(['min:0.01']),

                    \Filament\Forms\Components\Textarea::make('refund_reason')
                        ->required()
                        ->placeholder('Reason for refund'),
                ])
                ->action(function (array $data) {
                    try {
                        $payment = $this->getRecord();

                        if ($data['refund_type'] === 'full') {
                            $payment->processFullRefund(
                                $data['refund_reason'],
                                auth()->id()
                            );
                        } else {
                            $payment->processPartialRefund(
                                $data['refund_amount'],
                                $data['refund_reason'],
                                auth()->id()
                            );
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Refund processed')
                            ->body('Payment refund has been processed successfully.')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Refund failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\EditAction::make()
                ->color('warning'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Payment Overview')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('internal_reference')
                                    ->label('Reference')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('amount')
                                    ->money('GHS')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                Infolists\Components\IconEntry::make('status')
                                    ->icon(fn (Payment $record): string => $record->status_icon)
                                    ->color(fn (Payment $record): string => $record->status_color),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Customer & Subscription')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('customer.name')
                                    ->label('Customer')
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('customer.email')
                                    ->label('Email')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('customer.phone')
                                    ->label('Phone')
                                    ->copyable(),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('package.name')
                                    ->label('Package')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('subscription.status')
                                    ->label('Subscription Status')
                                    ->badge()
                                    ->color(fn (?string $state): string => match($state) {
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'expired' => 'danger',
                                        'suspended' => 'gray',
                                        default => 'secondary',
                                    }),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Payment Method & Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Method')
                                    ->formatStateUsing(fn (string $state): string => match($state) {
                                        'mobile_money' => 'Mobile Money',
                                        'bank_transfer' => 'Bank Transfer',
                                        default => ucfirst(str_replace('_', ' ', $state))
                                    })
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('provider_display_name')
                                    ->label('Provider')
                                    ->visible(fn (Payment $record): bool => $record->payment_method === 'mobile_money')
                                    ->badge()
                                    ->color('success'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('mobile_number')
                                    ->label('Mobile Number')
                                    ->visible(fn (Payment $record): bool => $record->payment_method === 'mobile_money')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('transaction_id')
                                    ->label('Transaction ID')
                                    ->placeholder('Not provided')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('external_reference')
                                    ->label('External Reference')
                                    ->placeholder('Not provided')
                                    ->copyable(),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_date')
                                    ->label('Payment Date')
                                    ->dateTime()
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime()
                                    ->badge()
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime()
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Refund Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('refund_amount')
                                    ->label('Refunded Amount')
                                    ->money('GHS')
                                    ->placeholder('₵0.00')
                                    ->color('danger'),

                                Infolists\Components\TextEntry::make('refunded_at')
                                    ->label('Refunded At')
                                    ->dateTime()
                                    ->placeholder('Not refunded')
                                    ->badge()
                                    ->color('danger'),
                            ]),

                        Infolists\Components\TextEntry::make('refund_reason')
                            ->label('Refund Reason')
                            ->placeholder('No refund processed')
                            ->columnSpanFull(),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('refund_transaction_id')
                                    ->label('Refund Transaction ID')
                                    ->placeholder('N/A')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('refundedBy.name')
                                    ->label('Refunded By')
                                    ->placeholder('N/A'),
                            ]),
                    ])
                    ->visible(fn (Payment $record): bool => $record->isRefunded())
                    ->collapsible(),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('failure_reason')
                            ->label('Failure Reason')
                            ->placeholder('N/A')
                            ->visible(fn (Payment $record): bool => $record->isFailed()),

                        Infolists\Components\TextEntry::make('processedBy.name')
                            ->label('Processed By')
                            ->placeholder('System'),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No additional notes')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentResource\Widgets\PaymentStats::class,
        ];
    }
}
