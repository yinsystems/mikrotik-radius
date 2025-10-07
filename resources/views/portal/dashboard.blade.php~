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
                    <span class="text-blue-700 font-medium">Password:</span>
                    <div class="flex items-center space-x-2">
                        <span id="currentPassword" class="font-mono bg-white px-3 py-1 rounded border text-blue-900">
                            {{ $customer->password ? str_repeat('•', strlen($customer->password)) : 'Not set' }}
                        </span>
                        <button onclick="togglePasswordVisibility('currentPassword', '{{ $customer->password ?? '' }}')"
                                class="text-blue-600 hover:text-blue-700" title="Show/hide password">
                            <i id="currentPasswordIcon" class="fas fa-eye"></i>
                        </button>
                        @if($customer->password)
                        <button onclick="copyToClipboard('{{ $customer->password }}', 'Password')"
                                class="text-blue-600 hover:text-blue-700" title="Copy password">
                            <i class="fas fa-copy"></i>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            <div class="mt-3 p-3 bg-blue-100 rounded text-sm text-blue-800">
                <i class="fas fa-info-circle mr-1"></i>
                Use these credentials to connect to the WiFi network after purchasing a package.
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="border-t border-gray-200 pt-6">
            <h4 class="font-medium text-gray-900 mb-4 flex items-center">
                <i class="fas fa-lock text-green-600 mr-2"></i>
                Change WiFi Password
            </h4>

            <form id="changePasswordForm" class="space-y-4">
                @csrf

                <!-- Current Password -->
                <div>
                    <label for="currentPasswordInput" class="block text-sm font-medium text-gray-700 mb-2">
                        Current Password *
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="currentPasswordInput"
                               name="current_password"
                               class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter current password"
                               required>
                        <button type="button"
                                onclick="togglePasswordInput('currentPasswordInput')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <i id="currentPasswordInputIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div>
                    <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-2">
                        New Password *
                    </label>
                    <div class="relative">
                        <input type="text"
                               id="newPassword"
                               name="new_password"
                               class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter new password"
                               minlength="6"
                               required>
                        <button type="button"
                                onclick="generateNewPassword()"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-blue-600 hover:text-blue-700"
                                title="Generate strong password">
                            <i class="fas fa-magic"></i>
                        </button>
                    </div>

                    <!-- Password strength indicators -->
                    <div class="mt-2">
                        <div class="flex items-center space-x-4 text-xs">
                            <div class="flex items-center space-x-1">
                                <div id="newLengthCheck" class="w-2 h-2 rounded-full bg-gray-300"></div>
                                <span class="text-gray-600">6+ characters</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <div id="newComplexCheck" class="w-2 h-2 rounded-full bg-gray-300"></div>
                                <span class="text-gray-600">Letters & numbers</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirm New Password -->
                <div>
                    <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm New Password *
                    </label>
                    <input type="text"
                           id="confirmPassword"
                           name="confirm_password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Confirm new password"
                           required>
                    <div id="passwordMatchIndicator" class="mt-1 text-xs hidden">
                        <span id="passwordMatchText"></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 transition-colors btn-loading"
                        id="changePasswordBtn">
                    <span class="btn-text">
                        <i class="fas fa-save mr-2"></i>
                        Update WiFi Password
                    </span>
                    <div class="spinner hidden"></div>
                </button>
            </form>
        </div>
    </div>

    <!-- Package History Section -->
    @if(isset($packageHistory) && $packageHistory->count() > 0)
        <div class="bg-white rounded-xl card-shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-history text-indigo-600 mr-2"></i>
                Complete Package History
                <span class="ml-auto text-sm text-gray-500 font-normal">{{ $packageHistory->total() }} total</span>
            </h3>

            @if($packageHistory->count() > 0)
                <div class="space-y-4">
                    @foreach($packageHistory as $subscription)
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-box text-indigo-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900">{{ $subscription->package->name }}</h4>
                                        <p class="text-sm text-gray-500 mb-1">
                                            Purchased: {{ $subscription->created_at->format('M j, Y g:i A') }}
                                        </p>
                                        @if($subscription->expires_at)
                                            <p class="text-xs text-gray-400">
                                                @if($subscription->expires_at->isPast())
                                                    Expired: {{ $subscription->expires_at->format('M j, Y g:i A') }}
                                                @else
                                                    Expires: {{ $subscription->expires_at->format('M j, Y g:i A') }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xl font-bold text-gray-900">
                                        GH₵{{ number_format($subscription->package->price, 2) }}
                                    </div>
                                    <span class="px-3 py-1 text-sm rounded-full
                                        @if($subscription->status === 'active') bg-green-100 text-green-800
                                        @elseif($subscription->status === 'expired') bg-red-100 text-red-800
                                        @elseif($subscription->status === 'suspended') bg-yellow-100 text-yellow-800
                                        @elseif($subscription->status === 'pending') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($subscription->status) }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-3">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-download text-blue-500"></i>
                                    <span class="text-gray-700">{{ $subscription->package->data_limit_display }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-clock text-green-500"></i>
                                    <span class="text-gray-700">{{ $subscription->package->duration_display }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-tachometer-alt text-purple-500"></i>
                                    <span class="text-gray-700">{{ $subscription->package->bandwidth_display }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-users text-orange-500"></i>
                                    <span class="text-gray-700">{{ $subscription->package->simultaneous_users ?? 'Unlimited' }} Devices</span>
                                </div>
                            </div>

                            @if($subscription->payment)
                                <div class="border-t border-gray-100 pt-3 flex items-center justify-between text-sm">
                                    <div class="flex items-center space-x-4">
                                        <span class="text-gray-600">
                                            <i class="fas fa-credit-card mr-1"></i>
                                            Payment: {{ ucfirst($subscription->payment->method) }}
                                        </span>
                                        <span class="px-2 py-1 text-xs rounded-full
                                            @if($subscription->payment->status === 'completed') bg-green-100 text-green-800
                                            @elseif($subscription->payment->status === 'failed') bg-red-100 text-red-800
                                            @elseif($subscription->payment->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucfirst($subscription->payment->status) }}
                                        </span>
                                    </div>
                                    @if($subscription->payment->reference)
                                        <span class="text-gray-500 font-mono text-xs">
                                            Ref: {{ $subscription->payment->reference }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($packageHistory->hasPages())
                    <div class="mt-6 flex justify-center">
                        {{ $packageHistory->links() }}
                    </div>
                @endif
            @endif
        </div>
    @else
        <div class="bg-gray-50 rounded-xl p-8 text-center">
            <div class="w-16 h-16 mx-auto bg-gray-200 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-history text-2xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Package History</h3>
            <p class="text-gray-600 text-sm mb-4">
                You haven't purchased any packages yet. Start by selecting a package that suits your needs.
            </p>
            <a href="{{ route('portal.packages') }}"
               class="inline-flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                <i class="fas fa-shopping-cart"></i>
                <span>Browse Packages</span>
            </a>
        </div>
    @endif

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

    // Generate new password
    function generateNewPassword() {
        const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        const specialChars = '!@#$%&*';
        let password = '';

        // Ensure we have at least one letter and one number
        password += chars.charAt(Math.floor(Math.random() * 26)); // Letter
        password += '23456789'.charAt(Math.floor(Math.random() * 8)); // Number

        // Fill the rest randomly
        for (let i = 2; i < 8; i++) {
            if (i === 7 && Math.random() > 0.5) {
                password += specialChars.charAt(Math.floor(Math.random() * specialChars.length));
            } else {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
        }

        // Shuffle the password
        password = password.split('').sort(() => Math.random() - 0.5).join('');

        document.getElementById('newPassword').value = password;
        document.getElementById('confirmPassword').value = password;

        // Trigger validation
        document.getElementById('newPassword').dispatchEvent(new Event('input'));
        document.getElementById('confirmPassword').dispatchEvent(new Event('input'));

        showAlert('Success', 'Strong password generated and filled in both fields!');
    }

    // Password validation for change password form
    document.addEventListener('DOMContentLoaded', function() {
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const newLengthCheck = document.getElementById('newLengthCheck');
        const newComplexCheck = document.getElementById('newComplexCheck');
        const passwordMatchIndicator = document.getElementById('passwordMatchIndicator');
        const passwordMatchText = document.getElementById('passwordMatchText');

        newPasswordInput.addEventListener('input', function() {
            const password = this.value;

            // Check length requirement
            if (password.length >= 6) {
                newLengthCheck.classList.remove('bg-gray-300');
                newLengthCheck.classList.add('bg-green-500');
            } else {
                newLengthCheck.classList.remove('bg-green-500');
                newLengthCheck.classList.add('bg-gray-300');
            }

            // Check complexity requirement
            if (/(?=.*[a-zA-Z])(?=.*[0-9])/.test(password)) {
                newComplexCheck.classList.remove('bg-gray-300');
                newComplexCheck.classList.add('bg-green-500');
            } else {
                newComplexCheck.classList.remove('bg-green-500');
                newComplexCheck.classList.add('bg-gray-300');
            }

            checkPasswordMatch();
        });

        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword.length > 0) {
                passwordMatchIndicator.classList.remove('hidden');

                if (newPassword === confirmPassword) {
                    passwordMatchText.textContent = '✓ Passwords match';
                    passwordMatchText.className = 'text-green-600';
                } else {
                    passwordMatchText.textContent = '✗ Passwords do not match';
                    passwordMatchText.className = 'text-red-600';
                }
            } else {
                passwordMatchIndicator.classList.add('hidden');
            }
        }

        // Handle change password form submission
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const currentPassword = document.getElementById('currentPasswordInput').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (!currentPassword) {
                showAlert('Error', 'Please enter your current password');
                return;
            }

            if (!newPassword) {
                showAlert('Error', 'Please enter a new password');
                return;
            }

            if (newPassword.length < 6) {
                showAlert('Error', 'New password must be at least 6 characters long');
                return;
            }

            if (!/(?=.*[a-zA-Z])(?=.*[0-9])/.test(newPassword)) {
                showAlert('Error', 'New password must contain both letters and numbers');
                return;
            }

            if (newPassword !== confirmPassword) {
                showAlert('Error', 'New passwords do not match');
                return;
            }

            if (currentPassword === newPassword) {
                showAlert('Error', 'New password must be different from current password');
                return;
            }

            // Submit the form
            const formData = new FormData(this);

            fetch("{{ route('portal.change-password') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Success', 'WiFi password updated successfully!');

                    // Clear form
                    document.getElementById('changePasswordForm').reset();

                    // Update the displayed password
                    document.getElementById('currentPassword').textContent = '•'.repeat(newPassword.length);

                    // Reset indicators
                    passwordMatchIndicator.classList.add('hidden');
                    newLengthCheck.classList.remove('bg-green-500');
                    newLengthCheck.classList.add('bg-gray-300');
                    newComplexCheck.classList.remove('bg-green-500');
                    newComplexCheck.classList.add('bg-gray-300');

                    // Reload page after 2 seconds to refresh the current password display
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert('Error', data.message || 'Failed to update password');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error', 'An error occurred. Please try again.');
            });
        });
    });

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
