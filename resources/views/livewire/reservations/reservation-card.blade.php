<div class="flex flex-col bg-white border shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700 dark:shadow-gray-700/70 p-3 space-y-3 min-h-0">
    <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
        <div class="text-base font-semibold text-gray-800 dark:text-white flex items-center gap-2 flex-wrap">
            @if (!is_null($reservation->table_id))
            <div @class(['p-2 rounded-md tracking-wide', 'bg-skin-base/[0.2] text-skin-base' => (!is_null($reservation->table_id)), 'bg-yellow-200 text-yellow-800' => (is_null($reservation->table_id))])>
                <h3 wire:loading.class.delay='opacity-50'
                    @class(['font-semibold'])>
                    {{ $reservation->table->table_code }}
                </h3>
            </div>
            @endif

            @if (is_null($reservation->table_id) && user_can('Update Reservation'))
            <x-secondary-button wire:click="assignTable({{ $reservation->id }})"  wire:key='reservation-table-assign-{{ $reservation->id . microtime() }}' class="text-xs sm:text-sm">
                <svg  fill="currentcolor" class="w-4 h-4 sm:w-5 sm:h-5 transition duration-75 group-hover:text-gray-900 dark:text-gray-200  dark:group-hover:text-white mr-1" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 44.999 44.999" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M42.558,23.378l2.406-10.92c0.18-0.816-0.336-1.624-1.152-1.803c-0.816-0.182-1.623,0.335-1.802,1.151l-2.145,9.733 h-9.647c-0.835,0-1.512,0.677-1.512,1.513c0,0.836,0.677,1.513,1.512,1.513h0.573l-3.258,7.713 c-0.325,0.771,0.034,1.657,0.805,1.982c0.19,0.081,0.392,0.12,0.588,0.12c0.59,0,1.15-0.348,1.394-0.925l2.974-7.038l4.717,0.001 l2.971,7.037c0.327,0.77,1.215,1.127,1.982,0.805c0.77-0.325,1.13-1.212,0.805-1.982l-3.257-7.713h0.573 C41.791,24.564,42.403,24.072,42.558,23.378z"></path> <path d="M14.208,24.564h0.573c0.835,0,1.512-0.677,1.512-1.513c0-0.836-0.677-1.513-1.512-1.513H5.134L2.99,11.806 C2.809,10.99,2,10.472,1.188,10.655c-0.815,0.179-1.332,0.987-1.152,1.803l2.406,10.92c0.153,0.693,0.767,1.187,1.477,1.187h0.573 L1.234,32.28c-0.325,0.77,0.035,1.655,0.805,1.98c0.768,0.324,1.656-0.036,1.982-0.805l2.971-7.037l4.717-0.001l2.972,7.038 c0.244,0.577,0.804,0.925,1.394,0.925c0.196,0,0.396-0.039,0.588-0.12c0.77-0.325,1.13-1.212,0.805-1.98L14.208,24.564z"></path> <path d="M24.862,31.353h-0.852V18.308h8.13c0.835,0,1.513-0.677,1.513-1.512s-0.678-1.513-1.513-1.513H12.856 c-0.835,0-1.513,0.678-1.513,1.513c0,0.834,0.678,1.512,1.513,1.512h8.13v13.045h-0.852c-0.835,0-1.512,0.679-1.512,1.514 s0.677,1.513,1.512,1.513h4.728c0.837,0,1.514-0.678,1.514-1.513S25.699,31.353,24.862,31.353z"></path> </g> </g> </g></svg>

                @lang('modules.order.setTable')
            </x-secondary-button>
        @endif


        <span @class(['inline-flex items-center text-xs font-medium px-2 py-1 rounded uppercase tracking-wide whitespace-nowrap ',
            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400 border border-gray-400' => ($reservation->reservation_status == 'No_Show'),
            'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-400 border border-blue-400' => ($reservation->reservation_status == 'Checked_In'),
            'bg-green-100 text-green-800 dark:bg-gray-700 dark:text-green-400 border border-green-400' => ($reservation->reservation_status == 'Confirmed'),
            'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-400 border border-red-400' => ($reservation->reservation_status == 'Cancelled'),
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-400 border border-yellow-400' => ($reservation->reservation_status == 'Pending'),
            ])>
            @lang('modules.reservation.' . $reservation->reservation_status)

        </span>

        </div>
        <div class="text-gray-700 dark:text-gray-400 flex flex-col space-y-1 sm:space-y-2 text-right">
            <div class="flex gap-2 justify-end text-xs sm:text-sm font-medium">
                {{ $reservation->party_size }} @lang('modules.reservation.guests')
            </div>

            <div class="inline-flex gap-2 items-center text-xs sm:text-sm font-medium text-skin-base justify-end">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-clock sm:w-4 sm:h-4" viewBox="0 0 16 16">
                    <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/>
                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/>
                </svg>
                <span class="text-xs sm:text-sm">{{ $reservation->reservation_date_time->translatedFormat('l, d M, h:i A') }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 p-2 sm:p-3 bg-gray-100 rounded-md dark:bg-gray-700 dark:text-gray-300">
        <div class="text-xs inline-flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
            </svg>
            <span class="truncate">{{ $reservation->customer->name }}</span>
        </div>

        @if (!is_null($reservation->customer->email))
        <div class="text-xs flex items-center gap-1 justify-end">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope-at" viewBox="0 0 16 16">
                <path d="M2 2a2 2 0 0 0-2 2v8.01A2 2 0 0 0 2 14h5.5a.5.5 0 0 0 0-1H2a1 1 0 0 1-.966-.741l5.64-3.471L8 9.583l7-4.2V8.5a.5.5 0 0 0 1 0V4a2 2 0 0 0-2-2zm3.708 6.208L1 11.105V5.383zM1 4.217V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v.217l-7 4.2z"/>
                <path d="M14.247 14.269c1.01 0 1.587-.857 1.587-2.025v-.21C15.834 10.43 14.64 9 12.52 9h-.035C10.42 9 9 10.36 9 12.432v.214C9 14.82 10.438 16 12.358 16h.044c.594 0 1.018-.074 1.237-.175v-.73c-.245.11-.673.18-1.18.18h-.044c-1.334 0-2.571-.788-2.571-2.655v-.157c0-1.657 1.058-2.724 2.64-2.724h.04c1.535 0 2.484 1.05 2.484 2.326v.118c0 .975-.324 1.39-.639 1.39-.232 0-.41-.148-.41-.42v-2.19h-.906v.569h-.03c-.084-.298-.368-.63-.954-.63-.778 0-1.259.555-1.259 1.4v.528c0 .892.49 1.434 1.26 1.434.471 0 .896-.227 1.014-.643h.043c.118.42.617.648 1.12.648m-2.453-1.588v-.227c0-.546.227-.791.573-.791.297 0 .572.192.572.708v.367c0 .573-.253.744-.564.744-.354 0-.581-.215-.581-.8Z"/>
            </svg>
            <span class="truncate">{{ $reservation->customer->email }}</span>
        </div>
        @endif

        @if (!is_null($reservation->customer->phone))
        <div class="text-xs inline-flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
            </svg>
            <span class="truncate">{{ $reservation->customer->phone }}</span>
        </div>
        @endif

    </div>

    @if (!is_null($reservation->special_requests))
    <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 p-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square flex-shrink-0" viewBox="0 0 16 16">
            <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
            <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
        </svg>
        <span class="truncate">{{ $reservation->special_requests }}</span>
    </div>
    @endif

    <div class="flex justify-start gap-2">

        @if (user_can('Update Reservation'))

            <x-select wire:model.live='reservationStatus' class="w-full sm:text-sm">
                <option value="Confirmed">@lang('modules.reservation.Confirmed')</option>
                <option value="Checked_In">@lang('modules.reservation.Checked_In')</option>
                <option value="Cancelled">@lang('modules.reservation.Cancelled')</option>
                <option value="No_Show">@lang('modules.reservation.No_Show')</option>
                <option value="Pending">@lang('modules.reservation.Pending')</option>
            </x-select>

        @endif
    </div>


    @if ($tableReservation)
    <x-dialog-modal wire:model.live="showTableModal" maxWidth="2xl">
        <x-slot name="title">
            @lang('modules.table.availableTables')
        </x-slot>

        <x-slot name="content">
            @livewire('reservations.assignTable', ['reservation' => $tableReservation])
        </x-slot>

        <x-slot name="footer">
            <x-button-cancel wire:click="$toggle('showTableModal')" wire:loading.attr="disabled" />
        </x-slot>
    </x-dialog-modal>
    @endif

</div>
