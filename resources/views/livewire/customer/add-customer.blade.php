<x-dialog-modal wire:model.live="showAddCustomerModal">
    <x-slot name="title">
        <h2 class="text-lg">@lang('modules.customer.addCustomer')</h2>
    </x-slot>

    <x-slot name="content">
        <form wire:submit="submitForm">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-label for="customer_name" value="{{ __('modules.customer.name') }}" />
                    <div class="mb-2" wire:key="search-input">
                        <x-input id="customer_name" type="text" name="menu_name" x-on:click="open = open"
                             class="block w-full placeholder:italic" wire:model.live.debounce.300ms="customerName" autofocus
                             autocomplete="off" placeholder="{{ __('modules.customer.enterCustomerName') }}" />
                    </div>

                    <!-- Dropdown for search results -->
                    <div class="relative" @click.away="$wire.call('resetSearch')">
                        @if($availableResults && count($availableResults) > 0)
                            <div class="absolute z-50 w-full bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden transition-all duration-300 ease-in-out">
                                <div class="max-h-64 overflow-y-auto scrollbar-thin">
                                    @foreach($availableResults as $result)
                                        <div wire:key="customer-{{ $result->id }}" wire:click="selectCustomer({{ $result->id }})" class="group flex items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors duration-200">
                                            <div class="ltr:mr-4 rtl:ml-4 flex-shrink-0">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center transform group-hover:scale-105 transition-transform duration-200">
                                                    <span class="text-white font-medium text-sm">{{ substr($result->name, 0, 1) }}</span>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white mb-1">{{ $result->name }}</p>
                                                <div class="flex flex-wrap gap-3">
                                                    @if($result->email)
                                                        <span class="inline-flex items-center text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors duration-200">
                                                            <svg class="w-3.5 h-3.5 ltr:mr-1.5 rtl:ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                            {{ $result->email }}
                                                        </span>
                                                    @endif
                                                    @if($result->phone)
                                                        <span class="inline-flex items-center text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors duration-200">
                                                            <svg class="w-3.5 h-3.5 ltr:mr-1.5 rtl:ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                            {{ $result->phone }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <x-input-error for="customerName" class="mt-2" />
                </div>
                <div>
                    <x-label for="customerPhone" value="{{ __('modules.customer.phone') }}" />
                    <x-input id="customerPhone" class="block mt-1 w-full" type="tel" name="customerPhone"  wire:model='customerPhone' />
                    <x-input-error for="customerPhone" class="mt-2" />
                </div>
                <div>
                    <x-label for="customerEmail" value="{{ __('modules.customer.email') }}" />
                    <x-input id="customerEmail" class="block mt-1 w-full" type="email" name="customerEmail"  wire:model='customerEmail' />
                    <x-input-error for="customerEmail" class="mt-2" />
                </div>
                <div>
                    <x-label for="customerAddress" value="{{ __('modules.customer.address') }}" />
                    <x-textarea id="customerAddress" class="block mt-1 w-full" name="customerAddress" rows="3" data-gramm="false" wire:model='customerAddress' />
                    <x-input-error for="customerAddress" class="mt-2" />
                </div>
            </div>

            <div class="flex w-full pb-4 space-x-4 rtl:space-x-reverse mt-6">
                <x-button>@lang('app.save')</x-button>
                <x-button-cancel  wire:click="$set('showAddCustomerModal', false)">@lang('app.cancel')</x-button-cancel>
            </div>
        </form>
    </x-slot>
</x-dialog-modal>
