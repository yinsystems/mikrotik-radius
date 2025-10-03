<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RadAcct extends Model
{
    protected $table = 'radacct';
    protected $primaryKey = 'radacctid';
    public $timestamps = false;

    protected $fillable = [
        'acctsessionid',
        'acctuniqueid',
        'username',
        'realm',
        'nasipaddress',
        'nasportid',
        'nasporttype',
        'acctstarttime',
        'acctupdatetime',
        'acctstoptime',
        'acctinterval',
        'acctsessiontime',
        'acctauthentic',
        'connectinfo_start',
        'connectinfo_stop',
        'acctinputoctets',
        'acctoutputoctets',
        'calledstationid',
        'callingstationid',
        'acctterminatecause',
        'servicetype',
        'framedprotocol',
        'framedipaddress',
        'framedipv6address',
        'framedipv6prefix',
        'framedinterfaceid',
        'delegatedipv6prefix'
    ];

    protected $casts = [
        'radacctid' => 'integer',
        'acctinterval' => 'integer',
        'acctsessiontime' => 'integer',
        'acctinputoctets' => 'integer',
        'acctoutputoctets' => 'integer',
        'acctstarttime' => 'datetime',
        'acctupdatetime' => 'datetime',
        'acctstoptime' => 'datetime'
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

    public function nas()
    {
        return $this->belongsTo(Nas::class, 'nasipaddress', 'nasname');
    }

    // Helper methods
    public function getTotalBytesAttribute()
    {
        return ($this->acctinputoctets ?? 0) + ($this->acctoutputoctets ?? 0);
    }

    public function getTotalMbAttribute()
    {
        return round($this->total_bytes / (1024 * 1024), 2);
    }

    public function getTotalGbAttribute()
    {
        return round($this->total_bytes / (1024 * 1024 * 1024), 3);
    }

    public function getFormattedSizeAttribute()
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

    public function getSessionDurationAttribute()
    {
        if ($this->acctsessiontime) {
            $hours = floor($this->acctsessiontime / 3600);
            $minutes = floor(($this->acctsessiontime % 3600) / 60);
            $seconds = $this->acctsessiontime % 60;

            if ($hours > 0) {
                return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            } else {
                return sprintf('%02d:%02d', $minutes, $seconds);
            }
        }
        return '00:00';
    }

    public function isActiveSession()
    {
        return is_null($this->acctstoptime);
    }

    // Static methods for analytics
    public static function getTotalUsage($username, Carbon $from = null, Carbon $to = null)
    {
        $query = self::where('username', $username);

        if ($from) $query->where('acctstarttime', '>=', $from);
        if ($to) $query->where('acctstarttime', '<=', $to);

        return $query->sum(\DB::raw('acctinputoctets + acctoutputoctets'));
    }

    public static function getActiveSessions($username = null)
    {
        $query = self::whereNull('acctstoptime');

        if ($username) {
            $query->where('username', $username);
        }

        return $query->get();
    }

    public static function getSessionCount($username, Carbon $from = null, Carbon $to = null)
    {
        $query = self::where('username', $username);

        if ($from) $query->where('acctstarttime', '>=', $from);
        if ($to) $query->where('acctstarttime', '<=', $to);

        return $query->count();
    }

    public static function getUserSessionTime($username, Carbon $from = null, Carbon $to = null)
    {
        $query = self::where('username', $username);

        if ($from) $query->where('acctstarttime', '>=', $from);
        if ($to) $query->where('acctstarttime', '<=', $to);

        return $query->sum('acctsessiontime');
    }

    public static function getTopUsers($limit = 10, Carbon $from = null, Carbon $to = null)
    {
        $query = self::query();

        if ($from) $query->where('acctstarttime', '>=', $from);
        if ($to) $query->where('acctstarttime', '<=', $to);

        return $query->groupBy('username')
                    ->selectRaw('username, SUM(acctinputoctets + acctoutputoctets) as total_usage, COUNT(*) as session_count, SUM(acctsessiontime) as total_time')
                    ->orderByDesc('total_usage')
                    ->limit($limit)
                    ->get();
    }

    public static function getDailyUsage(Carbon $date = null)
    {
        $date = $date ?? today();

        return self::whereDate('acctstarttime', $date)
                  ->groupBy('username')
                  ->selectRaw('username, SUM(acctinputoctets + acctoutputoctets) as total_usage, COUNT(*) as session_count, SUM(acctsessiontime) as total_time')
                  ->get();
    }

    public static function getUsageByNas(Carbon $from = null, Carbon $to = null)
    {
        $query = self::query();

        if ($from) $query->where('acctstarttime', '>=', $from);
        if ($to) $query->where('acctstarttime', '<=', $to);

        return $query->groupBy('nasipaddress')
                    ->selectRaw('nasipaddress, SUM(acctinputoctets + acctoutputoctets) as total_usage, COUNT(*) as session_count, COUNT(DISTINCT username) as unique_users')
                    ->get();
    }

    public static function getConcurrentSessions(Carbon $timestamp = null)
    {
        $timestamp = $timestamp ?? now();

        return self::where('acctstarttime', '<=', $timestamp)
                  ->where(function($query) use ($timestamp) {
                      $query->whereNull('acctstoptime')
                           ->orWhere('acctstoptime', '>=', $timestamp);
                  })
                  ->count();
    }

    public static function terminateUserSessions($username, $reason = 'Admin Disconnect')
    {
        return self::where('username', $username)
                  ->whereNull('acctstoptime')
                  ->update([
                      'acctstoptime' => now(),
                      'acctterminatecause' => $reason
                  ]);
    }

    // Scopes
    public function scopeByUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('acctstoptime');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('acctstoptime');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('acctstarttime', today());
    }

    public function scopeYesterday($query)
    {
        return $query->whereDate('acctstarttime', yesterday());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('acctstarttime', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('acctstarttime', now()->month)
                    ->whereYear('acctstarttime', now()->year);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('acctstarttime', [$start, $end]);
    }

    public function scopeByNas($query, $nasIp)
    {
        return $query->where('nasipaddress', $nasIp);
    }

    public function scopeHighUsage($query, $thresholdMb = 1000)
    {
        return $query->whereRaw('(acctinputoctets + acctoutputoctets) >= ?', [$thresholdMb * 1024 * 1024]);
    }

    public function scopeLongSessions($query, $thresholdHours = 4)
    {
        return $query->where('acctsessiontime', '>=', $thresholdHours * 3600);
    }
}
