@php
$settings= new \App\Settings\GeneralSettings();
@endphp
@extends('layouts.portal')

@section('title', 'Welcome - WiFi Portal')

@section('content')
<div class="space-y-6">
    <!-- Welcome Card -->
    <div class="bg-white rounded-xl card-shadow p-6 text-center">
        <div class="mb-6">
            <div class="w-24 h-24 mx-auto mb-4 flex items-center justify-center">
                <img src="{{ asset('logo/wifi-campus.png') }}" alt="WiFi Campus Logo" class="max-w-full max-h-full object-contain">
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome to WiFi</h2>
            <p class="text-gray-600">Enjoy unlimited true Unlimited Data</p>
        </div>

        @if(isset($settings) && @$settings?->announcement_text)
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 text-left">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            {{ @$settings?->announcement_text }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="space-y-4">
            <!-- New User Button with Free Trial Promotion -->
            <div class="relative">
                <!-- Free Trial Badge -->
                <div class="absolute -top-3 -right-3 z-10">
                    <div class="bg-gradient-to-r from-green-400 to-green-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg animate-pulse">
                        FREE TRIAL
                    </div>
                </div>

                <a href="{{ route('portal.register') }}"
                   class="group relative w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl font-medium hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex flex-col items-center space-y-2 overflow-hidden">

                    <!-- Background pattern -->
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-400/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                    <!-- Main content -->
                    <div class="relative z-10 flex items-center space-x-3">
                            <i class="fas fa-user-plus text-xl"></i>
                        <div class="text-left">
                            <div class="text-lg font-bold">Create Account</div>
                            <div class="text-blue-100 text-sm font-medium">Get started with free trial access!</div>
                        </div>
                    </div>

                    <!-- Benefits list -->
                    <div class="relative z-10 w-full pt-2 border-t border-blue-400/30">
                        <div class="flex justify-center space-x-6 text-xs text-blue-100">
                            <div class="flex items-center space-x-1">
                                <i class="fas fa-check-circle text-green-300"></i>
                                <span>Unlimited Data</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <i class="fas fa-wifi text-green-300"></i>
                                <span>Try for Free</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <i class="fas fa-clock text-green-300"></i>
                                <span>Pay GHC 0.00</span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Existing User Button -->
            <a href="{{ route('portal.login') }}"
               class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-gray-700 transition-colors flex items-center justify-center space-x-2 border-2 border-transparent hover:border-gray-500">
                <i class="fas fa-sign-in-alt"></i>
                <span>Existing User - Login</span>
            </a>
        </div>
    </div>

    <!-- Instructions Card -->
    @if(isset($settings) && $settings->user_instructions)
        <div class="bg-white rounded-xl card-shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-list-ul text-blue-600 mr-2"></i>
                How to Connect
            </h3>
            <div class="text-gray-600 text-sm leading-relaxed">
                {!! nl2br(e($settings->user_instructions)) !!}
            </div>
        </div>
    @endif

    <!-- Available Packages Preview -->
    @if(isset($packages) && $packages->count() > 0)
        <div class="bg-white rounded-xl card-shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-box text-green-600 mr-2"></i>
                Available Packages
            </h3>
            <div class="grid gap-3">
                @foreach($packages->take(3) as $package)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900">{{ $package->name }}</h4>
                            <p class="text-sm text-gray-600">{{ $package->data_limit }}GB - {{ $package->validity_days }} days</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-green-600">GH₵{{ number_format($package->price, 2) }}</p>
                        </div>
                    </div>
                @endforeach

                @if($packages->count() > 3)
                    <p class="text-center text-sm text-gray-500 mt-2">
                        And {{ $packages->count() - 3 }} more packages available
                    </p>
                @endif
            </div>
        </div>
    @endif

    <!-- Advertisement Card -->
    @if(isset($settings) && @$settings?->advertisement_text)
        <div class="bg-gradient-to-r from-purple-400 to-pink-400 rounded-xl p-6 text-white text-center">
            <h3 class="font-bold text-lg mb-2">Special Offer!</h3>
            <p class="text-sm opacity-90">{{ $settings?->advertisement_text }}</p>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    // Auto-redirect if user has active session
    @if(Session::has('customer_phone'))
        window.addEventListener('load', function() {
            window.location.href = "{{ route('portal.dashboard') }}";
        });
    @endif
</script>
@endpush
