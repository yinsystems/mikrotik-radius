@php
$settings= new \App\Settings\GeneralSettings();
@endphp
@extends('layouts.portal')

@section('title', 'Welcome - WiFi Portal')

@push('styles')
    <!-- Custom Video Player Styles -->
    <style>
        .custom-video-player {
            position: relative;
            width: 100%;
            max-width: 100%;
            border-radius: 0.5rem;
            overflow: hidden;
            background: #000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .custom-video-player video {
            width: 100%;
            height: auto;
            display: block;
            background: #000;
        }

        .custom-video-player video::-webkit-media-controls-panel {
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
        }

        .custom-video-player video::-webkit-media-controls-play-button {
            background-color: rgba(59, 130, 246, 0.9);
            border-radius: 50%;
        }

        .custom-video-player video::-webkit-media-controls-play-button:hover {
            background-color: rgba(37, 99, 235, 1);
        }

        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.3);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .custom-video-player:hover .video-overlay {
            opacity: 1;
        }

        .play-button-overlay {
            width: 80px;
            height: 80px;
            background: rgba(59, 130, 246, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            transition: all 0.3s ease;
        }

        .play-button-overlay:hover {
            background: rgba(37, 99, 235, 1);
            transform: scale(1.1);
        }

        .play-button-overlay i {
            color: white;
            font-size: 24px;
            margin-left: 4px;
        }

        .video-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            color: white;
            padding: 20px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .custom-video-player:hover .video-info {
            transform: translateY(0);
        }

        .video-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            display: none;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- Promotional Video Section -->
    @if($settings->promotional_video)
        <div class="bg-white rounded-xl card-shadow p-6">
            <div class="text-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-play-circle text-blue-600 mr-2"></i>
                   Learn How To Use.
                </h3>
                <p class="text-gray-600 text-sm mt-1">Learn about our high-speed WiFi services</p>
            </div>

            <div class="custom-video-player">
                <video
                    id="promotional-video"
                    controls
                    preload="metadata"
                    playsinline
                    webkit-playsinline>
                    <source src="{{ asset('storage/' . $settings->promotional_video) }}" type="video/mp4">
                    <p class="text-center text-gray-600 p-8">
                        Your browser doesn't support HTML5 video.
                        <a href="{{ asset('storage/' . $settings->promotional_video) }}"
                           class="text-blue-600 hover:text-blue-700 underline"
                           download>Download the video</a> instead.
                    </p>
                </video>
            </div>
        </div>
    @endif


    <!-- Welcome Card -->
    <div class="bg-white rounded-xl card-shadow p-6 text-center">
        <div class="mb-6">
            <div class="w-24 h-24 mx-auto mb-4 flex items-center justify-center">
                <img src="{{ asset('logo/wifi-campus.png') }}" alt="WiFi Campus Logo" class="max-w-full max-h-full object-contain">
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome to WiFi</h2>
            <p class="text-gray-600">Enjoy True Unlimited Internet Access</p>
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
            <!-- Browse Packages Button (no authentication required) -->
            <a href="{{ route('portal.packages') }}"
               class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 transition-colors flex items-center justify-center space-x-2 border-2 border-transparent hover:border-green-500">
                <i class="fas fa-eye"></i>
                <span>Browse All Packages</span>
            </a>

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
                <span>Portal - Login</span>
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
    // Simple HTML5 video player functionality
    @if($settings->promotional_video)
        let video = null;
        let playIcon = null;
        let videoLoading = null;

        document.addEventListener('DOMContentLoaded', function() {
            video = document.getElementById('promotional-video');
            playIcon = document.getElementById('playIcon');
            videoLoading = document.getElementById('videoLoading');

            if (video) {
                // Event listeners
                video.addEventListener('loadstart', function() {
                    showLoading();
                    console.log('Video loading started');
                });

                video.addEventListener('loadedmetadata', function() {
                    hideLoading();
                    console.log('Video metadata loaded');
                });

                video.addEventListener('canplay', function() {
                    hideLoading();
                    console.log('Video can start playing');
                });

                video.addEventListener('play', function() {
                    updatePlayIcon(false);
                    console.log('Video started playing');
                });

                video.addEventListener('pause', function() {
                    updatePlayIcon(true);
                    console.log('Video paused');
                });

                video.addEventListener('ended', function() {
                    updatePlayIcon(true);
                    console.log('Video ended');
                });

                video.addEventListener('error', function(e) {
                    hideLoading();
                    console.error('Video error:', e);
                    alert('Error loading video. Please check the file format and try again.');
                });

                video.addEventListener('waiting', function() {
                    showLoading();
                });

                video.addEventListener('playing', function() {
                    hideLoading();
                });

                // Initialize
                updatePlayIcon(!video.paused);
            }
        });

        function toggleVideo() {
            if (video) {
                if (video.paused) {
                    video.play().catch(function(error) {
                        console.error('Play failed:', error);
                        alert('Unable to play video. Please try clicking the video controls directly.');
                    });
                } else {
                    video.pause();
                }
            }
        }

        function updatePlayIcon(showPlay) {
            if (playIcon) {
                playIcon.className = showPlay ? 'fas fa-play' : 'fas fa-pause';
            }
        }

        function showLoading() {
            if (videoLoading) {
                videoLoading.style.display = 'block';
            }
        }

        function hideLoading() {
            if (videoLoading) {
                videoLoading.style.display = 'none';
            }
        }

        // Make functions available globally
        window.toggleVideo = toggleVideo;
    @endif

    // Auto-redirect if user has active session
    @if(Session::has('customer_phone'))
        window.addEventListener('load', function() {
            window.location.href = "{{ route('portal.dashboard') }}";
        });
    @endif
</script>
@endpush
