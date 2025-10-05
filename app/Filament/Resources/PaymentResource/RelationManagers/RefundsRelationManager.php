<?php

namespace App\Filament\Resources\PaymentResource\RelationManagers;

use App\Models\PaymentRefund;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    protected static ?string $title = 'Payment Refunds';

    protected static ?string $icon = 'heroicon-o-arrow-uturn-left';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Refund Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('refund_amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₵')
                                    ->minValue(0.01)
                                    ->maxValue(fn () => $this->getOwnerRecord()->getRemainingRefundableAmount())
                                    ->helperText(function () {
                                        $payment = $this->getOwnerRecord();
                                        $remaining = $payment->getRemainingRefundableAmount();
                                        return "Maximum refundable: ₵" . number_format($remaining, 2);
                                    }),
                                
                                Forms\Components\Select::make('refund_type')
                                    ->required()
                                    ->options([
                                        'full' => 'Full Refund',
                                        'partial' => 'Partial Refund',
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        if ($state === 'full') {
                                            $payment = $this->getOwnerRecord();
                                            $set('refund_amount', $payment->getRemainingRefundableAmount());
                                        }
                                    }),
                            ]),
                        
                        Forms\Components\Textarea::make('refund_reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Reason for this refund')
                            ->helperText('Explain why this refund is being processed'),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('refund_method')
                                    ->required()
                                    ->options([
                                        'auto' => 'Automatic (Provider API)',
                                        'manual' => 'Manual Process',
                                        'provider_api' => 'Provider API Call',
                                    ])
                                    ->default('manual')
                                    ->helperText('How the refund will be processed'),
                                
                                Forms\Components\Select::make('refund_status')
                                    ->required()
                                    ->options([
                                        'pending' => 'Pending',
                                        'processing' => 'Processing',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                    ])
                                    ->default('pending')
                                    ->reactive(),
                            ]),
                    ]),
                
                Forms\Components\Section::make('Processing Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('refund_transaction_id')
                                    ->placeholder('Provider refund transaction ID')
                                    ->visible(fn (callable $get) => in_array($get('refund_status'), ['completed', 'processing']))
                                    ->helperText('Transaction ID from payment provider'),
                                
                                Forms\Components\TextInput::make('refund_reference')
                                    ->placeholder('Internal refund reference')
                                    ->default(fn () => 'REF-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)))
                                    ->helperText('Internal reference for tracking'),
                            ]),
                        
                        Forms\Components\DateTimePicker::make('processed_at')
                            ->visible(fn (callable $get) => $get('refund_status') === 'completed')
                            ->default(now())
                            ->helperText('When the refund was completed'),
                        
                        Forms\Components\Hidden::make('processed_by')
                            ->default(Auth::id()),
                        
                        Forms\Components\Textarea::make('notes')
                            ->rows(2)
                            ->placeholder('Additional notes about this refund')
                            ->helperText('Internal notes for reference'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('refund_reference')
            ->columns([
                Tables\Columns\TextColumn::make('refund_reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('refund_amount')
                    ->label('Amount')
                    ->money('GHS')
                    ->sortable()
                    ->weight('semibold')
                    ->color('danger'),
                
                Tables\Columns\BadgeColumn::make('refund_type')
                    ->label('Type')
                    ->color(fn (string $state): string => match($state) {
                        'full' => 'danger',
                        'partial' => 'warning',
                        default => 'gray',
                    }),
                
                Tables\Columns\BadgeColumn::make('refund_status')
                    ->label('Status')
                    ->color(fn (string $state): string => match($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'pending' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'processing' => 'heroicon-o-arrow-path',
                        'pending' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),
                
                Tables\Columns\TextColumn::make('refund_method')
                    ->label('Method')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'auto' => 'Automatic',
                        'manual' => 'Manual',
                        'provider_api' => 'Provider API',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('processedBy.name')
                    ->label('Processed By')
                    ->placeholder('System'),
                
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Pending'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('refund_status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing', 
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                
                Tables\Filters\SelectFilter::make('refund_type')
                    ->options([
                        'full' => 'Full Refund',
                        'partial' => 'Partial Refund',
                    ]),
                
                Tables\Filters\SelectFilter::make('refund_method')
                    ->options([
                        'auto' => 'Automatic',
                        'manual' => 'Manual',
                        'provider_api' => 'Provider API',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Process Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->modalHeading('Process Payment Refund')
                    ->before(function () {
                        $payment = $this->getOwnerRecord();
                        
                        if (!$payment->canBeRefunded()) {
                            Notification::make()
                                ->title('Cannot process refund')
                                ->body('This payment cannot be refunded.')
                                ->danger()
                                ->send();
                            
                            $this->halt();
                        }
                    })
                    ->using(function (array $data) {
                        $payment = $this->getOwnerRecord();
                        
                        // Create the refund record
                        $refund = $payment->refunds()->create($data);
                        
                        // If refund is completed, update the main payment
                        if ($data['refund_status'] === 'completed') {
                            $this->processMainPaymentRefund($payment, $refund);
                        }
                        
                        return $refund;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Refund created')
                            ->body('Payment refund has been recorded successfully.')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Refund Details'),
                
                Tables\Actions\EditAction::make()
                    ->modalHeading('Edit Refund'),
                
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentRefund $record) => $record->isPending())
                    ->form([
                        Forms\Components\TextInput::make('refund_transaction_id')
                            ->placeholder('Provider transaction ID'),
                        Forms\Components\Textarea::make('notes')
                            ->placeholder('Completion notes'),
                    ])
                    ->action(function (PaymentRefund $record, array $data) {
                        $record->markAsCompleted(
                            $data['refund_transaction_id'] ?? null
                        );
                        
                        if (!empty($data['notes'])) {
                            $record->update(['notes' => $data['notes']]);
                        }
                        
                        // Update main payment
                        $this->processMainPaymentRefund($this->getOwnerRecord(), $record);
                        
                        Notification::make()
                            ->title('Refund completed')
                            ->body('Refund has been marked as completed.')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('fail')
                    ->label('Mark Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentRefund $record) => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('failure_reason')
                            ->required()
                            ->placeholder('Reason for failure'),
                    ])
                    ->action(function (PaymentRefund $record, array $data) {
                        $record->markAsFailed($data['failure_reason']);
                        
                        Notification::make()
                            ->title('Refund marked as failed')
                            ->body('Refund has been marked as failed.')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn (PaymentRefund $record) => !$record->isCompleted()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No refunds processed')
            ->emptyStateDescription('No refunds have been processed for this payment.')
            ->emptyStateIcon('heroicon-o-arrow-uturn-left');
    }

    protected function processMainPaymentRefund($payment, PaymentRefund $refund): void
    {
        if (!$refund->isCompleted()) {
            return;
        }

        $totalRefunded = $payment->refunds()->completed()->sum('refund_amount');
        $newStatus = ($totalRefunded >= $payment->amount) ? 'refunded' : 'partially_refunded';

        $payment->update([
            'status' => $newStatus,
            'refund_amount' => $totalRefunded,
            'refunded_at' => now(),
            'refund_reason' => $refund->refund_reason,
            'refund_transaction_id' => $refund->refund_transaction_id,
            'refund_reference' => $refund->refund_reference,
            'refunded_by' => $refund->processed_by,
        ]);

        // Handle subscription effects
        if ($payment->subscription) {
            if ($newStatus === 'refunded') {
                $payment->subscription->suspend('Payment fully refunded');
            } elseif ($newStatus === 'partially_refunded') {
                $refundPercentage = ($totalRefunded / $payment->amount) * 100;
                if ($refundPercentage >= 50) {
                    $payment->subscription->suspend("Payment {$refundPercentage}% refunded");
                }
            }
        }
    }
}