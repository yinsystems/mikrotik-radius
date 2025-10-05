<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class PortalSessionMiddleware
{
    /**
     * Handle an incoming request for portal session management.
     * 
     * This middleware handles:
     * - Session timeout management
     * - Security measures for portal sessions
     * - Customer authentication verification
     * - Activity tracking
     */
    public function handle(Request $request, Closure $next)
    {
        // Define session timeout (30 minutes for portal)
        $timeoutMinutes = 30;
        
        // Check if customer is logged into portal
        $customerId = Session::get('portal_customer_id');
        $customerPhone = Session::get('portal_customer_phone');
        $lastActivity = Session::get('portal_last_activity');
        
        // If not authenticated, redirect to login
        if (!$customerId || !$customerPhone) {
            $this->clearPortalSession();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be logged in to access this page.',
                    'not_authenticated' => true,
                    'redirect' => route('portal.index')
                ], 401);
            }
            
            return redirect()->route('portal.index')
                ->with('error', 'You must be logged in to access this page.');
        }
        
        // Session timeout disabled - sessions never expire
        // if ($lastActivity) {
        //     $lastActivityTime = Carbon::parse($lastActivity);
        //     
        //     // Check if session has expired
        //     if ($lastActivityTime->diffInMinutes(Carbon::now()) > $timeoutMinutes) {
        //         // Session expired - clear all portal data
        //         $this->clearPortalSession();
        //         
        //         if ($request->expectsJson()) {
        //             return response()->json([
        //                 'success' => false,
        //                 'message' => 'Your session has expired. Please log in again.',
        //                 'session_expired' => true,
        //                 'redirect' => route('portal.index')
        //             ], 401);
        //         }
        //         
        //         return redirect()->route('portal.index')
        //             ->with('error', 'Your session has expired. Please log in again.');
        //     }
        // }
        
        // Update last activity
        Session::put('portal_last_activity', Carbon::now()->toDateTimeString());
        
        // Regenerate session ID periodically for security (every 15 minutes)
        $sessionCreated = Session::get('portal_session_created', Carbon::now());
        if (Carbon::parse($sessionCreated)->diffInMinutes(Carbon::now()) >= 15) {
            Session::regenerate();
            Session::put('portal_session_created', Carbon::now()->toDateTimeString());
        }
        
        // Add security headers for portal
        $response = $next($request);
        
        if (method_exists($response, 'header')) {
            $response->header('X-Frame-Options', 'DENY');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-XSS-Protection', '1; mode=block');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        
        return $response;
    }
    
    /**
     * Clear all portal-related session data
     */
    private function clearPortalSession()
    {
        Session::forget([
            'portal_customer_id',
            'portal_customer_phone',
            'portal_last_activity',
            'portal_session_created',
            'selected_package_id',
            'payment_id',
            'otp_verified_phone',
            'otp_attempts',
            'otp_last_sent',
            // Also clear backward compatibility variables
            'customer_id',
            'customer_phone'
        ]);
    }
}
