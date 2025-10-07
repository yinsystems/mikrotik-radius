@extends('layouts.portal')

@section('title', 'Dashboard - WiFi Portal')
@php
$settings = new \App\Settings\GeneralSettings();
@endphp
@section('content')
<div class="space-y-6">

    <!-- Welcome Header -->
    <div class="text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome Back!</h2>
        <p class="text-gray-600">Manage your internet packages and view usage</p>
    </div>

    <!-- Announcements -->
    @if(isset($settings) && @$settings?->announcement_enabled)
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center">
                <i class="fas fa-bullhorn text-blue-600 mr-2"></i>
                Announcements
            </h3>
            <div class="text-blue-800 text-sm leading-relaxed">
                {!! nl2br(e(@$settings?->announcement_message)) !!}
            </div>
        </div>
    @endif
    <!-- Active Subscription Card -->
    @if($activeSubscription)
        <div class="bg-white rounded-xl card-shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <img src="{{ asset('logo/wifi.png') }}" alt="WiFi Logo" class="w-5 h-5 mr-2">
                    Active Package
                </h3>
                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                    Active
                </span>
            </div>

            <div class="bg-green-50 rounded-lg p-4 mb-4 border border-green-200">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xl font-bold text-gray-900">{{ $activeSubscription->package->name }}</h4>
                    <div class="text-right">
                        <div class="text-lg font-bold text-green-600">
                            GH₵{{ number_format($activeSubscription->package->price, 2) }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Paid
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-download text-blue-600"></i>
                        <span class="text-sm text-gray-700">
                                <strong>{{ $activeSubscription->package->data_limit_display }}</strong> Data
                            </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock text-blue-600"></i>
                        <span class="text-sm text-gray-700">
                                <strong>{{ $activeSubscription->package->duration_display }}</strong>
                            </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-tachometer-alt text-blue-600"></i>
                        <span class="text-sm text-gray-700">
                                <strong>{{ $activeSubscription->package->bandwidth_display }}</strong>
                            </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-users text-blue-600"></i>
                        <span class="text-sm text-gray-700">
                                <strong>{{ $activeSubscription->package->simultaneous_users ?? 'Unlimited' }}</strong> Devices
                            </span>
                    </div>
                </div>


                <!-- Subscription Details -->
                <div class="border-t border-green-200 pt-3">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Started:</span>
                            <span class="font-medium">{{ $activeSubscription->starts_at->format('M j, Y H:i:s') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Expires:</span>
                            <span class="font-medium">{{ $activeSubscription->expires_at->format('M j, Y H:i:s') }}</span>
                        </div>
                    </div>

                    <!-- Time Remaining -->
                    @php
                        $timeRemaining = $activeSubscription->expires_at->diffForHumans(null, false, true);

                        // Determine expiring soon based on package duration type
                        $package = $activeSubscription->package;
                        $isExpiringSoon = false;

                        if ($package->duration_type === 'minutely') {
                            // For minute packages, consider expiring soon if less than 20% of duration remains
                            $totalMinutes = $package->duration_value;
                            $remainingMinutes = $activeSubscription->expires_at->diffInMinutes();
                            $isExpiringSoon = $remainingMinutes < ($totalMinutes * 0.2);
                        } elseif ($package->duration_type === 'hourly') {
                            // For hourly packages, expiring soon if less than 1 hour remains
                            $isExpiringSoon = $activeSubscription->expires_at->diffInHours() < 1;
                        } else {
                            // For daily/weekly/monthly packages, expiring soon if less than 24 hours remains
                            $isExpiringSoon = $activeSubscription->expires_at->diffInHours() < 24;
                        }
                    @endphp
                    <div class="mt-3 pt-3 border-t border-green-200">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Time Remaining:</span>
                            <span class="font-bold {{ $isExpiringSoon ? 'text-red-600' : 'text-green-600' }}">
                                {{ $timeRemaining }}
                            </span>
                        </div>

                        @if($isExpiringSoon)
                            <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Your package is expiring soon! Consider purchasing a new package.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Connect Button -->
            <div class="text-center">
                <a href="http://192.168.77.1/logout"

                   target="_blank"
                   class="inline-flex items-center space-x-2 bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Connect to Internet</span>
                </a>
            </div>
        </div>
    @else
        <!-- No Active Subscription -->
        <div class="bg-white rounded-xl card-shadow p-6 text-center">
            <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-wifi-slash text-2xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Package</h3>
            <p class="text-gray-600 text-sm mb-4">
                You don't have an active internet package. Purchase a package to get started.
            </p>
            <a href="{{ route('portal.packages') }}"
               class="inline-flex items-center space-x-2 bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                <i class="fas fa-shopping-cart"></i>
                <span>Browse Packages</span>
            </a>
        </div>
    @endif

    <!-- Quick Actions Card -->
    <div class="bg-white rounded-xl card-shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-bolt text-purple-600 mr-2"></i>
            Quick Actions
        </h3>

        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('portal.packages') }}"
               class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-plus-circle text-2xl text-blue-600 mb-2"></i>
                <span class="text-sm font-medium text-blue-800">Buy Package</span>
            </a>

            <button onclick="refreshConnection()"
                    class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                <i class="fas fa-sync-alt text-2xl text-green-600 mb-2"></i>
                <span class="text-sm font-medium text-green-800">Refresh</span>
            </button>

            @if(isset($settings) && $settings->whatsapp_support_enabled)
                <a href="{{ \App\Helpers\SettingsHelper::getWhatsAppUrl('Need help with my WiFi package') }}"
                   target="_blank"
                   class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fab fa-whatsapp text-2xl text-green-600 mb-2"></i>
                    <span class="text-sm font-medium text-green-800">Support</span>
                </a>
            @endif

            <button onclick="viewHistory()"
                    class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <i class="fas fa-history text-2xl text-gray-600 mb-2"></i>
                <span class="text-sm font-medium text-gray-800">History</span>
            </button>
        </div>
    </div>

    <!-- Account Information Card -->
    <div class="bg-white rounded-xl card-shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-user text-indigo-600 mr-2"></i>
            Account Information
        </h3>

        <div class="space-y-3">
            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                <span class="text-gray-600">Phone Number:</span>
                <span class="font-medium">{{ $customer->phone }}</span>
            </div>

            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                <span class="text-gray-600">Member Since:</span>
                <span class="font-medium">{{ $customer->created_at->format('M j, Y') }}</span>
            </div>

            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                <span class="text-gray-600">Account Status:</span>
                <span class="px-2 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                    {{ ucfirst($customer->status) }}
                </span>
            </div>

            <div class="flex items-center justify-between py-2">
                <span class="text-gray-600">Last Login:</span>
                <span class="font-medium">{{ now()->format('M j, Y g:i A') }}</span>
            </div>
        </div>
    </div>

    <!-- WiFi Credentials & Password Change Card -->
    <div class="bg-white rounded-xl card-shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-wifi text-blue-600 mr-2"></i>
            WiFi Login Credentials
        </h3>

        <!-- Current WiFi Credentials -->
        <div class="bg-blue-50 rounded-lg p-4 mb-6">
            <h4 class="font-medium text-blue-900 mb-3 flex items-center">
                <i class="fas fa-key text-blue-600 mr-2"></i>
                Current WiFi Login Details
            </h4>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-blue-700 font-medium">Username:</span>
                    <div class="flex items-center space-x-2">
                        <span class="font-mono bg-white px-3 py-1 rounded border text-blue-900">{{ $customer->phone }}</span>
                        <button onclick="copyToClipboard('{{ $customer->phone }}', 'Username')"
                                class="text-blue-600 hover:text-blue-700" title="Copy username">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-blue-700 font-medium">WiFi Token:</span>
                    <div class="flex items-center space-x-2">
                        @if($customer->hasValidInternetToken())
                            <span id="currentToken" class="font-mono bg-white px-3 py-1 rounded border text-blue-900 text-lg font-bold tracking-wider">
                                {{ $customer->internet_token }}
                            </span>
                            <button onclick="copyToClipboard('{{ $customer->internet_token }}', 'WiFi Token')"
                                    class="text-blue-600 hover:text-blue-700" title="Copy WiFi token">
                                <i class="fas fa-copy"></i>
                            </button>
                        @else
                            <span class="text-gray-500 italic">Token available after subscription purchase</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="mt-3 p-3 bg-blue-100 rounded text-sm text-blue-800">
                <i class="fas fa-info-circle mr-1"></i>
                @if($customer->hasValidInternetToken())
                    Use these credentials to connect to WiFi: Username ({{ $customer->phone }}) + Token ({{ $customer->internet_token }})
                @else
                    Purchase a subscription to get your WiFi token. You'll use your phone number as username and a 6-digit token.
                @endif
            </div>
        </div>


    </div>

{{--    <!-- Package History Section -->--}}
{{--    @if(isset($packageHistory) && $packageHistory->count() > 0)--}}
{{--        <div class="bg-white rounded-xl card-shadow p-6">--}}
{{--            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">--}}
{{--                <i class="fas fa-history text-indigo-600 mr-2"></i>--}}
{{--                Complete Package History--}}
{{--                <span class="ml-auto text-sm text-gray-500 font-normal">{{ $packageHistory->total() }} total</span>--}}
{{--            </h3>--}}

{{--            @if($packageHistory->count() > 0)--}}
{{--                <div class="space-y-4">--}}
{{--                    @foreach($packageHistory as $subscription)--}}
{{--                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">--}}
{{--                            <div class="flex items-start justify-between mb-3">--}}
{{--                                <div class="flex items-center space-x-3">--}}
{{--                                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">--}}
{{--                                        <i class="fas fa-box text-indigo-600"></i>--}}
{{--                                    </div>--}}
{{--                                    <div>--}}
{{--                                        <h4 class="font-semibold text-gray-900">{{ $subscription->package->name }}</h4>--}}
{{--                                        <p class="text-sm text-gray-500 mb-1">--}}
{{--                                            Purchased: {{ $subscription->created_at->format('M j, Y g:i A') }}--}}
{{--                                        </p>--}}
{{--                                        @if($subscription->expires_at)--}}
{{--                                            <p class="text-xs text-gray-400">--}}
{{--                                                @if($subscription->expires_at->isPast())--}}
{{--                                                    Expired: {{ $subscription->expires_at->format('M j, Y g:i A') }}--}}
{{--                                                @else--}}
{{--                                                    Expires: {{ $subscription->expires_at->format('M j, Y g:i A') }}--}}
{{--                                                @endif--}}
{{--                                            </p>--}}
{{--                                        @endif--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                                <div class="text-right">--}}
{{--                                    <div class="text-xl font-bold text-gray-900">--}}
{{--                                        GH₵{{ number_format($subscription->package->price, 2) }}--}}
{{--                                    </div>--}}
{{--                                    <span class="px-3 py-1 text-sm rounded-full--}}
{{--                                        @if($subscription->status === 'active') bg-green-100 text-green-800--}}
{{--                                        @elseif($subscription->status === 'expired') bg-red-100 text-red-800--}}
{{--                                        @elseif($subscription->status === 'suspended') bg-yellow-100 text-yellow-800--}}
{{--                                        @elseif($subscription->status === 'pending') bg-blue-100 text-blue-800--}}
{{--                                        @else bg-gray-100 text-gray-800--}}
{{--                                        @endif">--}}
{{--                                        {{ ucfirst($subscription->status) }}--}}
{{--                                    </span>--}}
{{--                                </div>--}}
{{--                            </div>--}}

{{--                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-3">--}}
{{--                                <div class="flex items-center space-x-2">--}}
{{--                                    <i class="fas fa-download text-blue-500"></i>--}}
{{--                                    <span class="text-gray-700">{{ $subscription->package->data_limit_display }}</span>--}}
{{--                                </div>--}}
{{--                                <div class="flex items-center space-x-2">--}}
{{--                                    <i class="fas fa-clock text-green-500"></i>--}}
{{--                                    <span class="text-gray-700">{{ $subscription->package->duration_display }}</span>--}}
{{--                                </div>--}}
{{--                                <div class="flex items-center space-x-2">--}}
{{--                                    <i class="fas fa-tachometer-alt text-purple-500"></i>--}}
{{--                                    <span class="text-gray-700">{{ $subscription->package->bandwidth_display }}</span>--}}
{{--                                </div>--}}
{{--                                <div class="flex items-center space-x-2">--}}
{{--                                    <i class="fas fa-users text-orange-500"></i>--}}
{{--                                    <span class="text-gray-700">{{ $subscription->package->simultaneous_users ?? 'Unlimited' }} Devices</span>--}}
{{--                                </div>--}}
{{--                            </div>--}}

{{--                            @if($subscription->payment)--}}
{{--                                <div class="border-t border-gray-100 pt-3 flex items-center justify-between text-sm">--}}
{{--                                    <div class="flex items-center space-x-4">--}}
{{--                                        <span class="text-gray-600">--}}
{{--                                            <i class="fas fa-credit-card mr-1"></i>--}}
{{--                                            Payment: {{ ucfirst($subscription->payment->method) }}--}}
{{--                                        </span>--}}
{{--                                        <span class="px-2 py-1 text-xs rounded-full--}}
{{--                                            @if($subscription->payment->status === 'completed') bg-green-100 text-green-800--}}
{{--                                            @elseif($subscription->payment->status === 'failed') bg-red-100 text-red-800--}}
{{--                                            @elseif($subscription->payment->status === 'pending') bg-yellow-100 text-yellow-800--}}
{{--                                            @else bg-gray-100 text-gray-800--}}
{{--                                            @endif">--}}
{{--                                            {{ ucfirst($subscription->payment->status) }}--}}
{{--                                        </span>--}}
{{--                                    </div>--}}
{{--                                    @if($subscription->payment->reference)--}}
{{--                                        <span class="text-gray-500 font-mono text-xs">--}}
{{--                                            Ref: {{ $subscription->payment->reference }}--}}
{{--                                        </span>--}}
{{--                                    @endif--}}
{{--                                </div>--}}
{{--                            @endif--}}
{{--                        </div>--}}
{{--                    @endforeach--}}
{{--                </div>--}}

{{--                <!-- Pagination -->--}}
{{--                @if($packageHistory->hasPages())--}}
{{--                    <div class="mt-6 flex justify-center">--}}
{{--                        {{ $packageHistory->links() }}--}}
{{--                    </div>--}}
{{--                @endif--}}
{{--            @endif--}}
{{--        </div>--}}
{{--    @else--}}
{{--        <div class="bg-gray-50 rounded-xl p-8 text-center">--}}
{{--            <div class="w-16 h-16 mx-auto bg-gray-200 rounded-full flex items-center justify-center mb-4">--}}
{{--                <i class="fas fa-history text-2xl text-gray-400"></i>--}}
{{--            </div>--}}
{{--            <h3 class="text-lg font-medium text-gray-900 mb-2">No Package History</h3>--}}
{{--            <p class="text-gray-600 text-sm mb-4">--}}
{{--                You haven't purchased any packages yet. Start by selecting a package that suits your needs.--}}
{{--            </p>--}}
{{--            <a href="{{ route('portal.packages') }}"--}}
{{--               class="inline-flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">--}}
{{--                <i class="fas fa-shopping-cart"></i>--}}
{{--                <span>Browse Packages</span>--}}
{{--            </a>--}}
{{--        </div>--}}
{{--    @endif--}}

    <!-- Navigation -->
    <div class="flex justify-between items-center pt-4">
        <a href="{{ route('portal.index') }}"
           class="text-gray-600 hover:text-gray-700 flex items-center space-x-2">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>

        <button onclick="confirmLogout()"
                class="text-red-600 hover:text-red-700 flex items-center space-x-2">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>
    </div>

    <form id="logout-form" action="{{ route('portal.logout') }}" method="POST" class="hidden">
        @csrf
    </form>

</div>
@endsection

@push('scripts')
<script>
    function refreshConnection() {
        showLoading();

        // Simulate refresh
        setTimeout(() => {
            hideLoading();
            showAlert('Success', 'Connection status refreshed successfully!');
        }, 2000);
    }

    function viewHistory() {
        // Find the package history section by text content
        const historyHeadings = document.querySelectorAll('h3');
        let historySection = null;

        for (let heading of historyHeadings) {
            if (heading.textContent.includes('Complete Package History')) {
                historySection = heading;
                break;
            }
        }

        if (historySection) {
            // Scroll to the parent container for better positioning
            const historyContainer = historySection.closest('.bg-white');
            if (historyContainer) {
                historyContainer.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                    inline: 'nearest'
                });
            } else {
                historySection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                    inline: 'nearest'
                });
            }
        } else {
            // Fallback: scroll to bottom of page if history section not found
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        }
    }

    function confirmLogout() {
        if (confirm('Are you sure you want to logout?')) {
            document.getElementById('logout-form').submit();
        }
    }

    // Copy to clipboard function
    function copyToClipboard(text, type) {
        navigator.clipboard.writeText(text).then(() => {
            showAlert('Success', `${type} copied to clipboard!`);
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showAlert('Success', `${type} copied to clipboard!`);
        });
    }

    // Toggle password visibility for display
    function togglePasswordVisibility(elementId, password) {
        const element = document.getElementById(elementId);
        const icon = document.getElementById(elementId + 'Icon');

        if (element.textContent.includes('•')) {
            element.textContent = password;
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            element.textContent = '•'.repeat(password.length);
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Toggle password visibility for input fields
    function togglePasswordInput(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(inputId + 'Icon');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }



    // Auto-refresh subscription status every 30 seconds
    setInterval(() => {
        // In a real application, you would check subscription status here
        console.log('Checking subscription status...');
    }, 30000);

    // Check if user should be redirected to router
    @if($activeSubscription)
        // Show connect button prominently for active users
        console.log('User has active subscription');
    @endif
</script>
@endpush
