@php
$settings = new \App\Settings\GeneralSettings();
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'WiFi Portal')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('logo/wifi.png') }}">

    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Poppins', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'Noto Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Styles -->
    <style>
        .loading {
            display: none;
        }
        .loading.show {
            display: block;
        }
        .btn-loading {
            position: relative;
        }
        .btn-loading:disabled {
            opacity: 0.7;
        }
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-shadow {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">

    <!-- Main Container -->
    <div class="min-h-screen flex flex-col">

        <!-- Header -->
        <header class="gradient-bg text-white py-4 px-4">
            <div class="max-w-md mx-auto flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <img src="{{ asset('logo/wifi.png') }}" alt="WiFi Logo" class="w-8 h-8 object-contain">
                    <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
                </div>

                @if(Session::has('customer_phone'))
                    <form action="{{ route('portal.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-white hover:text-gray-200 transition-colors">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                @endif
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 py-6 px-4">
            <div class="max-w-md mx-auto">
                @yield('content')
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t py-4 px-4 text-center text-gray-600 text-sm">
            <div class="max-w-md mx-auto">
                @if(isset($settings) && $settings)
                    @if($settings->whatsapp_support_enabled)
                        <div class="mb-2">
                            <a href="{{ \App\Helpers\SettingsHelper::getWhatsAppUrl('Need help with WiFi connection') }}"
                               target="_blank"
                               class="inline-flex items-center space-x-2 text-green-600 hover:text-green-700">
                                <i class="fab fa-whatsapp"></i>
                                <span>WhatsApp Support</span>
                            </a>
                        </div>
                    @endif

                    @if($settings->company_phone)
                        <div class="mb-2">
                            <a href="tel:{{ $settings->company_phone }}"
                               class="inline-flex items-center space-x-2 text-blue-600 hover:text-blue-700">
                                <i class="fas fa-phone"></i>
                                <span>{{ $settings->company_phone }}</span>
                            </a>
                        </div>
                    @endif
                @endif

                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <!-- Alert Modal -->
    <div id="alertModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 id="alertTitle" class="text-lg font-medium text-gray-900"></h3>
                    <button onclick="closeAlert()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="alertMessage" class="text-gray-600 mb-4"></div>
                <div class="flex justify-end">
                    <button onclick="closeAlert()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
                <div class="spinner"></div>
                <span> <i class="fa-solid fa-spinner"></i> Processing...</span>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-xl font-semibold text-gray-900">Terms and Conditions</h3>
                    <button onclick="closeTermsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[70vh]">
                    <div class="space-y-4 text-sm text-gray-700 leading-relaxed">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">1. Acceptance of Terms</h4>
                            <p>By using our WiFi service, you agree to these terms and conditions. If you do not agree, please do not use our service.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">2. Service Usage</h4>
                            <p>Our WiFi service is provided for legitimate internet access. You agree to:</p>
                            <ul class="list-disc ml-6 mt-2 space-y-1">
                                <li>Use the service responsibly and lawfully</li>
                                <li>Not engage in illegal activities or content sharing</li>
                                <li>Not attempt to hack, disrupt, or misuse the network</li>
                                <li>Not consume excessive bandwidth that affects other users</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">3. Account Registration</h4>
                            <p>To access our service, you must provide accurate information during registration. You are responsible for maintaining the confidentiality of your account credentials.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">4. Data Usage and Fair Use</h4>
                            <p>While we provide internet access, we reserve the right to manage network traffic and implement fair use policies to ensure optimal service for all users.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">5. Service Availability</h4>
                            <p>We strive to provide reliable service but cannot guarantee 100% uptime. Service may be interrupted for maintenance, technical issues, or other circumstances beyond our control.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">6. Content Restrictions</h4>
                            <p>You agree not to access or transmit:</p>
                            <ul class="list-disc ml-6 mt-2 space-y-1">
                                <li>Illegal or copyrighted content</li>
                                <li>Malicious software or viruses</li>
                                <li>Spam or unsolicited communications</li>
                                <li>Content that violates others' privacy or rights</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">7. Limitation of Liability</h4>
                            <p>We provide this service "as is" and are not liable for any damages arising from your use of the service, including but not limited to data loss, security breaches, or service interruptions.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">8. Termination</h4>
                            <p>We reserve the right to suspend or terminate your access to the service at any time for violation of these terms or any other reason deemed necessary.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">9. Changes to Terms</h4>
                            <p>These terms may be updated from time to time. Continued use of the service constitutes acceptance of any changes.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">10. Contact Information</h4>
                            <p>If you have questions about these terms, please contact us through the support channels provided in the portal.</p>
                        </div>

                        <div class="mt-6 pt-4 border-t text-xs text-gray-500">
                            <p>Last updated: {{ date('F j, Y') }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end p-6 border-t bg-gray-50">
                    <button onclick="closeTermsModal()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-xl font-semibold text-gray-900">Privacy Policy</h3>
                    <button onclick="closePrivacyModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[70vh]">
                    <div class="space-y-4 text-sm text-gray-700 leading-relaxed">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">1. Information We Collect</h4>
                            <p>To provide our WiFi service, we collect the following information:</p>
                            <ul class="list-disc ml-6 mt-2 space-y-1">
                                <li><strong>Personal Information:</strong> Name, phone number, and email address (if provided)</li>
                                <li><strong>Device Information:</strong> MAC address, device type, and browser information</li>
                                <li><strong>Usage Data:</strong> Connection times, data usage, and websites visited</li>
                                <li><strong>Location Data:</strong> Approximate location based on WiFi access point</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">2. How We Use Your Information</h4>
                            <p>We use your information to:</p>
                            <ul class="list-disc ml-6 mt-2 space-y-1">
                                <li>Provide and maintain WiFi service access</li>
                                <li>Authenticate your identity and manage your account</li>
                                <li>Send service notifications and support communications</li>
                                <li>Monitor network usage and ensure fair access for all users</li>
                                <li>Improve our service quality and troubleshoot issues</li>
                                <li>Comply with legal requirements and regulations</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">3. Information Sharing</h4>
                            <p>We do not sell or rent your personal information. We may share information only in these circumstances:</p>
                            <ul class="list-disc ml-6 mt-2 space-y-1">
                                <li>With your explicit consent</li>
                                <li>To comply with legal obligations or court orders</li>
                                <li>To protect our rights, property, or safety</li>
                                <li>With service providers who help operate our network (under strict confidentiality)</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">4. Data Security</h4>
                            <p>We implement appropriate security measures to protect your information, including encryption, access controls, and regular security assessments. However, no internet transmission is 100% secure.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">5. Data Retention</h4>
                            <p>We retain your information for as long as necessary to provide services and comply with legal requirements. Usage logs are typically retained for 12 months, while account information is kept until you request deletion.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">6. Your Rights</h4>
                            <p>You have the right to:</p>
                            <ul class="list-disc ml-6 mt-2 space-y-1">
                                <li>Access and review your personal information</li>
                                <li>Request correction of inaccurate information</li>
                                <li>Request deletion of your account and data</li>
                                <li>Object to processing of your information</li>
                                <li>Withdraw consent where applicable</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">7. Cookies and Tracking</h4>
                            <p>We use essential cookies to maintain your session and provide service. We do not use tracking cookies for advertising purposes.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">8. Third-Party Services</h4>
                            <p>Our service may link to third-party websites or services. We are not responsible for their privacy practices. Please review their privacy policies separately.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">9. Children's Privacy</h4>
                            <p>Our service is not intended for children under 13. We do not knowingly collect personal information from children under 13.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">10. Contact Us</h4>
                            <p>If you have questions about this privacy policy or wish to exercise your rights, please contact us through the support channels provided in the portal.</p>
                        </div>

                        <div class="mt-6 pt-4 border-t text-xs text-gray-500">
                            <p>Last updated: {{ date('F j, Y') }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end p-6 border-t bg-gray-50">
                    <button onclick="closePrivacyModal()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // CSRF Token for AJAX requests
        window.axios = axios;
        window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Alert functions
        function showAlert(title, message, type = 'info') {
            document.getElementById('alertTitle').textContent = title;
            document.getElementById('alertMessage').textContent = message;
            document.getElementById('alertModal').classList.remove('hidden');
        }

        function closeAlert() {
            document.getElementById('alertModal').classList.add('hidden');
        }

        // Loading functions
        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        // Form submission helper
        function submitForm(formId, url, options = {}) {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            showLoading();

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();

                if (data.success) {
                    if (options.onSuccess) {
                        options.onSuccess(data);
                    } else if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        showAlert('Success', data.message, 'success');
                    }
                } else {
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat().join('\n');
                        showAlert('Validation Error', errorMessages, 'error');
                    } else {
                        showAlert('Error', data.message, 'error');
                    }
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showAlert('Error', 'An unexpected error occurred. Please try again.', 'error');
            });
        }

        // Phone number formatting
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 0 && !value.startsWith('0')) {
                value = '0' + value;
            }
            input.value = value;
        }

        // OTP input handling
        function handleOtpInput(element, nextElementId) {
            if (element.value.length >= 1 && nextElementId) {
                document.getElementById(nextElementId).focus();
            }
        }

        // Auto-submit OTP when complete
        function checkOtpComplete() {
            const inputs = document.querySelectorAll('.otp-input');
            let otp = '';
            inputs.forEach(input => otp += input.value);

            if (otp.length === 6) {
                document.getElementById('otp').value = otp;
                // Auto-submit or enable submit button
                const submitBtn = document.querySelector('.otp-submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            }
        }

        // Session timeout management
        let sessionTimeoutWarning = null;
        let sessionActivityTimer = null;

        function resetSessionTimer() {
            // Clear existing timers
            if (sessionTimeoutWarning) clearTimeout(sessionTimeoutWarning);
            if (sessionActivityTimer) clearTimeout(sessionActivityTimer);

            // Set warning timer (25 minutes - 5 minutes before timeout)
            sessionTimeoutWarning = setTimeout(() => {
                showSessionWarning();
            }, 25 * 60 * 1000);

            // Set activity timer (30 minutes - session timeout)
            sessionActivityTimer = setTimeout(() => {
                handleSessionTimeout();
            }, 30 * 60 * 1000);
        }

        function showSessionWarning() {
            if (confirm('Your session will expire in 5 minutes due to inactivity. Click OK to continue your session.')) {
                // User clicked OK, extend session
                fetch(window.location.href, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(() => {
                    resetSessionTimer();
                }).catch(() => {
                    // If request fails, probably already expired
                    handleSessionTimeout();
                });
            } else {
                // User chose to logout or clicked cancel
                window.location.href = "{{ route('portal.index') }}";
            }
        }

        function handleSessionTimeout() {
            showAlert('Session Expired', 'Your session has expired due to inactivity. Please log in again.', 'warning');
            setTimeout(() => {
                window.location.href = "{{ route('portal.index') }}";
            }, 3000);
        }

        // Track user activity to reset session timer
        function trackActivity() {
            resetSessionTimer();
        }

        // Modal functions for Terms and Privacy Policy
        function showTermsModal() {
            document.getElementById('termsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeTermsModal() {
            document.getElementById('termsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function showPrivacyModal() {
            document.getElementById('privacyModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closePrivacyModal() {
            document.getElementById('privacyModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id === 'termsModal') {
                closeTermsModal();
            }
            if (e.target.id === 'privacyModal') {
                closePrivacyModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTermsModal();
                closePrivacyModal();
            }
        });

        // Only set up session management on protected pages
        @if(Session::has('portal_customer_id'))
        document.addEventListener('DOMContentLoaded', function() {
            resetSessionTimer();

            // Track various user activities
            ['click', 'keypress', 'scroll', 'mousemove', 'touchstart'].forEach(event => {
                document.addEventListener(event, trackActivity, { passive: true });
            });
        });
        @endif
    </script>

    @stack('scripts')
</body>
</html>
