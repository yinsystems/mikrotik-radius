<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'transaction_id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('₵')
                            ->minValue(0.01),

                        Forms\Components\Select::make('currency')
                            ->options([
                                'GHS' => 'Ghanaian Cedi (₵)',
                                'USD' => 'US Dollar ($)',
                                'EUR' => 'Euro (€)',
                                'GBP' => 'British Pound (£)',
                            ])
                            ->default('GHS')
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'card' => 'Credit/Debit Card',
                                'bank_transfer' => 'Bank Transfer',
                                'mobile_money' => 'Mobile Money',
                                'cash' => 'Cash',
                                'paypal' => 'PayPal',
                                'crypto' => 'Cryptocurrency',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->unique(Payment::class, 'transaction_id', ignoreRecord: true),

                        Forms\Components\TextInput::make('reference')
                            ->label('Payment Reference')
                            ->helperText('Internal reference or receipt number'),

                        Forms\Components\DateTimePicker::make('payment_date')
                            ->default(now())
                            ->native(false),

                        Forms\Components\Select::make('subscription_id')
                            ->label('Related Subscription')
                            ->relationship('subscription', 'id')
                            ->searchable()
                            ->nullable()
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Refund Information')
                    ->schema([
                        Forms\Components\TextInput::make('refund_amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->prefix('₵')
                            ->minValue(0),

                        Forms\Components\Textarea::make('refund_reason')
                            ->label('Refund Reason')
                            ->rows(3),

                        Forms\Components\DateTimePicker::make('refunded_at')
                            ->label('Refund Date')
                            ->native(false),
                    ])
                    ->visible(fn ($get) => $get('status') === 'refunded'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_id')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::SemiBold)
                    ->placeholder('No transaction ID'),

                Tables\Columns\TextColumn::make('amount')
                    ->money('GHS')
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->icon('heroicon-o-banknotes'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        'refunded' => 'purple',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'processing' => 'heroicon-o-arrow-path',
                        'failed' => 'heroicon-o-x-circle',
                        'cancelled' => 'heroicon-o-no-symbol',
                        'refunded' => 'heroicon-o-arrow-uturn-left',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'card' => 'Card',
                        'bank_transfer' => 'Transfer',
                        'mobile_money' => 'Mobile',
                        'cash' => 'Cash',
                        'paypal' => 'PayPal',
                        'crypto' => 'Crypto',
                        'other' => 'Other',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),

                Tables\Columns\TextColumn::make('subscription.package.name')
                    ->label('Package')
                    ->placeholder('No subscription')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No reference')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('refund_amount')
                    ->label('Refunded')
                    ->money('GHS')
                    ->placeholder('No refund')
                    ->color('purple')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'card' => 'Card',
                        'bank_transfer' => 'Bank Transfer',
                        'mobile_money' => 'Mobile Money',
                        'cash' => 'Cash',
                        'paypal' => 'PayPal',
                        'crypto' => 'Cryptocurrency',
                        'other' => 'Other',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('completed_payments')
                    ->label('Completed Only')
                    ->query(fn (Builder $query) => $query->where('status', 'completed'))
                    ->toggle(),

                Tables\Filters\Filter::make('refunded_payments')
                    ->label('Refunded Payments')
                    ->query(fn (Builder $query) => $query->where('status', 'refunded'))
                    ->toggle(),

                Tables\Filters\Filter::make('high_value')
                    ->label('High Value (>₵500)')
                    ->query(fn (Builder $query) => $query->where('amount', '>', 500))
                    ->toggle(),

                Tables\Filters\Filter::make('recent_payments')
                    ->label('Recent (7 days)')
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7)))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        // Generate transaction ID if not provided
                        if (empty($data['transaction_id'])) {
                            $data['transaction_id'] = 'TXN-' . strtoupper(uniqid());
                        }
                        
                        // Set payment date if not provided
                        if (empty($data['payment_date'])) {
                            $data['payment_date'] = now();
                        }
                        
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Payment $record) {
                            $record->update([
                                'status' => 'completed',
                                'payment_date' => now(),
                            ]);
                            
                            Notification::make()
                                ->title('Payment Completed')
                                ->body('Payment has been marked as completed.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Payment $record) => in_array($record->status, ['pending', 'processing'])),

                    Tables\Actions\Action::make('mark_failed')
                        ->label('Mark as Failed')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Payment $record) {
                            $record->update(['status' => 'failed']);
                            
                            Notification::make()
                                ->title('Payment Failed')
                                ->body('Payment has been marked as failed.')
                                ->warning()
                                ->send();
                        })
                        ->visible(fn (Payment $record) => in_array($record->status, ['pending', 'processing'])),

                    Tables\Actions\Action::make('process_refund')
                        ->label('Process Refund')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('purple')
                        ->form([
                            Forms\Components\TextInput::make('refund_amount')
                                ->label('Refund Amount')
                                ->numeric()
                                ->prefix('₵')
                                ->required()
                                ->default(fn (Payment $record) => $record->amount),

                            Forms\Components\Textarea::make('refund_reason')
                                ->label('Refund Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Payment $record, array $data) {
                            $record->update([
                                'status' => 'refunded',
                                'refund_amount' => $data['refund_amount'],
                                'refund_reason' => $data['refund_reason'],
                                'refunded_at' => now(),
                            ]);
                            
                            Notification::make()
                                ->title('Refund Processed')
                                ->body("Refund of ₵{$data['refund_amount']} has been processed.")
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Payment $record) => $record->status === 'completed' && !$record->isRefunded()),

                    Tables\Actions\Action::make('send_receipt')
                        ->label('Send Receipt')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->action(function (Payment $record) {
                            // Here you would implement receipt sending
                            Notification::make()
                                ->title('Receipt Sent')
                                ->body('Payment receipt has been sent to customer.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Payment $record) => $record->status === 'completed'),

                    Tables\Actions\Action::make('view_details')
                        ->label('View Full Details')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(fn (Payment $record) => route('filament.admin.resources.payments.view', $record), shouldOpenInNewTab: true)
                        ->visible(fn () => class_exists('App\Filament\Resources\PaymentResource')),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm'),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('This will permanently delete this payment record.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('mark_completed_bulk')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Payment $payment) {
                                if (in_array($payment->status, ['pending', 'processing'])) {
                                    $payment->update([
                                        'status' => 'completed',
                                        'payment_date' => now(),
                                    ]);
                                }
                            });
                            
                            Notification::make()
                                ->title('Payments Completed')
                                ->body(count($records) . ' payments have been marked as completed.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('export_payments')
                        ->label('Export Payments')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            // Here you would implement payment export
                            Notification::make()
                                ->title('Export Started')
                                ->body('Payment export has been initiated.')
                                ->info()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}