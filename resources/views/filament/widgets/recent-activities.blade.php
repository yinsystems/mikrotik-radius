<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recent Activities
        </x-slot>

        <x-slot name="description">
            Latest customer activities and system events
        </x-slot>

        <div class="space-y-3">
            @foreach($this->getActivities() as $activity)
                <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border-l-4 border-l-{{ $activity['color'] }}-500">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-{{ $activity['color'] }}-100 dark:bg-{{ $activity['color'] }}-900 rounded-full flex items-center justify-center">
                            <x-heroicon-o-user-plus class="w-4 h-4 text-{{ $activity['color'] }}-600 dark:text-{{ $activity['color'] }}-400" 
                                @if($activity['icon'] === 'heroicon-o-user-plus') style="display: inline" @else style="display: none" @endif />
                            <x-heroicon-o-rectangle-stack class="w-4 h-4 text-{{ $activity['color'] }}-600 dark:text-{{ $activity['color'] }}-400" 
                                @if($activity['icon'] === 'heroicon-o-rectangle-stack') style="display: inline" @else style="display: none" @endif />
                            <x-heroicon-o-banknotes class="w-4 h-4 text-{{ $activity['color'] }}-600 dark:text-{{ $activity['color'] }}-400" 
                                @if($activity['icon'] === 'heroicon-o-banknotes') style="display: inline" @else style="display: none" @endif />
                            <x-heroicon-o-wifi class="w-4 h-4 text-{{ $activity['color'] }}-600 dark:text-{{ $activity['color'] }}-400" 
                                @if($activity['icon'] === 'heroicon-o-wifi') style="display: inline" @else style="display: none" @endif />
                        </div>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $activity['title'] }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $activity['description'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-500">
                                    Customer: {{ $activity['customer'] }}
                                </p>
                            </div>
                            
                            <div class="text-right">
                                @if($activity['amount'])
                                    <p class="text-sm font-medium text-green-600 dark:text-green-400">
                                        ₵{{ number_format($activity['amount'], 2) }}
                                    </p>
                                @endif
                                <p class="text-xs text-gray-500 dark:text-gray-500">
                                    {{ $activity['time']->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if(empty($this->getActivities()))
                <div class="text-center py-8">
                    <x-heroicon-o-inbox class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <p class="text-gray-500 dark:text-gray-400">No recent activities found</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>