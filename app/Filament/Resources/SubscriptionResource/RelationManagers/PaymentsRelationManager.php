<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Payment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Colors\Color;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payment History';

    protected static ?string $label = 'Payment';

    protected static ?string $pluralLabel = 'Payments';

    protected static ?string $icon = 'heroicon-o-credit-card';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0)
                            ->prefix('₵')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Auto-calculate tax if needed
                                $taxRate = 0.075; // 7.5% VAT
                                $tax = $state * $taxRate;
                                $set('tax_amount', round($tax, 2));
                                $set('amount', round($state + $tax, 2));
                            }),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('Tax Amount')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->prefix('₵')
                            ->default(0),

                        Forms\Components\TextInput::make('amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->prefix('₵')
                            ->required(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'card' => 'Card Payment',
                                'mobile_money' => 'Mobile Money',
                                'ussd' => 'USSD',
                                'pos' => 'POS',
                                'online' => 'Online Payment',
                                'crypto' => 'Cryptocurrency',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('status')
                            ->label('Payment Status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                                'partially_refunded' => 'Partially Refunded',
                            ])
                            ->default('pending')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('reference')
                            ->label('Payment Reference')
                            ->maxLength(100)
                            ->placeholder('Transaction ID, Receipt number, etc.'),

                        Forms\Components\TextInput::make('gateway_reference')
                            ->label('Gateway Reference')
                            ->maxLength(100)
                            ->placeholder('Paystack, Flutterwave, etc. reference')
                            ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['card', 'online', 'mobile_money'])),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\Select::make('payment_for')
                            ->label('Payment For')
                            ->options([
                                'subscription' => 'New Subscription',
                                'renewal' => 'Subscription Renewal',
                                'upgrade' => 'Package Upgrade',
                                'penalty' => 'Penalty/Fine',
                                'installation' => 'Installation Fee',
                                'equipment' => 'Equipment',
                                'other' => 'Other',
                            ])
                            ->default('subscription')
                            ->required(),

                        Forms\Components\DateTimePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\DateTimePicker::make('confirmed_at')
                            ->label('Confirmation Date')
                            ->visible(fn (Forms\Get $get) => in_array($get('status'), ['completed', 'refunded']))
                            ->default(fn (Forms\Get $get) => $get('status') === 'completed' ? now() : null),

                        Forms\Components\TextInput::make('confirmed_by')
                            ->label('Confirmed By')
                            ->maxLength(100)
                            ->visible(fn (Forms\Get $get) => in_array($get('status'), ['completed', 'refunded']))
                            ->default(auth()->user()->name ?? null),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Payment description or additional details...'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Internal notes (not visible to customer)...'),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('GHS')
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Total')
                    ->money('GHS')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'bank_transfer' => 'info',
                        'card' => 'primary',
                        'mobile_money' => 'warning',
                        'online' => 'primary',
                        'pos' => 'info',
                        'ussd' => 'warning',
                        'crypto' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        'refunded' => 'danger',
                        'partially_refunded' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_for')
                    ->label('Purpose')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'subscription' => 'primary',
                        'renewal' => 'success',
                        'upgrade' => 'info',
                        'penalty' => 'danger',
                        'installation' => 'warning',
                        'equipment' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->limit(20)
                    ->tooltip(function (Payment $record): ?string {
                        return $record->reference;
                    }),

                Tables\Columns\TextColumn::make('gateway_reference')
                    ->label('Gateway Ref')
                    ->searchable()
                    ->copyable()
                    ->limit(15)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Confirmed')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Not confirmed')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('confirmed_by')
                    ->label('Confirmed By')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->tooltip(function (Payment $record): ?string {
                        return $record->description;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially Refunded',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'card' => 'Card Payment',
                        'mobile_money' => 'Mobile Money',
                        'ussd' => 'USSD',
                        'pos' => 'POS',
                        'online' => 'Online Payment',
                        'crypto' => 'Cryptocurrency',
                        'other' => 'Other',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_for')
                    ->label('Payment Purpose')
                    ->options([
                        'subscription' => 'New Subscription',
                        'renewal' => 'Subscription Renewal',
                        'upgrade' => 'Package Upgrade',
                        'penalty' => 'Penalty/Fine',
                        'installation' => 'Installation Fee',
                        'equipment' => 'Equipment',
                        'other' => 'Other',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->label('Amount From')
                            ->numeric()
                            ->prefix('₵'),
                        Forms\Components\TextInput::make('amount_to')
                            ->label('Amount To')
                            ->numeric()
                            ->prefix('₵'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),

                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('paid_from')
                            ->label('Paid From'),
                        Forms\Components\DatePicker::make('paid_to')
                            ->label('Paid To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['paid_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['paid_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('pending_confirmation')
                    ->label('Pending Confirmation')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'completed')->whereNull('confirmed_at')),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('payment_date', today())),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('payment_date', [
                        now()->startOfMonth(),
                        now()->endOfMonth()
                    ])),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Record Payment')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Set subscription_id from the owner record
                        $subscription = $this->getOwnerRecord();
                        $data['subscription_id'] = $subscription->id;
                        $data['customer_id'] = $subscription->customer_id;
                        $data['package_id'] = $subscription->package_id;
                        
                        // Generate reference if not provided
                        if (empty($data['reference'])) {
                            $data['reference'] = 'PAY-' . now()->format('YmdHis') . '-' . $subscription->id;
                        }
                        
                        return $data;
                    })
                    ->after(function (Payment $record) {
                        // Auto-process subscription if payment is completed
                        if ($record->status === 'completed' && $record->payment_for === 'subscription') {
                            $subscription = $record->subscription;
                            if ($subscription && $subscription->status === 'pending') {
                                $subscription->activate();
                                
                                \Filament\Notifications\Notification::make()
                                    ->title('Subscription Activated')
                                    ->body('Subscription has been automatically activated after payment confirmation.')
                                    ->success()
                                    ->send();
                            }
                        }
                    }),

                Tables\Actions\Action::make('payment_summary')
                    ->label('Payment Summary')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->action(function () {
                        $subscription = $this->getOwnerRecord();
                        $payments = $subscription->payments;
                        
                        $totalPaid = $payments->where('status', 'completed')->sum('amount');
                        $totalPending = $payments->where('status', 'pending')->sum('amount');
                        $totalRefunded = $payments->where('status', 'refunded')->sum('amount');
                        $paymentCount = $payments->count();
                        $lastPayment = $payments->sortByDesc('payment_date')->first();
                        
                        $summary = sprintf(
                            "Payment Summary:\n\n" .
                            "Total Completed: ₵%s\n" .
                            "Total Pending: ₵%s\n" .
                            "Total Refunded: ₵%s\n" .
                            "Total Payments: %d\n" .
                            "Last Payment: %s",
                            number_format($totalPaid, 2),
                            number_format($totalPending, 2),
                            number_format($totalRefunded, 2),
                            $paymentCount,
                            $lastPayment ? $lastPayment->payment_date->format('M j, Y') : 'None'
                        );
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment Summary')
                            ->body($summary)
                            ->info()
                            ->duration(10000)
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('confirm_payment')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        $record->update([
                            'status' => 'completed',
                            'confirmed_at' => now(),
                            'confirmed_by' => auth()->user()->name ?? 'System',
                        ]);
                        
                        // Auto-activate subscription if needed
                        if ($record->payment_for === 'subscription') {
                            $subscription = $record->subscription;
                            if ($subscription && $subscription->status === 'pending') {
                                $subscription->activate();
                            }
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment Confirmed')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Payment $record): bool => $record->status === 'pending'),

                Tables\Actions\Action::make('mark_failed')
                    ->label('Mark Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('failure_reason')
                            ->label('Failure Reason')
                            ->required()
                            ->placeholder('Reason for payment failure...'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Payment $record, array $data) {
                        $record->update([
                            'status' => 'failed',
                            'notes' => ($record->notes ? $record->notes . "\n\n" : '') . 
                                      "Payment marked as failed on " . now()->format('Y-m-d H:i:s') . 
                                      "\nReason: " . $data['failure_reason'],
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment Marked as Failed')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (Payment $record): bool => in_array($record->status, ['pending', 'processing'])),

                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('refund_amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('₵')
                            ->default(fn (Payment $record) => $record->amount)
                            ->required(),
                        
                        Forms\Components\Textarea::make('refund_reason')
                            ->label('Refund Reason')
                            ->required()
                            ->placeholder('Reason for refund...'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Payment $record, array $data) {
                        $isPartialRefund = $data['refund_amount'] < $record->amount;
                        
                        // Create refund record (you might have a separate refunds table)
                        $record->paymentRefunds()->create([
                            'amount' => $data['refund_amount'],
                            'reason' => $data['refund_reason'],
                            'processed_at' => now(),
                            'processed_by' => auth()->user()->name ?? 'System',
                        ]);
                        
                        $record->update([
                            'status' => $isPartialRefund ? 'partially_refunded' : 'refunded',
                            'notes' => ($record->notes ? $record->notes . "\n\n" : '') . 
                                      "Refund processed on " . now()->format('Y-m-d H:i:s') . 
                                      "\nAmount: ₵" . number_format($data['refund_amount'], 2) . 
                                      "\nReason: " . $data['refund_reason'],
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Refund Processed')
                            ->body("₵" . number_format($data['refund_amount'], 2) . " refund has been processed.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Payment $record): bool => $record->status === 'completed'),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('confirm_payments')
                        ->label('Confirm Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $confirmed = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    $record->update([
                                        'status' => 'completed',
                                        'confirmed_at' => now(),
                                        'confirmed_by' => auth()->user()->name ?? 'System',
                                    ]);
                                    $confirmed++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title("Confirmed {$confirmed} payments")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('export_payments')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function ($records) {
                            $exportData = $records->map(function (Payment $record) {
                                return [
                                    'ID' => $record->id,
                                    'Reference' => $record->reference,
                                    'Amount' => $record->amount,
                                    'Tax' => $record->tax_amount,
                                    'Total' => $record->amount,
                                    'Method' => $record->payment_method,
                                    'Status' => $record->status,
                                    'Purpose' => $record->payment_for,
                                    'Payment Date' => $record->payment_date->format('Y-m-d H:i:s'),
                                    'Confirmed Date' => $record->confirmed_at?->format('Y-m-d H:i:s'),
                                    'Confirmed By' => $record->confirmed_by,
                                    'Description' => $record->description,
                                ];
                            });
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Export Prepared')
                                ->body('Selected payments have been prepared for export.')
                                ->info()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('calculate_totals')
                        ->label('Calculate Totals')
                        ->icon('heroicon-o-calculator')
                        ->color('primary')
                        ->action(function ($records) {
                            $totalAmount = $records->sum('amount');
                            $completedAmount = $records->where('status', 'completed')->sum('amount');
                            $pendingAmount = $records->where('status', 'pending')->sum('amount');
                            $refundedAmount = $records->where('status', 'refunded')->sum('amount');
                            
                            $summary = sprintf(
                                "Selected Payments Summary:\n\n" .
                                "Total Amount: ₵%s\n" .
                                "Completed: ₵%s\n" .
                                "Pending: ₵%s\n" .
                                "Refunded: ₵%s\n" .
                                "Records: %d",
                                number_format($totalAmount, 2),
                                number_format($completedAmount, 2),
                                number_format($pendingAmount, 2),
                                number_format($refundedAmount, 2),
                                $records->count()
                            );
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Payment Totals')
                                ->body($summary)
                                ->info()
                                ->duration(10000)
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('payment_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}