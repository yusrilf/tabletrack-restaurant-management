<div>
    <form wire:submit="submitForm">
        @csrf
        <div class="space-y-4">

            <div>
                <x-label for="memberName" value="{{ __('modules.staff.name') }}" />
                <x-input id="memberName" class="block mt-1 w-full" type="text" autofocus wire:model='memberName' />
                <x-input-error for="memberName" class="mt-2" />
            </div>

            <div>
                <x-label for="memberEmail" value="{{ __('modules.staff.email') }}" />
                <x-input id="memberEmail" class="block mt-1 w-full" type="email" autofocus wire:model='memberEmail' />
                <x-input-error for="memberEmail" class="mt-2" />
            </div>
            <div>
                <x-label class="mt-4" for="restaurantPhoneNumber"
                    value="{{ __('modules.settings.restaurantPhoneNumber') }}" />
                <div class="flex gap-2 mt-2">
                    <!-- Phone Code Dropdown -->
                    <div x-data="{ isOpen: @entangle('phoneCodeIsOpen').live }" @click.away="isOpen = false" class="relative w-32">
                        <div @click="isOpen = !isOpen"
                            class="p-2 bg-gray-100 border rounded cursor-pointer dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-600 dark:focus:ring-gray-600">
                            <div class="flex items-center justify-between">
                                <span class="text-sm">
                                    @if($restaurantPhoneCode)
                                        +{{ $restaurantPhoneCode }}
                                    @else
                                        {{ __('modules.settings.select') }}
                                    @endif
                                </span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Search Input and Options -->
                        <ul x-show="isOpen" x-transition class="absolute z-10 w-full mt-1 overflow-auto bg-white rounded-lg shadow-lg max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-600 dark:focus:ring-gray-600">
                            <li class="sticky top-0 px-3 py-2 bg-white dark:bg-gray-900 z-10">
                                <x-input wire:model.live.debounce.300ms="phoneCodeSearch" class="block w-full" type="text" placeholder="{{ __('placeholders.search') }}" />
                            </li>
                            @forelse ($phonecodes as $phonecode)
                                <li @click="$wire.selectPhoneCode('{{ $phonecode }}')"
                                    wire:key="phone-code-{{ $phonecode }}"
                                    class="relative py-2 pl-3 text-gray-900 transition-colors duration-150 cursor-pointer select-none pr-9 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800 dark:text-gray-300 dark:focus:border-gray-600 dark:focus:ring-gray-600"
                                    :class="{ 'bg-gray-100 dark:bg-gray-800': '{{ $phonecode }}' === '{{ $restaurantPhoneCode }}' }" role="option">
                                    <div class="flex items-center">
                                        <span class="block ml-3 text-sm whitespace-nowrap">+{{ $phonecode }}</span>
                                        <span x-show="'{{ $phonecode }}' === '{{ $restaurantPhoneCode }}'" class="absolute inset-y-0 right-0 flex items-center pr-4 text-black dark:text-gray-300" x-cloak>
                                            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </div>
                                </li>
                            @empty
                                <li class="relative py-2 pl-3 text-gray-500 cursor-default select-none pr-9 dark:text-gray-400">
                                    {{ __('modules.settings.noPhoneCodesFound') }}
                                </li>
                            @endforelse
                        </ul>
                    </div>

                    <!-- Phone Number Input -->
                    <x-input id="restaurantPhoneNumber" class="block w-full" type="tel"
                        wire:model='restaurantPhoneNumber' placeholder="1234567890" />
                </div>

                <x-input-error for="restaurantPhoneCode" class="mt-2" />
                <x-input-error for="restaurantPhoneNumber" class="mt-2" />
           </div>
        <div>
            <x-label for="memberPassword" value="{{ __('modules.staff.password') }}" />
            <x-input id="memberPassword" class="block mt-1 w-full" type="password" autofocus
                wire:model='memberPassword' />
            <x-input-error for="memberPassword" class="mt-2" />
        </div>

        <div>
            <x-label for="memberRole" value="{{ __('app.role') }}" />

            <x-select class="mt-1 block w-full" wire:model='memberRole'>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}">{{ $role->display_name }}</option>
                @endforeach
            </x-select>

            <x-input-error for="memberRole" class="mt-2" />
        </div>

</div>

<div class="flex w-full pb-4 space-x-4 mt-6 rtl:space-x-reverse">
    <x-button>@lang('app.save')</x-button>
    <x-button-cancel wire:click="$dispatch('hideAddStaff')"
        wire:loading.attr="disabled">@lang('app.cancel')</x-button-cancel>
</div>
</form>
</div>
