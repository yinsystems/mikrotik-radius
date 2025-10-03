<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DataUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'username',
        'date',
        'bytes_uploaded',
        'bytes_downloaded',
        'total_bytes',
        'session_count',
        'session_time', // total session time in seconds
        'peak_concurrent_sessions'
    ];

    protected $casts = [
        'date' => 'date',
        'bytes_uploaded' => 'integer',
        'bytes_downloaded' => 'integer',
        'total_bytes' => 'integer',
        'session_count' => 'integer',
        'session_time' => 'integer',
        'peak_concurrent_sessions' => 'integer'
    ];

    protected $table = 'data_usage';
    // Relationships
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customer()
    {
        return $this->hasOneThrough(Customer::class, Subscription::class, 'id', 'id', 'subscription_id', 'customer_id');
    }

    // Helper methods
    public function getTotalMbAttribute()
    {
        return round($this->total_bytes / (1024 * 1024), 2);
    }

    public function getTotalGbAttribute()
    {
        return round($this->total_bytes / (1024 * 1024 * 1024), 3);
    }

    public function getUploadMbAttribute()
    {
        return round($this->bytes_uploaded / (1024 * 1024), 2);
    }

    public function getDownloadMbAttribute()
    {
        return round($this->bytes_downloaded / (1024 * 1024), 2);
    }

    public function getFormattedTotalSizeAttribute()
    {
        $bytes = $this->total_bytes;

        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        } elseif ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    public function getFormattedSessionTimeAttribute()
    {
        $seconds = $this->session_time;

        if ($seconds >= 3600) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        } elseif ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . 'm ' . $remainingSeconds . 's';
        } else {
            return $seconds . 's';
        }
    }

    public function getAverageSessionTimeAttribute()
    {
        if ($this->session_count == 0) {
            return 0;
        }

        return round($this->session_time / $this->session_count);
    }

    // Scopes
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', now()->toDateString());
    }

    public function scopeYesterday($query)
    {
        return $query->whereDate('date', now()->subDay());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeLastWeek($query)
    {
        return $query->whereBetween('date', [
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
    }

    public function scopeLastMonth($query)
    {
        return $query->whereMonth('date', now()->subMonth()->month)
                    ->whereYear('date', now()->subMonth()->year);
    }

    public function scopeByUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeBySubscription($query, $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function scopeHighUsage($query, $thresholdMb = 1000)
    {
        return $query->where('total_bytes', '>=', $thresholdMb * 1024 * 1024);
    }

    public function scopeWithSessions($query)
    {
        return $query->where('session_count', '>', 0);
    }

    // Static methods for analytics
    public static function getTotalUsageByPeriod($start, $end, $groupBy = 'date')
    {
        return self::whereBetween('date', [$start, $end])
                  ->groupBy($groupBy)
                  ->selectRaw("$groupBy, SUM(total_bytes) as total_bytes, SUM(session_count) as total_sessions, SUM(session_time) as total_time")
                  ->orderBy($groupBy)
                  ->get();
    }

    public static function getTopUsers($start, $end, $limit = 10)
    {
        return self::whereBetween('date', [$start, $end])
                  ->groupBy('username')
                  ->selectRaw('username, SUM(total_bytes) as total_usage, SUM(session_count) as total_sessions')
                  ->orderByDesc('total_usage')
                  ->limit($limit)
                  ->get();
    }

    public static function getDailyAverages($start, $end)
    {
        $data = self::whereBetween('date', [$start, $end])
                   ->selectRaw('AVG(total_bytes) as avg_daily_usage, AVG(session_count) as avg_daily_sessions, AVG(session_time) as avg_daily_time')
                   ->first();

        return [
            'avg_daily_usage_mb' => round($data->avg_daily_usage / (1024 * 1024), 2),
            'avg_daily_sessions' => round($data->avg_daily_sessions, 1),
            'avg_daily_time_hours' => round($data->avg_daily_time / 3600, 2)
        ];
    }

    public static function updateFromRadAcct($username, $date = null)
    {
        $date = $date ?? now()->toDateString();

        $radAcctData = RadAcct::where('username', $username)
                             ->whereDate('acctstarttime', $date)
                             ->selectRaw('
                                 SUM(acctinputoctets) as total_upload,
                                 SUM(acctoutputoctets) as total_download,
                                 COUNT(*) as session_count,
                                 SUM(acctsessiontime) as total_time
                             ')
                             ->first();

        if ($radAcctData && ($radAcctData->total_upload > 0 || $radAcctData->total_download > 0)) {
            $subscription = Subscription::where('username', $username)->first();

            if ($subscription) {
                self::updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'username' => $username,
                        'date' => $date
                    ],
                    [
                        'bytes_uploaded' => $radAcctData->total_upload ?? 0,
                        'bytes_downloaded' => $radAcctData->total_download ?? 0,
                        'total_bytes' => ($radAcctData->total_upload ?? 0) + ($radAcctData->total_download ?? 0),
                        'session_count' => $radAcctData->session_count ?? 0,
                        'session_time' => $radAcctData->total_time ?? 0
                    ]
                );
            }
        }
    }

    public static function updateAllUsersForDate($date = null)
    {
        $date = $date ?? now()->toDateString();

        $activeUsernames = Subscription::where('status', 'active')
                                     ->where('expires_at', '>', $date)
                                     ->pluck('username');

        foreach ($activeUsernames as $username) {
            self::updateFromRadAcct($username, $date);
        }
    }

    // Data export methods
    public static function exportUsageReport($start, $end, $format = 'array')
    {
        $data = self::whereBetween('date', [$start, $end])
                   ->with(['subscription.customer', 'subscription.package'])
                   ->orderBy('date')
                   ->get();

        if ($format === 'csv') {
            return self::formatAsCsv($data);
        }

        return $data->map(function ($usage) {
            return [
                'date' => $usage->date->format('Y-m-d'),
                'customer_name' => $usage->subscription->customer->name ?? 'Unknown',
                'username' => $usage->username,
                'package' => $usage->subscription->package->name ?? 'Unknown',
                'total_mb' => $usage->total_mb,
                'upload_mb' => $usage->upload_mb,
                'download_mb' => $usage->download_mb,
                'sessions' => $usage->session_count,
                'session_time' => $usage->formatted_session_time
            ];
        });
    }

    private static function formatAsCsv($data)
    {
        $csv = "Date,Customer,Username,Package,Total MB,Upload MB,Download MB,Sessions,Session Time\n";

        foreach ($data as $usage) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%.2f,%.2f,%.2f,%d,%s\n",
                $usage->date->format('Y-m-d'),
                $usage->subscription->customer->name ?? 'Unknown',
                $usage->username,
                $usage->subscription->package->name ?? 'Unknown',
                $usage->total_mb,
                $usage->upload_mb,
                $usage->download_mb,
                $usage->session_count,
                $usage->formatted_session_time
            );
        }

        return $csv;
    }

    // Enhanced data usage tracking methods
    public function updateUsageFromRadius()
    {
        $subscription = $this->subscription;
        if (!$subscription) {
            return false;
        }

        // Get latest RADIUS accounting data for this subscription
        $radAcctData = RadAcct::where('username', $subscription->username)
                             ->whereDate('acctstarttime', $this->date)
                             ->selectRaw('
                                 SUM(acctinputoctets) as total_upload,
                                 SUM(acctoutputoctets) as total_download,
                                 COUNT(*) as session_count,
                                 SUM(acctsessiontime) as total_time,
                                 MAX(acctsessiontime) as max_session_time
                             ')
                             ->first();

        if ($radAcctData) {
            $this->update([
                'bytes_uploaded' => $radAcctData->total_upload ?? 0,
                'bytes_downloaded' => $radAcctData->total_download ?? 0,
                'total_bytes' => ($radAcctData->total_upload ?? 0) + ($radAcctData->total_download ?? 0),
                'session_count' => $radAcctData->session_count ?? 0,
                'session_time' => $radAcctData->total_time ?? 0
            ]);

            // Update subscription's total data used
            $totalUsed = self::where('subscription_id', $this->subscription_id)
                            ->sum('total_bytes');

            $subscription->update(['data_used' => $totalUsed]);

            // Check if data limit is exceeded
            $this->checkDataLimitExceeded();

            return true;
        }

        return false;
    }

    public function checkDataLimitExceeded()
    {
        $subscription = $this->subscription;
        if (!$subscription || !$subscription->package->data_limit) {
            return false;
        }

        $totalUsedMb = $subscription->data_used / (1024 * 1024);
        $dataLimitMb = $subscription->package->data_limit;

        // If 100% of data limit is exceeded, suspend subscription
        if ($totalUsedMb >= $dataLimitMb) {
            $subscription->suspend('Data limit exceeded');

            // Log the data limit exceeded event
            \Log::info("Data limit exceeded for subscription {$subscription->id}, username: {$subscription->username}");

            return true;
        }

        // Warning at 90% usage
        if ($totalUsedMb >= ($dataLimitMb * 0.9)) {
            // Trigger warning notification (could be email, SMS, etc.)
            event(new \App\Events\DataLimitWarning($subscription, $totalUsedMb, $dataLimitMb));
        }

        return false;
    }

    public function generateUsageReport($type = 'daily')
    {
        $subscription = $this->subscription;
        $customer = $subscription->customer;
        $package = $subscription->package;

        $report = [
            'report_type' => $type,
            'date' => $this->date->format('Y-m-d'),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone
            ],
            'subscription' => [
                'id' => $subscription->id,
                'username' => $subscription->username,
                'status' => $subscription->status,
                'expires_at' => $subscription->expires_at->format('Y-m-d H:i:s')
            ],
            'package' => [
                'name' => $package->name,
                'data_limit_mb' => $package->data_limit,
                'bandwidth_up' => $package->bandwidth_upload,
                'bandwidth_down' => $package->bandwidth_download
            ],
            'usage' => [
                'total_mb' => $this->total_mb,
                'upload_mb' => $this->upload_mb,
                'download_mb' => $this->download_mb,
                'session_count' => $this->session_count,
                'session_time' => $this->formatted_session_time,
                'avg_session_time' => $this->average_session_time
            ],
            'limits' => [
                'data_used_percentage' => $package->data_limit ? round(($this->total_mb / $package->data_limit) * 100, 2) : 0,
                'remaining_mb' => $package->data_limit ? max(0, $package->data_limit - $this->total_mb) : 'unlimited'
            ],
            'warnings' => []
        ];

        // Add warnings if applicable
        if ($package->data_limit && $this->total_mb >= ($package->data_limit * 0.9)) {
            $report['warnings'][] = 'Approaching data limit (90% used)';
        }

        if ($this->session_count > 50) {
            $report['warnings'][] = 'High number of sessions detected';
        }

        if ($package->data_limit && $this->total_mb >= $package->data_limit) {
            $report['warnings'][] = 'Data limit exceeded - subscription may be suspended';
        }

        return $report;
    }

    // Static method to sync all usage data from RADIUS
    public static function syncAllUsageFromRadius($date = null)
    {
        $date = $date ?? now()->toDateString();

        // Get all active subscriptions
        $activeSubscriptions = Subscription::where('status', 'active')
                                         ->where('expires_at', '>', $date)
                                         ->get();

        $syncedCount = 0;
        foreach ($activeSubscriptions as $subscription) {
            $usage = self::firstOrCreate([
                'subscription_id' => $subscription->id,
                'username' => $subscription->username,
                'date' => $date
            ]);

            if ($usage->updateUsageFromRadius()) {
                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    // Method to generate comprehensive analytics
    public static function generateAnalyticsReport($startDate, $endDate)
    {
        $totalUsage = self::whereBetween('date', [$startDate, $endDate])
                         ->sum('total_bytes');

        $totalSessions = self::whereBetween('date', [$startDate, $endDate])
                            ->sum('session_count');

        $totalTime = self::whereBetween('date', [$startDate, $endDate])
                        ->sum('session_time');

        $uniqueUsers = self::whereBetween('date', [$startDate, $endDate])
                          ->distinct('username')
                          ->count();

        $topUsers = self::whereBetween('date', [$startDate, $endDate])
                       ->groupBy('username')
                       ->selectRaw('username, SUM(total_bytes) as total_usage')
                       ->orderByDesc('total_usage')
                       ->limit(10)
                       ->get();

        $dailyAverages = self::whereBetween('date', [$startDate, $endDate])
                            ->groupBy('date')
                            ->selectRaw('date, AVG(total_bytes) as avg_usage, AVG(session_count) as avg_sessions')
                            ->orderBy('date')
                            ->get();

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => \Carbon\Carbon::parse($startDate)->diffInDays($endDate) + 1
            ],
            'totals' => [
                'total_usage_gb' => round($totalUsage / (1024 * 1024 * 1024), 2),
                'total_sessions' => $totalSessions,
                'total_time_hours' => round($totalTime / 3600, 2),
                'unique_users' => $uniqueUsers
            ],
            'averages' => [
                'avg_usage_per_user_mb' => $uniqueUsers > 0 ? round($totalUsage / $uniqueUsers / (1024 * 1024), 2) : 0,
                'avg_sessions_per_user' => $uniqueUsers > 0 ? round($totalSessions / $uniqueUsers, 1) : 0,
                'avg_session_duration_minutes' => $totalSessions > 0 ? round($totalTime / $totalSessions / 60, 1) : 0
            ],
            'top_users' => $topUsers->map(function($user) {
                return [
                    'username' => $user->username,
                    'total_usage_gb' => round($user->total_usage / (1024 * 1024 * 1024), 2)
                ];
            }),
            'daily_trends' => $dailyAverages->map(function($day) {
                return [
                    'date' => $day->date,
                    'avg_usage_mb' => round($day->avg_usage / (1024 * 1024), 2),
                    'avg_sessions' => round($day->avg_sessions, 1)
                ];
            })
        ];
    }
}
