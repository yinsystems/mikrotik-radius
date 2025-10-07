<?php

namespace App\Models;

use DB;
use Exception;
use Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Str;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'username', // Customer's chosen RADIUS username (defaults to phone)
        'password', // Customer's chosen RADIUS password
        'status', // 'active', 'suspended', 'blocked'
        'registration_date',
        'last_login',
        'notes',
        'internet_token', // 6-digit token for WiFi authentication
        'token_generated_at' // When the current token was generated
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'last_login' => 'datetime',
        'token_generated_at' => 'datetime'
    ];

    // Relationships
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function activeSubscriptions()
    {
        return $this->hasMany(Subscription::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function dataUsage()
    {
        return $this->hasManyThrough(
            DataUsage::class,
            Subscription::class,
            'customer_id',
            'subscription_id'
        );
    }

    // Helper methods
    public function getFullNameAttribute()
    {
        return $this->name;
    }

    public function getActiveSubscription()
    {
        return $this->activeSubscription;
    }

    public function hasActiveSubscription()
    {
        return $this->activeSubscription()->exists();
    }

    public function getCurrentPackage()
    {
        return $this->activeSubscription?->package;
    }

    public function getTotalSpent()
    {
        return $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getLastPaymentDate()
    {
        return $this->payments()
            ->where('status', 'completed')
            ->latest('payment_date')
            ->value('payment_date');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function scopeWithActiveSubscription($query)
    {
        return $query->whereHas('activeSubscription');
    }

    public function scopeWithoutActiveSubscription($query)
    {
        return $query->whereDoesntHave('activeSubscription');
    }

    public function scopeRegisteredBetween($query, $start, $end)
    {
        return $query->whereBetween('registration_date', [$start, $end]);
    }

    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    // Status management with RADIUS integration
    public function suspend($reason = null)
    {
        $this->update(['status' => 'suspended']);

        // Suspend all active subscriptions with RADIUS sync
        $this->subscriptions()->whereIn('status', ['active', 'pending'])->each(function ($subscription) use ($reason) {
            $subscription->suspend($reason);
        });

        // Log the suspension
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Suspended: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }

        // Terminate any active RADIUS sessions
        $this->terminateAllActiveSessions('Customer Suspended');

        return $this;
    }

    public function resume($reason = 'Customer Resumed')
    {
        $this->update(['status' => 'active']);

        // Reactivate suspended subscriptions if they're not expired
        $this->subscriptions()->where('status', 'suspended')->each(function ($subscription) {
            if (!$subscription->isExpired()) {
                $subscription->activate();
            }
        });

        // Log the resume action
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Resumed: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }

        return $this;
    }

    public function activate()
    {
        return $this->resume('Customer Activated');
    }

    public function block($reason = null)
    {
        $this->update(['status' => 'blocked']);

        // Block all subscriptions with RADIUS sync
        $this->subscriptions()->whereIn('status', ['active', 'suspended', 'pending'])->each(function ($subscription) use ($reason) {
            $subscription->block($reason);
        });

        // Log the block action
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Blocked: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }

        // Terminate any active RADIUS sessions
        $this->terminateAllActiveSessions('Customer Blocked');

        return $this;
    }

    public function unblock($reason = 'Customer Unblocked')
    {
        $this->update(['status' => 'active']);

        // Reactivate non-expired subscriptions
        $this->subscriptions()->where('status', 'blocked')->each(function ($subscription) {
            if (!$subscription->isExpired()) {
                $subscription->activate();
            }
        });

        // Log the unblock action
        if ($reason) {
            $this->update(['notes' => ($this->notes ? $this->notes . "\n" : '') . "Unblocked: {$reason} on " . now()->format('Y-m-d H:i:s')]);
        }

        return $this;
    }

    public function terminateAllActiveSessions($reason = 'Admin Action')
    {
        $this->subscriptions->each(function ($subscription) use ($reason) {
            if ($subscription->username) {
                RadAcct::terminateUserSessions($subscription->username, $reason);
            }
        });

        return $this;
    }

    // Enhanced status check methods
    public function isSuspended()
    {
        return $this->status === 'suspended';
    }

    public function isBlocked()
    {
        return $this->status === 'blocked';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function canAccessInternet()
    {
        return $this->isActive() && $this->hasActiveSubscription();
    }

    // Credential management methods
    public function updateSubscriptionCredentials($subscriptionId, $newUsername = null, $newPassword = null)
    {
        $subscription = $this->subscriptions()->findOrFail($subscriptionId);
        return $subscription->updateCredentials($newUsername, $newPassword);
    }

    public function getDefaultUsername()
    {
        // Return phone number without special characters as default username
        return preg_replace('/[^0-9]/', '', $this->phone);
    }

    public function hasCustomUsername($subscriptionId)
    {
        $subscription = $this->subscriptions()->find($subscriptionId);
        if (!$subscription) return false;

        return $subscription->username !== $this->getDefaultUsername();
    }

    // Trial package management
    public function hasUsedTrialPackage()
    {
        return $this->subscriptions()
            ->whereHas('package', function ($query) {
                $query->where('is_trial', true);
            })
            ->exists();
    }

    public function getTrialSubscription()
    {
        return $this->subscriptions()
            ->whereHas('package', function ($query) {
                $query->where('is_trial', true);
            })
            ->first();
    }

    public function isEligibleForTrial()
    {
        // Customer is eligible for trial if:
        // 1. They haven't used a trial package before
        // 2. They don't have any active subscriptions
        return !$this->hasUsedTrialPackage() && !$this->hasActiveSubscription();
    }

    public function assignTrialPackage($trialPackageId = null)
    {
        // Check eligibility
        if (!$this->isEligibleForTrial()) {
            throw new Exception('Customer is not eligible for trial package');
        }

        // Find trial package
        $trialPackage = $trialPackageId
            ? Package::where('id', $trialPackageId)->where('is_trial', true)->first()
            : Package::where('is_trial', true)->where('is_active', true)->first();

        if (!$trialPackage) {
            throw new Exception('No trial package available');
        }

        // Create trial subscription
        $subscription = $this->createSubscription($trialPackage->id);

        // Activate trial immediately
        $subscription->activate();

        // Log trial assignment
        $this->update([
            'notes' => ($this->notes ? $this->notes . "\n" : '') .
                "Trial package '{$trialPackage->name}' assigned on " . now()->format('Y-m-d H:i:s')
        ]);

        return $subscription;
    }

    // Subscription management with RADIUS integration
    public function createSubscription($packageId, $paymentId = null)
    {
        $package = Package::findOrFail($packageId);

        $subscription = $this->subscriptions()->create([
            'package_id' => $packageId,
            'starts_at' => now(),
            'expires_at' => $this->calculateExpiration($package),
            'status' => "pending",
            'payment_id' => $paymentId,
        ]);

        // Create RADIUS user using customer credentials
        $subscription->createRadiusUser();

        return $subscription;
    }

    public function activateLatestSubscription()
    {
        $subscription = $this->subscriptions()->latest()->first();

        if ($subscription && $subscription->status === 'pending') {
            $subscription->activate();
        }

        return $subscription;
    }

    public function suspendAllSubscriptions($reason = null)
    {
        $this->subscriptions()->where('status', 'active')->each(function ($subscription) use ($reason) {
            $subscription->suspend($reason);
        });

        $this->update(['status' => 'suspended']);

        return $this;
    }

    public function reactivateAllSubscriptions()
    {
        $this->subscriptions()->where('status', 'suspended')->each(function ($subscription) {
            if (!$subscription->isExpired()) {
                $subscription->activate();
            }
        });

        $this->update(['status' => 'active']);

        return $this;
    }

    public function deleteAllRadiusUsers()
    {
        $this->subscriptions->each(function ($subscription) {
            $subscription->deleteRadiusUser();
        });

        return $this;
    }

    public function syncAllRadiusStatus()
    {
        $this->subscriptions->each(function ($subscription) {
            $subscription->syncRadiusStatus();
        });

        return $this;
    }

    private function calculateExpiration($package)
    {
        return match ($package->duration_type) {
            'minutely' => now()->addMinutes($package->duration_value),
            'hourly' => now()->addHours($package->duration_value),
            'daily' => now()->addDays($package->duration_value),
            'weekly' => now()->addWeeks($package->duration_value),
            'monthly' => now()->addMonths($package->duration_value),
            'trial' => now()->addHours($package->trial_duration_hours),
            default => now()->addDays(1)
        };
    }

    // Refund management methods
    public function getTotalRefunds()
    {
        return $this->payments()->refunded()->sum('refund_amount');
    }

    public function getRefundablePayments()
    {
        return $this->payments()->where('status', 'completed')->get()->filter(function ($payment) {
            return $payment->canBeRefunded();
        });
    }

    public function hasRefundablePayments()
    {
        return $this->getRefundablePayments()->count() > 0;
    }

    public function getNetSpent()
    {
        $totalSpent = $this->getTotalSpent();
        $totalRefunds = $this->getTotalRefunds();

        return $totalSpent - $totalRefunds;
    }

    public function getRefundRate()
    {
        $totalPayments = $this->payments()->completed()->count();
        $refundedPayments = $this->payments()->refunded()->count();

        return $totalPayments > 0 ? ($refundedPayments / $totalPayments) * 100 : 0;
    }

    // Customer Portal Functions
    public function getCustomerDashboard()
    {
        $activeSubscription = $this->getActiveSubscription();
        $recentPayments = $this->payments()->latest()->limit(5)->get();
        $totalUsage = $this->getCurrentUsageStats();

        return [
            'customer' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'status' => $this->status,
                'created_at' => $this->created_at->format('Y-m-d H:i:s')
            ],
            'subscription' => $activeSubscription ? [
                'id' => $activeSubscription->id,
                'package_name' => $activeSubscription->package->name,
                'status' => $activeSubscription->status,
                'username' => $activeSubscription->username,
                'starts_at' => $activeSubscription->starts_at->format('Y-m-d H:i:s'),
                'expires_at' => $activeSubscription->expires_at->format('Y-m-d H:i:s'),
                'days_remaining' => max(0, $activeSubscription->expires_at->diffInDays(now())),
                'hours_remaining' => max(0, $activeSubscription->expires_at->diffInHours(now())),
                'is_trial' => $activeSubscription->is_trial,
                'auto_renew' => $activeSubscription->auto_renew,
                'package_details' => [
                    'data_limit_mb' => $activeSubscription->package->data_limit,
                    'bandwidth_up' => $activeSubscription->package->bandwidth_upload,
                    'bandwidth_down' => $activeSubscription->package->bandwidth_download,
                    'duration' => $activeSubscription->package->duration_value . ' ' . $activeSubscription->package->duration_type,
                    'price' => $activeSubscription->package->price,
                    'currency' => $activeSubscription->package->currency
                ]
            ] : null,
            'usage' => $totalUsage,
            'payment_summary' => [
                'total_spent' => $this->getTotalSpent(),
                'total_refunds' => $this->getTotalRefunds(),
                'net_spent' => $this->getNetSpent(),
                'recent_payments' => $recentPayments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'method' => $payment->payment_method,
                        'created_at' => $payment->created_at->format('Y-m-d H:i:s')
                    ];
                })
            ],
            'quick_actions' => [
                'can_renew' => $activeSubscription && $activeSubscription->canBeRenewed(),
                'can_upgrade' => $activeSubscription && $activeSubscription->status === 'active',
                'has_active_sessions' => $activeSubscription ? $activeSubscription->getCurrentSessionCount() > 0 : false,
                'approaching_expiry' => $activeSubscription && $activeSubscription->expires_at->diffInHours(now()) <= 48
            ]
        ];
    }

    public function getCurrentUsageStats()
    {
        $activeSubscription = $this->getActiveSubscription();

        if (!$activeSubscription) {
            return [
                'has_active_subscription' => false,
                'message' => 'No active subscription found'
            ];
        }

        $todayUsage = $activeSubscription->getTodaysUsage();
        $sessionStats = $activeSubscription->getSessionStatistics(30);
        $recentSessions = $activeSubscription->getSessionHistory(10, 7);

        $dataLimitMb = $activeSubscription->package->data_limit;
        $totalUsedMb = $activeSubscription->data_used / (1024 * 1024);

        return [
            'has_active_subscription' => true,
            'subscription_id' => $activeSubscription->id,
            'username' => $activeSubscription->username,
            'today' => [
                'date' => $todayUsage['date'],
                'sessions' => $todayUsage['sessions'],
                'active_sessions' => $todayUsage['active_sessions'],
                'data_used_mb' => $todayUsage['total_mb'],
                'time_spent' => $todayUsage['total_time_formatted']
            ],
            'monthly_stats' => [
                'total_sessions' => $sessionStats['total_sessions'],
                'active_sessions' => $sessionStats['active_sessions'],
                'total_data_gb' => $sessionStats['total_data_gb'],
                'total_time_hours' => $sessionStats['total_time_hours'],
                'avg_session_time' => $sessionStats['avg_session_time_formatted'],
                'avg_data_per_session_mb' => $sessionStats['avg_data_per_session_mb']
            ],
            'data_limits' => [
                'limit_mb' => $dataLimitMb,
                'used_mb' => round($totalUsedMb, 2),
                'remaining_mb' => $dataLimitMb ? max(0, $dataLimitMb - $totalUsedMb) : 'unlimited',
                'usage_percentage' => $dataLimitMb ? min(100, round(($totalUsedMb / $dataLimitMb) * 100, 1)) : 0,
                'is_approaching_limit' => $dataLimitMb && ($totalUsedMb / $dataLimitMb) >= 0.8,
                'is_over_limit' => $dataLimitMb && $totalUsedMb >= $dataLimitMb
            ],
            'recent_sessions' => $recentSessions->take(5)->map(function ($session) {
                return [
                    'start_time' => $session['start_time'],
                    'duration' => $session['session_time_formatted'],
                    'data_used_mb' => $session['total_mb'],
                    'is_active' => $session['is_active']
                ];
            })
        ];
    }

    public function allowSelfServiceRenewal($packageId = null)
    {
        $activeSubscription = $this->getActiveSubscription();

        if (!$activeSubscription) {
            return [
                'success' => false,
                'message' => 'No active subscription to renew',
                'can_renew' => false
            ];
        }

        // Check if subscription can be renewed
        if (!$activeSubscription->canBeRenewed()) {
            return [
                'success' => false,
                'message' => 'Subscription cannot be renewed at this time',
                'can_renew' => false,
                'reason' => 'Subscription status: ' . $activeSubscription->status
            ];
        }

        // If no package specified, use the current package or renewal package
        $renewalPackage = null;
        if ($packageId) {
            $renewalPackage = Package::find($packageId);
        } else {
            $renewalPackage = $activeSubscription->renewal_package_id
                ? Package::find($activeSubscription->renewal_package_id)
                : $activeSubscription->package;
        }

        if (!$renewalPackage) {
            return [
                'success' => false,
                'message' => 'Renewal package not found',
                'can_renew' => false
            ];
        }

        return [
            'success' => true,
            'can_renew' => true,
            'current_subscription' => [
                'id' => $activeSubscription->id,
                'package' => $activeSubscription->package->name,
                'expires_at' => $activeSubscription->expires_at->format('Y-m-d H:i:s'),
                'days_remaining' => $activeSubscription->expires_at->diffInDays(now())
            ],
            'renewal_package' => [
                'id' => $renewalPackage->id,
                'name' => $renewalPackage->name,
                'price' => $renewalPackage->price,
                'currency' => $renewalPackage->currency,
                'duration' => $renewalPackage->duration_value . ' ' . $renewalPackage->duration_type,
                'data_limit_mb' => $renewalPackage->data_limit,
                'bandwidth_up' => $renewalPackage->bandwidth_upload,
                'bandwidth_down' => $renewalPackage->bandwidth_download
            ],
            'payment_required' => $renewalPackage->price > 0,
            'message' => 'Subscription can be renewed'
        ];
    }

    public function getPaymentHistory($limit = 20)
    {
        return $this->payments()
            ->with(['subscription.package'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'transaction_id' => $payment->transaction_id,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    'subscription' => $payment->subscription ? [
                        'id' => $payment->subscription->id,
                        'package_name' => $payment->subscription->package->name ?? 'Unknown Package',
                        'duration' => $payment->subscription->package ?
                            $payment->subscription->package->duration_value . ' ' . $payment->subscription->package->duration_type
                            : 'Unknown'
                    ] : null,
                    'refund_info' => $payment->isRefunded() ? [
                        'refund_amount' => $payment->refund_amount,
                        'refund_reason' => $payment->refund_reason,
                        'refunded_at' => $payment->refunded_at ? $payment->refunded_at->format('Y-m-d H:i:s') : null
                    ] : null,
                    'can_be_refunded' => $payment->canBeRefunded()
                ];
            });
    }

    public function getAvailablePackagesForUpgrade()
    {
        $activeSubscription = $this->getActiveSubscription();

        if (!$activeSubscription || $activeSubscription->status !== 'active') {
            return collect();
        }

        $currentPackage = $activeSubscription->package;

        // Get packages with higher value (price or data limit)
        return Package::where('status', 'active')
            ->where('id', '!=', $currentPackage->id)
            ->where(function ($query) use ($currentPackage) {
                $query->where('price', '>', $currentPackage->price)
                    ->orWhere('data_limit', '>', $currentPackage->data_limit);
            })
            ->orderBy('price')
            ->get()
            ->map(function ($package) use ($currentPackage) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'price' => $package->price,
                    'currency' => $package->currency,
                    'duration' => $package->duration_value . ' ' . $package->duration_type,
                    'data_limit_mb' => $package->data_limit,
                    'bandwidth_up' => $package->bandwidth_upload,
                    'bandwidth_down' => $package->bandwidth_download,
                    'upgrade_benefits' => [
                        'price_difference' => $package->price - $currentPackage->price,
                        'data_increase_mb' => $package->data_limit ?
                            ($currentPackage->data_limit ?
                                $package->data_limit - $currentPackage->data_limit :
                                $package->data_limit) : 'unlimited',
                        'bandwidth_increase' => [
                            'upload' => $package->bandwidth_upload - $currentPackage->bandwidth_upload,
                            'download' => $package->bandwidth_download - $currentPackage->bandwidth_download
                        ]
                    ]
                ];
            });
    }

    public function requestPasswordReset()
    {
        // Generate a secure password reset token
        $token = Str::random(60);

        // Store in database with expiration (you'd need a password_resets table)
        DB::table('password_resets')->updateOrInsert(
            ['email' => $this->email],
            [
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        // Send password reset email/SMS
        // event(new CustomerPasswordResetRequested($this, $token));

        return [
            'success' => true,
            'message' => 'Password reset instructions sent to your email/phone',
            'token' => $token // In production, don't return the token
        ];
    }

    // Internet Token Management Methods
    
    /**
     * Generate a new 6-digit internet token for WiFi authentication
     */
    public function generateInternetToken()
    {
        $token = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Ensure token is unique across all customers
        while (self::where('internet_token', $token)->exists()) {
            $token = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        }
        
        $this->update([
            'internet_token' => $token,
            'token_generated_at' => now()
        ]);
        
        // Create RADIUS entries with the new token
        $this->createRadiusEntries();
        
        \Log::info("Generated new internet token for customer {$this->username}: {$token}");
        
        return $token;
    }
    
    /**
     * Create RADIUS authentication entries using the internet token
     */
    public function createRadiusEntries()
    {
        if (!$this->internet_token) {
            return false;
        }
        
        // Create RadCheck entry for authentication
        RadCheck::updateOrCreate(
            ['username' => $this->username, 'attribute' => 'Cleartext-Password'],
            ['op' => ':=', 'value' => $this->internet_token]
        );
        
        \Log::info("Created RADIUS entries for customer {$this->username} with token authentication");
        
        return true;
    }
    
    /**
     * Remove all RADIUS entries for this customer (except accounting)
     */
    public function removeRadiusEntries()
    {
        RadCheck::where('username', $this->username)->delete();
        RadReply::where('username', $this->username)->delete();
        
        \Log::info("Removed all RADIUS entries for customer {$this->username}");
        
        return true;
    }
    
    /**
     * Check if customer has a valid internet token
     */
    public function hasValidInternetToken()
    {
        return !empty($this->internet_token) && !empty($this->token_generated_at);
    }
    
    /**
     * Get the internet token for display/SMS purposes
     */
    public function getInternetToken()
    {
        return $this->internet_token;
    }
    
    /**
     * Regenerate internet token (for new subscriptions)
     */
    public function regenerateInternetToken()
    {
        // Remove old RADIUS entries
        $this->removeRadiusEntries();
        
        // Generate new token and create new RADIUS entries
        return $this->generateInternetToken();
    }
}
