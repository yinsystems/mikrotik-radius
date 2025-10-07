@extends('layouts.portal')

@section('title', 'Select Package - WiFi Portal')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <!-- Navigation -->
    <div class="flex justify-between items-center pt-4">
        <a href="{{ route('portal.dashboard') }}"
           class="text-gray-600 hover:text-gray-700 flex items-center space-x-2">
            <i class="fas fa-arrow-left"></i>
            <span>Back</span>
        </a>
    </div>
    <div class="text-center">
        <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center">
            <img src="{{ asset('logo/wifi.png') }}" alt="WiFi Logo" class="max-w-full max-h-full object-contain">
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Choose Your Package</h2>
        <p class="text-gray-600">Select the internet package that suits your needs</p>
    </div>

    @if(@$settings?->advertisement_enabled)
        <div class="bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl p-4 text-white text-center">
            @if(@$settings?->advertisement_image)
                <div class="mb-3">
                    <img src="{{ asset('storage/' . $settings->advertisement_image) }}"
                         alt="{{ @$settings?->advertisement_title }}"
                         class="max-w-full h-32 mx-auto object-contain rounded-lg">
                </div>
            @endif

            <h3 class="font-bold text-lg mb-2">{{ @$settings?->advertisement_title ?: 'Special Offer!' }}</h3>
            <p class="text-sm opacity-90">{{ @$settings?->advertisement_description }}</p>

            @if(@$settings?->advertisement_link)
                <div class="mt-3">
                    <a href="{{ @$settings?->advertisement_link }}"
                       class="inline-block bg-white text-purple-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition-colors">
                        {{ @$settings?->advertisement_button_text ?: 'Learn More' }}
                    </a>
                </div>
            @endif
        </div>
    @endif
    <!-- Active Subscription Warning -->
    @if(isset($purchaseCheck) && $purchaseCheck['has_active'])
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5"></i>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-900 mb-2">Active Package Warning</h3>
                    <p class="text-yellow-800 text-sm mb-3">{{ $purchaseCheck['warning'] }}</p>

                    @if($purchaseCheck['active_subscription'])
                        <div class="bg-yellow-100 rounded-lg p-3">
                            <h4 class="font-medium text-yellow-900 mb-1">Current Package:</h4>
                            <div class="text-sm text-yellow-800">
                                <div class="flex justify-between mb-1">
                                    <span>{{ $purchaseCheck['active_subscription']->package->name }}</span>
                                    <span class="font-medium">Expires: {{ $purchaseCheck['active_subscription']->expires_at->format('M j, Y g:i A') }}</span>
                                </div>
                                <div class="text-xs">
                                    Time remaining: {{ $purchaseCheck['active_subscription']->expires_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Package Cards -->
    <div class="space-y-4">
        @forelse($packages as $package)
            <div class="bg-white rounded-xl card-shadow overflow-hidden border border-gray-200 hover:border-blue-300 transition-colors">
                <div class="p-6">
                    <!-- Package Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">{{ $package->name }}</h3>
                            @if($package->description)
                                <p class="text-gray-600 text-sm mt-1">{{ $package->description }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-green-600">
                                GH₵{{ number_format($package->price, 2) }}
                            </div>
                            @if($package->original_price && $package->original_price > $package->price)
                                <div class="text-sm text-gray-500 line-through">
                                    GH₵{{ number_format($package->original_price, 2) }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Package Features -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-download text-blue-600"></i>
                            <span class="text-sm text-gray-700">
                                <strong>{{ $package->data_limit_display }}</strong> Data
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-clock text-blue-600"></i>
                            <span class="text-sm text-gray-700">
                                <strong>{{ $package->duration_display }}</strong>
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-tachometer-alt text-blue-600"></i>
                            <span class="text-sm text-gray-700">
                                <strong>{{ $package->bandwidth_display }}</strong>
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-users text-blue-600"></i>
                            <span class="text-sm text-gray-700">
                                <strong>{{ $package->simultaneous_users ?? 'Unlimited' }}</strong> Devices
                            </span>
                        </div>
                    </div>



                    <!-- Select Button -->
                    <button type="button"
                            onclick="selectPackage({{ $package->id }}, '{{ $package->name }}', {{ $package->price }})"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 transition-colors flex items-center justify-center space-x-2">
                        <i class="fas fa-check"></i>
                        <span>Select This Package</span>
                    </button>
                </div>

                <!-- Popular/Recommended Badge -->
                @if($package->is_featured)
                    <div class="absolute top-4 right-4">
                        <span class="bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            POPULAR
                        </span>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl card-shadow p-8 text-center">
                <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-box-open text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Packages Available</h3>
                <p class="text-gray-600 text-sm">
                    We're currently updating our packages. Please try again later or contact support.
                </p>

                @if(isset($settings) && $settings->whatsapp_support_enabled)
                    <div class="mt-4">
                        <a href="{{ \App\Helpers\SettingsHelper::getWhatsAppUrl('Need help with packages') }}"
                           target="_blank"
                           class="inline-flex items-center space-x-2 text-green-600 hover:text-green-700">
                            <i class="fab fa-whatsapp"></i>
                            <span>Contact Support</span>
                        </a>
                    </div>
                @endif
            </div>
        @endforelse
    </div>

    <!-- Package History Section -->
    @if(isset($packageHistory) && $packageHistory->count() > 0)
        <div class="bg-white rounded-xl card-shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-history text-purple-600 mr-2"></i>
                Package History
                <span class="ml-auto text-sm text-gray-500 font-normal">Last {{ $packageHistory->count() }} purchases</span>
            </h3>

            <div class="space-y-3">
                @foreach($packageHistory as $subscription)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-box text-purple-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $subscription->package->name }}</h4>
                                    <p class="text-sm text-gray-500">
                                        {{ $subscription->created_at->format('M j, Y g:i A') }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-gray-900">
                                    GH₵{{ number_format($subscription->package->price, 2) }}
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full
                                    @if($subscription->status === 'active') bg-green-100 text-green-800
                                    @elseif($subscription->status === 'expired') bg-red-100 text-red-800
                                    @elseif($subscription->status === 'suspended') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($subscription->status) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 text-xs text-gray-600 mt-3">
                            <div class="flex items-center space-x-1">
                                <i class="fas fa-download text-blue-500"></i>
                                <span>{{ $subscription->package->data_limit_display }}</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <i class="fas fa-clock text-green-500"></i>
                                <span>{{ $subscription->package->duration_display }}</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <i class="fas fa-calendar text-purple-500"></i>
                                <span>
                                    @if($subscription->expires_at)
                                        @if($subscription->expires_at->isPast())
                                            Expired {{ $subscription->expires_at->diffForHumans() }}
                                        @else
                                            Expires {{ $subscription->expires_at->diffForHumans() }}
                                        @endif
                                    @else
                                        No expiry
                                    @endif
                                </span>
                            </div>
                        </div>

                        @if($subscription->payment)
                            <div class="mt-2 flex items-center justify-between text-xs">
                                <span class="text-gray-500">
                                    Payment: {{ ucfirst($subscription->payment->method) }}
                                </span>
                                @if($subscription->payment->reference)
                                    <span class="text-gray-500 font-mono">
                                        Ref: {{ $subscription->payment->reference }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4 text-center">
                <a href="{{ route('portal.dashboard') }}"
                   class="text-purple-600 hover:text-purple-700 text-sm font-medium">
                    View Full History →
                </a>
            </div>
        </div>
    @endif

    <!-- Information Cards -->
    @if(isset($settings))
        @if($settings->user_instructions)
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Important Information
                </h3>
                <div class="text-blue-800 text-sm leading-relaxed">
                    {!! nl2br(e($settings->user_instructions)) !!}
                </div>
            </div>
        @endif
    @endif

    <!-- Navigation -->
    <div class="flex justify-between items-center pt-4">
        <a href="{{ route('portal.index') }}"
           class="text-gray-600 hover:text-gray-700 flex items-center space-x-2">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Home</span>
        </a>

        <a href="{{ route('portal.logout') }}"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
           class="text-red-600 hover:text-red-700 flex items-center space-x-2">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <form id="logout-form" action="{{ route('portal.logout') }}" method="POST" class="hidden">
        @csrf
    </form>

</div>
@endsection

@push('scripts')
<script>
    function selectPackage(packageId, packageName, packagePrice) {
        @if(isset($purchaseCheck) && $purchaseCheck['has_active'])
            // Show upgrade modal with current package details
            const currentPackage = {
                name: "{{ $purchaseCheck['active_subscription']->package->name ?? 'Unknown' }}",
                price: "{{ $purchaseCheck['active_subscription']->package->price ?? '0' }}",
                data_limit: "{{ $purchaseCheck['active_subscription']->package->data_limit ?? 'Unlimited' }}",
                expires_at: "{{ isset($purchaseCheck['active_subscription']) ? $purchaseCheck['active_subscription']->expires_at->toISOString() : '' }}"
            };
            showUpgradeModal(packageId, packageName, packagePrice, currentPackage);
        @else
            if (confirm(`Select "${packageName}" package for GH₵${packagePrice.toFixed(2)}?`)) {
                proceedWithPackageSelection(packageId);
            }
        @endif
    }

    // Auto-scroll to show all packages on load
    window.addEventListener('load', function() {
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    });
</script>
@endpush

<!-- Package Upgrade Confirmation Modal -->
<div id="upgradeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl max-w-md w-full mx-auto card-shadow">
            <div class="p-6">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Package Upgrade Confirmation</h3>
                    <button onclick="closeUpgradeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Current Package Info -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <h4 class="font-medium text-red-900 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Current Package Will Be Replaced
                    </h4>
                    <div id="currentPackageInfo" class="text-sm text-red-800"></div>
                </div>

                <!-- New Package Info -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <h4 class="font-medium text-green-900 mb-2">
                        <i class="fas fa-arrow-right mr-2"></i>New Package
                    </h4>
                    <div id="newPackageInfo" class="text-sm text-green-800"></div>
                </div>

                <!-- Warning Message -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Important:</strong> Your current subscription will be immediately deactivated and replaced.
                        Any remaining time/data from your current package will be lost.
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex space-x-3">
                    <button onclick="closeUpgradeModal()"
                            class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        Cancel
                    </button>
                    <button onclick="confirmUpgrade()"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Replace Package
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let pendingUpgradePackageId = null;

    function showUpgradeModal(packageId, packageName, packagePrice, currentPackage) {
        pendingUpgradePackageId = packageId;

        // Set current package info
        const currentInfo = `
            <div class="space-y-1">
                <div><strong>Package:</strong> ${currentPackage.name}</div>
                <div><strong>Price:</strong> GH₵${parseFloat(currentPackage.price).toFixed(2)}</div>
                <div><strong>Data:</strong> ${currentPackage.data_limit || 'Unlimited'}</div>
                <div><strong>Expires:</strong> ${new Date(currentPackage.expires_at).toLocaleDateString()}</div>
            </div>
        `;

        // Set new package info
        const newInfo = `
            <div class="space-y-1">
                <div><strong>Package:</strong> ${packageName}</div>
                <div><strong>Price:</strong> GH₵${packagePrice.toFixed(2)}</div>
            </div>
        `;

        document.getElementById('currentPackageInfo').innerHTML = currentInfo;
        document.getElementById('newPackageInfo').innerHTML = newInfo;
        document.getElementById('upgradeModal').classList.remove('hidden');
    }

    function closeUpgradeModal() {
        document.getElementById('upgradeModal').classList.add('hidden');
        pendingUpgradePackageId = null;
    }

    function confirmUpgrade() {
        if (pendingUpgradePackageId) {
            document.getElementById('upgradeModal').classList.add('hidden');
            proceedWithPackageSelection(pendingUpgradePackageId);
        }
    }

    function proceedWithPackageSelection(packageId) {
        showLoading();

        fetch("{{ route('portal.select.package') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                package_id: packageId
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();

            if (data.success) {
                if (data.redirect) {
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
</script>
