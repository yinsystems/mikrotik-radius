<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RadPostAuth extends Model
{
    protected $table = 'radpostauth';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'pass',
        'reply',
        'authdate'
    ];

    protected $casts = [
        'id' => 'integer',
        'authdate' => 'datetime'
    ];

    // Relationships
    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'username', 'username');
    }

    public function customer()
    {
        return $this->hasOneThrough(Customer::class, Subscription::class, 'username', 'id', 'username', 'customer_id');
    }

    // Helper methods
    public function isSuccessful()
    {
        return $this->reply === 'Access-Accept';
    }

    public function isFailed()
    {
        return $this->reply === 'Access-Reject';
    }

    // Static methods for security monitoring
    public static function getFailedAttempts($username, $minutes = 30)
    {
        return self::where('username', $username)
                  ->where('reply', 'Access-Reject')
                  ->where('authdate', '>=', now()->subMinutes($minutes))
                  ->count();
    }

    public static function getSuccessfulLogins($username, $minutes = 30)
    {
        return self::where('username', $username)
                  ->where('reply', 'Access-Accept')
                  ->where('authdate', '>=', now()->subMinutes($minutes))
                  ->count();
    }

    public static function getLastSuccessfulLogin($username)
    {
        return self::where('username', $username)
                  ->where('reply', 'Access-Accept')
                  ->orderBy('authdate', 'desc')
                  ->first();
    }

    public static function getLastFailedLogin($username)
    {
        return self::where('username', $username)
                  ->where('reply', 'Access-Reject')
                  ->orderBy('authdate', 'desc')
                  ->first();
    }

    public static function isBruteForceAttempt($username, $threshold = 5, $minutes = 30)
    {
        return self::getFailedAttempts($username, $minutes) >= $threshold;
    }

    public static function getLoginAttempts($username, $hours = 24)
    {
        return self::where('username', $username)
                  ->where('authdate', '>=', now()->subHours($hours))
                  ->orderBy('authdate', 'desc')
                  ->get();
    }

    public static function getTopFailedUsers($limit = 10, $hours = 24)
    {
        return self::where('reply', 'Access-Reject')
                  ->where('authdate', '>=', now()->subHours($hours))
                  ->groupBy('username')
                  ->selectRaw('username, COUNT(*) as failed_attempts, MAX(authdate) as last_attempt')
                  ->orderByDesc('failed_attempts')
                  ->limit($limit)
                  ->get();
    }

    public static function getAuthenticationStats($hours = 24)
    {
        $stats = self::where('authdate', '>=', now()->subHours($hours))
                    ->groupBy('reply')
                    ->selectRaw('reply, COUNT(*) as count')
                    ->pluck('count', 'reply');

        return [
            'successful' => $stats['Access-Accept'] ?? 0,
            'failed' => $stats['Access-Reject'] ?? 0,
            'total' => $stats->sum(),
            'success_rate' => $stats->sum() > 0 ? round(($stats['Access-Accept'] ?? 0) / $stats->sum() * 100, 2) : 0
        ];
    }

    public static function getHourlyAuthStats($hours = 24)
    {
        return self::where('authdate', '>=', now()->subHours($hours))
                  ->selectRaw('DATE_FORMAT(authdate, "%H:00") as hour, reply, COUNT(*) as count')
                  ->groupBy('hour', 'reply')
                  ->orderBy('hour')
                  ->get()
                  ->groupBy('hour')
                  ->map(function ($group) {
                      return [
                          'successful' => $group->where('reply', 'Access-Accept')->sum('count'),
                          'failed' => $group->where('reply', 'Access-Reject')->sum('count')
                      ];
                  });
    }

    public static function getUserLoginHistory($username, $limit = 50)
    {
        return self::where('username', $username)
                  ->orderBy('authdate', 'desc')
                  ->limit($limit)
                  ->get();
    }

    public static function getUniqueUsersCount($hours = 24)
    {
        return self::where('authdate', '>=', now()->subHours($hours))
                  ->where('reply', 'Access-Accept')
                  ->distinct('username')
                  ->count('username');
    }

    public static function getSuspiciousActivity($threshold = 10, $minutes = 30)
    {
        return self::where('authdate', '>=', now()->subMinutes($minutes))
                  ->where('reply', 'Access-Reject')
                  ->groupBy('username')
                  ->selectRaw('username, COUNT(*) as failed_attempts, MAX(authdate) as last_attempt')
                  ->havingRaw('COUNT(*) >= ?', [$threshold])
                  ->orderByDesc('failed_attempts')
                  ->get();
    }

    public static function cleanupOldLogs($days = 30)
    {
        return self::where('authdate', '<', now()->subDays($days))->delete();
    }

    // Scopes
    public function scopeByUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('reply', 'Access-Accept');
    }

    public function scopeFailed($query)
    {
        return $query->where('reply', 'Access-Reject');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('authdate', today());
    }

    public function scopeYesterday($query)
    {
        return $query->whereDate('authdate', yesterday());
    }

    public function scopeLastHours($query, $hours = 24)
    {
        return $query->where('authdate', '>=', now()->subHours($hours));
    }

    public function scopeLastMinutes($query, $minutes = 30)
    {
        return $query->where('authdate', '>=', now()->subMinutes($minutes));
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('authdate', [$start, $end]);
    }

    public function scopeBruteForce($query, $threshold = 5, $minutes = 30)
    {
        return $query->where('authdate', '>=', now()->subMinutes($minutes))
                    ->where('reply', 'Access-Reject')
                    ->groupBy('username')
                    ->havingRaw('COUNT(*) >= ?', [$threshold]);
    }
}
