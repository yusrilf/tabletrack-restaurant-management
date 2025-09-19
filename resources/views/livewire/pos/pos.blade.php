<div>

    <div class="flex-grow lg:flex h-auto">


        @include('pos.menu')
        @if (!$orderDetail)
            @include('pos.kot_items')
        @elseif($orderDetail->status == 'kot')
            @include('pos.order_items')
        @elseif($orderDetail->status == 'billed' || $orderDetail->status == 'paid')
            @include('pos.order_detail')
        @endif

    </div>

    <x-dialog-modal wire:model.live="showVariationModal" maxWidth="xl">
        <x-slot name="title">
            @lang('modules.menu.itemVariations')
        </x-slot>

        <x-slot name="content">
            @if ($menuItem)
            @livewire('pos.itemVariations', ['menuItem' => $menuItem], key(str()->random(50)))
            @endif
        </x-slot>

        <x-slot name="footer">
            <x-button-cancel wire:click="$toggle('showVariationModal')" wire:loading.attr="disabled" />
        </x-slot>
    </x-dialog-modal>

    <x-dialog-modal wire:model.live="showKotNote" maxWidth="xl">
        <x-slot name="title">
            @lang('modules.order.addNote')
        </x-slot>

        <x-slot name="content">
            <div>
                <x-label for="orderNote" :value="__('modules.order.orderNote')" />
                <x-textarea data-gramm="false"  class="block mt-1 w-full"  wire:model='orderNote' rows='2' />
                <x-input-error for="orderNote" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-button wire:click="$toggle('showKotNote')" wire:loading.attr="disabled">@lang('app.save')</x-button>
        </x-slot>
    </x-dialog-modal>

    <x-dialog-modal wire:model.live="showTableModal" maxWidth="2xl">
        <x-slot name="title">
            @lang('modules.table.availableTables')
        </x-slot>

        <x-slot name="content">
            @livewire('pos.setTable')
        </x-slot>

        <x-slot name="footer">
            <x-button-cancel wire:click="$toggle('showTableModal')" wire:loading.attr="disabled" />
        </x-slot>
    </x-dialog-modal>

    <x-dialog-modal wire:model.live="showDiscountModal" maxWidth="xl">
        <x-slot name="title">
            @lang('modules.order.addDiscount')
        </x-slot>

        <x-slot name="content">
            <div class="mt-4 flex">
                <!-- Discount Value -->
                <x-input id="discountValue" class="block w-2/3 text-md" type="number" step="0.01" wire:model.defer="discountValue"
                    placeholder="{{ __('modules.order.enterDiscountValue') }}" min="0" />
                <!-- Discount Type -->
                <x-select id="discountType" class="block ml-2 w-1/3 rounded-md border-gray-300" wire:model.defer="discountType">
                    <option value="fixed">@lang('modules.order.fixed')</option>
                    <option value="percent">@lang('modules.order.percent')</option>
                </x-select>
            </div>
        <x-input-error for="discountValue" class="mt-2" />
        </x-slot>

        <x-slot name="footer">
            <x-button-cancel wire:click="$set('showDiscountModal', false)">@lang('app.cancel')</x-button-cancel>
            <x-button class="ml-3" wire:click="addDiscounts" wire:loading.attr="disabled">@lang('app.save')</x-button>
        </x-slot>
    </x-dialog-modal>


    @if ($errors->count())
        <x-dialog-modal wire:model='showErrorModal' maxWidth="xl">
            <x-slot name="title">
                @lang('app.error')
            </x-slot>

            <x-slot name="content">
                <div class="space-y-3">
                    @foreach ($errors->all() as $error)
                        <div class="text-red-700 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle" viewBox="0 0 16 16">
                                <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>
                                <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
                            </svg>
                            {{ $error }}
                        </div>
                    @endforeach
                </div>

            </x-slot>

            <x-slot name="footer">
                @if ($showNewKotButton)
                    <x-button class="me-2">
                        <a href="{{ route('pos.kot', ['id' => $orderDetail->id]) }}">
                            @lang('modules.order.newKot')
                        </a>
                    </x-button>
                @endif
                <x-button-cancel wire:click="closeErrorModal" wire:loading.attr="disabled" />
            </x-slot>
        </x-dialog-modal>
    @endif

    <x-dialog-modal wire:model.live="showModifiersModal" maxWidth="xl">
        <x-slot name="title">
            @lang('modules.modifier.itemModifiers')
        </x-slot>

        <x-slot name="content">
            @if ($selectedModifierItem)
                @livewire('pos.itemModifiers', ['menuItemId' => $selectedModifierItem], key(str()->random(50)))
            @endif
        </x-slot>
    </x-dialog-modal>

    <!-- Thermal Printer Modal -->
    <x-dialog-modal wire:model.live="showThermalPrintModal" maxWidth="lg">
        <x-slot name="title">
            <div class="flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                @lang('modules.printer.thermalPrinter')
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4">
                @if (count($thermalPrinters) > 0)
                    <!-- Printer Selection -->
                    <div>
                        <x-label for="selectedThermalPrinter" :value="__('modules.printer.selectPrinter')" />
                        <x-select id="selectedThermalPrinter" class="block mt-1 w-full" wire:model.defer="selectedThermalPrinter">
                            <option value="">@lang('modules.printer.selectPrinter')</option>
                            @foreach ($thermalPrinters as $printer)
                                <option value="{{ $printer['id'] }}">{{ $printer['name'] }} ({{ $printer['ip_address'] }})</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="selectedThermalPrinter" class="mt-2" />
                    </div>

                    <!-- Print Type Selection -->
                    <div>
                        <x-label for="thermalPrintType" :value="__('modules.printer.printType')" />
                        <x-select id="thermalPrintType" class="block mt-1 w-full" wire:model.defer="thermalPrintType">
                            <option value="order">@lang('modules.order.orderReceipt')</option>
                            <option value="kot">@lang('modules.order.kotReceipt')</option>
                        </x-select>
                        <x-input-error for="thermalPrintType" class="mt-2" />
                    </div>

                    <!-- Printer Status -->
                    @if ($selectedThermalPrinter)
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    @lang('modules.printer.printerReady')
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Test Print Option -->
                    @if ($selectedThermalPrinter)
                        <div class="border-t pt-4">
                            <x-secondary-button wire:click="testThermalPrinter" wire:loading.attr="disabled" class="w-full">
                                <span wire:loading.remove wire:target="testThermalPrinter">
                                    @lang('modules.printer.testPrint')
                                </span>
                                <span wire:loading wire:target="testThermalPrinter" class="inline-flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    @lang('modules.printer.testing')
                                </span>
                            </x-secondary-button>
                        </div>
                    @endif
                @else
                    <!-- No Printers Available -->
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                            @lang('modules.printer.noPrintersAvailable')
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @lang('modules.printer.configurePrintersFirst')
                        </p>
                    </div>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-button-cancel wire:click="closeThermalPrintModal" wire:loading.attr="disabled">
                @lang('app.cancel')
            </x-button-cancel>
            
            @if (count($thermalPrinters) > 0 && $selectedThermalPrinter)
                <x-button class="ml-3" wire:click="printViaThermalPrinter" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="printViaThermalPrinter">
                        @lang('modules.printer.print')
                    </span>
                    <span wire:loading wire:target="printViaThermalPrinter" class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        @lang('modules.printer.printing')
                    </span>
                </x-button>
            @endif
        </x-slot>
    </x-dialog-modal>

    @script
    <script>
        $wire.on('play_beep', () => {
            new Audio("{{ asset('sound/sound_beep-29.mp3')}}").play();
        });

        $wire.on('print_location', (url) => {
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.target = '_blank';
            anchor.click();
        });

    </script>

    @endscript

</div>
