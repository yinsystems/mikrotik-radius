@php use App\Settings\GeneralSettings; @endphp
@extends('layouts.portal')

@section('title', 'Login - Campus WiFi')

@push('styles')
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet">
    <style>
        .video-js {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .video-js .vjs-big-play-button {
            border-radius: 50%;
            background-color: rgba(59, 130, 246, 0.9);
            border: none;
            font-size: 3em;
        }
        .video-js .vjs-big-play-button:hover {
            background-color: rgba(37, 99, 235, 0.9);
        }
    </style>
@endpush

@php
$settings  = new GeneralSettings();
@endphp

@section('content')
    <div class="space-y-6">

        <!-- Back Button -->
        <div class="flex items-center">
            <a href="{{ route('portal.index') }}" class="text-blue-600 hover:text-blue-700 flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>

        <!-- Login Form -->
        <div id="loginForm" class="bg-white rounded-xl card-shadow p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <img src="{{ asset('logo/wifi.png') }}" alt="WiFi Logo"
                         class="max-w-full max-h-full object-contain">
                </div>
                <h2 class="text-xl font-bold text-gray-900">Welcome Back</h2>
                <p class="text-gray-600 text-sm">Login to access your account</p>
            </div>

            <form id="loginFormSubmit" class="space-y-4">
                @csrf

                <!-- Phone Number -->
                <div>
                    <label for="loginPhone" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number *
                    </label>
                    <input type="tel"
                           id="loginPhone"
                           name="phone"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="0244123456"
                           oninput="formatPhoneNumber(this)"
                           required>
                    <p class="text-xs text-gray-500 mt-1">Enter your registered phone number</p>
                </div>

                <!-- Password Field (Hidden by default, shown when OTP is disabled) -->
                <div id="passwordField" class="hidden">
                    <label for="loginPassword" class="block text-sm font-medium text-gray-700 mb-2">
                        Portal Password *
                    </label>
                    <input type="password"
                           id="loginPassword"
                           name="password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your WiFi password">
                    <p class="text-xs text-gray-500 mt-1">The password you created during registration</p>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 transition-colors btn-loading"
                        id="loginBtn">
                    <span class="btn-text" id="loginBtnText">Send OTP</span>
                    <div class="spinner hidden"></div>
                </button>
            </form>

            <!-- Register Link -->
            <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                <p class="text-gray-600 text-sm">
                    Don't have an account?
                    <a href="{{ route('portal.register') }}" class="text-blue-600 hover:text-blue-700 font-medium">Register
                        here</a>
                </p>
            </div>
        </div>

        <!-- OTP Verification Form (Hidden Initially) -->
        <div id="otpForm" class="bg-white rounded-xl card-shadow p-6 hidden">
            <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-mobile-alt text-2xl text-blue-600"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Verify Your Phone</h2>
                <p class="text-gray-600 text-sm">Enter the 6-digit code sent to <span id="phoneDisplay"
                                                                                      class="font-medium"></span></p>
                <p class="text-xs text-gray-500 mt-2">Code expires in <span id="countdown"
                                                                            class="font-medium text-red-600"></span></p>
            </div>

            <form id="verifyForm" class="space-y-4">
                @csrf

                <!-- OTP Input -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3 text-center">Verification Code</label>
                    <div class="flex justify-center space-x-2 mb-4">
                        <input type="text" maxlength="1"
                               class="otp-input w-10 h-12 text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-medium"
                               onkeyup="handleOtpInput(this, 'otp2'); checkOtpComplete();" id="otp1">
                        <input type="text" maxlength="1"
                               class="otp-input w-10 h-12 text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-medium"
                               onkeyup="handleOtpInput(this, 'otp3'); checkOtpComplete();" id="otp2">
                        <input type="text" maxlength="1"
                               class="otp-input w-10 h-12 text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-medium"
                               onkeyup="handleOtpInput(this, 'otp4'); checkOtpComplete();" id="otp3">
                        <input type="text" maxlength="1"
                               class="otp-input w-10 h-12 text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-medium"
                               onkeyup="handleOtpInput(this, 'otp5'); checkOtpComplete();" id="otp4">
                        <input type="text" maxlength="1"
                               class="otp-input w-10 h-12 text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-medium"
                               onkeyup="handleOtpInput(this, 'otp6'); checkOtpComplete();" id="otp5">
                        <input type="text" maxlength="1"
                               class="otp-input w-10 h-12 text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-medium"
                               onkeyup="handleOtpInput(this, null); checkOtpComplete();" id="otp6">
                    </div>
                    <input type="hidden" id="otp" name="otp">
                </div>

                <!-- Verify Button -->
                <button type="submit"
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 transition-colors btn-loading otp-submit-btn"
                        id="verifyBtn"
                        disabled>
                    <span class="btn-text">Verify & Login</span>
                    <div class="spinner hidden"></div>
                </button>

                <!-- Resend Link -->
                <div class="text-center">
                    <button type="button"
                            id="resendBtn"
                            class="text-blue-600 hover:text-blue-700 text-sm underline"
                            onclick="resendOtp()"
                            disabled>
                        Resend Code
                    </button>
                    <p class="text-xs text-gray-500 mt-1">You can resend in <span id="resendCountdown">60</span> seconds
                    </p>
                </div>
            </form>

            <!-- Change Phone Number -->
            <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                <button type="button"
                        onclick="changePhoneNumber()"
                        class="text-gray-600 hover:text-gray-700 text-sm">
                    <i class="fas fa-edit mr-1"></i>
                    Change Phone Number
                </button>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <!-- Video.js JavaScript -->
    <script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>

    <script>
        // Initialize Video.js player when promotional video exists
        @if($settings->promotional_video)
            document.addEventListener('DOMContentLoaded', function() {
                if (document.getElementById('promotional-video')) {
                    var player = videojs('promotional-video', {
                        controls: true,
                        fluid: true,
                        responsive: true,
                        preload: 'metadata',
                        playbackRates: [0.5, 1, 1.25, 1.5, 2],
                        techOrder: ['html5'],
                        html5: {
                            vhs: {
                                overrideNative: true
                            }
                        }
                    });

                    // Optional: Add custom event listeners
                    player.ready(function() {
                        console.log('Promotional video player is ready');
                    });

                    player.on('play', function() {
                        console.log('Promotional video started playing');
                    });

                    player.on('ended', function() {
                        console.log('Promotional video ended');
                    });
                }
            });
        @endif

        let countdownTimer;
        let resendTimer;
        let expiresAt;
        let loginOtpEnabled = true; // Default to true

        // Check OTP status on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkOtpStatus();
        });

        // Check if OTP is enabled in settings
        function checkOtpStatus() {
            fetch("{{ route('portal.otp.status') }}")
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loginOtpEnabled = data.login_otp_enabled;
                        updateLoginForm();
                    }
                })
                .catch(error => {
                    console.error('Error checking OTP status:', error);
                    // Default to OTP enabled if there's an error
                    loginOtpEnabled = true;
                });
        }

        // Update the login form based on OTP status
        function updateLoginForm() {
            const buttonText = document.getElementById('loginBtnText');
            const passwordField = document.getElementById('passwordField');
            const passwordInput = document.getElementById('loginPassword');

            if (loginOtpEnabled) {
                buttonText.textContent = 'Send OTP';
                passwordField.classList.add('hidden');
                passwordInput.removeAttribute('required');
            } else {
                buttonText.textContent = 'Login';
                passwordField.classList.remove('hidden');
                passwordInput.setAttribute('required', 'required');
            }
        }

        // Handle login form submission
        document.getElementById('loginFormSubmit').addEventListener('submit', function (e) {
            e.preventDefault();

            const phone = document.getElementById('loginPhone').value;

            if (!phone) {
                showAlert('Error', 'Please enter your phone number');
                return;
            }

            // Validate phone number format
            const phoneRegex = /^0[0-9]{9}$/;
            if (!phoneRegex.test(phone)) {
                showAlert('Error', 'Please enter a valid 10-digit phone number starting with 0');
                return;
            }

            // Handle submission based on OTP status
            if (loginOtpEnabled) {
                // Original flow: Send OTP for verification
                submitForm('loginFormSubmit', "{{ route('portal.login.submit') }}", {
                    onSuccess: function (data) {
                        showOtpForm(phone, data.expires_at);
                    }
                });
            } else {
                // Password-based login
                const password = document.getElementById('loginPassword').value;
                if (!password) {
                    showAlert('Error', 'Please enter your password');
                    return;
                }

                submitDirectLogin();
            }
        });

        // Direct login with password
        function submitDirectLogin() {
            const formData = {
                phone: document.getElementById('loginPhone').value,
                password: document.getElementById('loginPassword').value,
                skip_otp: true // Flag to indicate OTP should be skipped
            };

            console.log('Submitting direct login with data:', formData);
            showLoading();

            fetch("{{ route('portal.login.submit') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                console.log('Login response:', data);

                if (data.success) {
                    // Login successful, redirect to dashboard
                    // showAlert('Success', 'Login successful!', function() {
                    //
                    // });
                    console.log('Redirecting to:', data.redirect_url || "{{ route('portal.dashboard') }}");
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.href = "{{ route('portal.dashboard') }}";
                    }
                } else {
                    console.error('Login failed:', data.message);
                    showAlert('Error', data.message || 'Login failed. Please try again.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Login error:', error);
                showAlert('Error', 'Login failed. Please try again.');
            });
        }

        // Handle OTP verification form submission
        document.getElementById('verifyForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const otp = document.getElementById('otp').value;
            if (otp.length !== 6) {
                showAlert('Error', 'Please enter the complete 6-digit code');
                return;
            }

            submitForm('verifyForm', "{{ route('portal.verify.login.otp') }}");
        });

        function showOtpForm(phone, expirationTime) {
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('otpForm').classList.remove('hidden');
            document.getElementById('phoneDisplay').textContent = phone;

            expiresAt = new Date(expirationTime);
            startCountdown();
            startResendTimer();

            // Focus on first OTP input
            document.getElementById('otp1').focus();
        }

        function startCountdown() {
            countdownTimer = setInterval(function () {
                const now = new Date().getTime();
                const distance = expiresAt.getTime() - now;

                if (distance > 0) {
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    document.getElementById('countdown').textContent =
                        (minutes < 10 ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
                } else {
                    document.getElementById('countdown').textContent = '00:00';
                    clearInterval(countdownTimer);
                    showAlert('Error', 'OTP has expired. Please request a new one.');
                }
            }, 1000);
        }

        function startResendTimer() {
            let seconds = 60;
            document.getElementById('resendBtn').disabled = true;

            resendTimer = setInterval(function () {
                seconds--;
                document.getElementById('resendCountdown').textContent = seconds;

                if (seconds <= 0) {
                    clearInterval(resendTimer);
                    document.getElementById('resendBtn').disabled = false;
                    document.querySelector('#resendBtn').nextElementSibling.textContent = 'You can now resend the code';
                }
            }, 1000);
        }

        function resendOtp() {
            const phone = document.getElementById('loginPhone').value;

            fetch("{{ route('portal.login.submit') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    phone: phone
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Success', 'New OTP sent successfully');
                        expiresAt = new Date(data.expires_at);
                        startCountdown();
                        startResendTimer();

                        // Clear OTP inputs
                        document.querySelectorAll('.otp-input').forEach(input => input.value = '');
                        document.getElementById('otp').value = '';
                        document.getElementById('verifyBtn').disabled = true;
                        document.getElementById('otp1').focus();
                    } else {
                        showAlert('Error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error', 'Failed to resend OTP. Please try again.');
                });
        }

        function changePhoneNumber() {
            document.getElementById('otpForm').classList.add('hidden');
            document.getElementById('loginForm').classList.remove('hidden');

            // Clear timers
            if (countdownTimer) clearInterval(countdownTimer);
            if (resendTimer) clearInterval(resendTimer);

            // Clear form
            document.getElementById('loginPhone').value = '';

            // Clear OTP inputs
            document.querySelectorAll('.otp-input').forEach(input => input.value = '');
            document.getElementById('otp').value = '';
            document.getElementById('verifyBtn').disabled = true;
        }

        // Enhanced OTP input handling
        function handleOtpInput(element, nextElementId) {
            // Only allow numbers
            element.value = element.value.replace(/[^0-9]/g, '');

            if (element.value.length >= 1 && nextElementId) {
                document.getElementById(nextElementId).focus();
            }

            checkOtpComplete();
        }

        function checkOtpComplete() {
            const inputs = document.querySelectorAll('.otp-input');
            let otp = '';
            inputs.forEach(input => otp += input.value);

            document.getElementById('otp').value = otp;

            if (otp.length === 6) {
                document.getElementById('verifyBtn').disabled = false;
                document.getElementById('verifyBtn').classList.add('bg-blue-600', 'hover:bg-blue-700');
                document.getElementById('verifyBtn').classList.remove('bg-gray-400');
            } else {
                document.getElementById('verifyBtn').disabled = true;
                document.getElementById('verifyBtn').classList.remove('bg-blue-600', 'hover:bg-blue-700');
                document.getElementById('verifyBtn').classList.add('bg-gray-400');
            }
        }
    </script>
@endpush
