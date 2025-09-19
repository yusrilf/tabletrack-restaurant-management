<div>
    <!-- Header Section -->
    <div class="p-4 bg-white dark:bg-gray-800">
        <div class="mb-4">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">@lang('menu.directPrintLog')</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @lang('modules.report.printLogMessage', ['startDate' => $startDate, 'endDate' => $endDate])
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Print Jobs Card -->
            <div class="p-4 bg-skin-base/10 rounded-xl shadow-sm dark:bg-skin-base/10 border border-skin-base/30 dark:border-skin-base/40">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-skin-base dark:text-skin-base">@lang('modules.report.totalPrintJobs')</h3>
                    <div class="p-2 bg-skin-base/10 rounded-lg dark:bg-skin-base/10">
                        <svg class="w-4 h-4 text-skin-base dark:text-skin-base" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl break-words font-bold text-skin-base dark:text-skin-base mb-4">
                    {{ $printJobs->total() }}
                </p>
            </div>

            <!-- Pending Jobs Card -->
            <div class="p-4 bg-yellow-50 rounded-xl shadow-sm dark:bg-yellow-900/10 border border-yellow-100 dark:border-yellow-800">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-yellow-700 dark:text-yellow-300">@lang('modules.report.pendingJobs')</h3>
                    <div class="p-2 bg-yellow-100 rounded-lg dark:bg-yellow-900/20">
                        <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300 mb-4">
                    {{ $statusCounts['pending'] ?? 0 }}
                </p>
            </div>

            <!-- Completed Jobs Card -->
            <div class="p-4 bg-green-50 rounded-xl shadow-sm dark:bg-green-900/10 border border-green-100 dark:border-green-800">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-green-700 dark:text-green-300">@lang('modules.report.completedJobs')</h3>
                    <div class="p-2 bg-green-100 rounded-lg dark:bg-green-900/20">
                        <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-green-700 dark:text-green-300 mb-4">
                    {{ $statusCounts['done'] ?? 0 }}
                </p>
            </div>

            <!-- Failed Jobs Card -->
            <div class="p-4 bg-red-50 rounded-xl shadow-sm dark:bg-red-900/10 border border-red-100 dark:border-red-800">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-red-700 dark:text-red-300">@lang('modules.report.failedJobs')</h3>
                    <div class="p-2 bg-red-100 rounded-lg dark:bg-red-900/20">
                        <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-red-700 dark:text-red-300 mb-4">
                    {{ $statusCounts['failed'] ?? 0 }}
                </p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="flex flex-wrap justify-between items-center gap-4 p-4 bg-gray-50 rounded-lg dark:bg-gray-700">
            <div class="lg:flex items-center mb-4 sm:mb-0">
                <form class="sm:pr-3" action="#" method="GET">
                    <div class="lg:flex gap-2 items-center">
                        <x-select id="dateRangeType" class="block w-full sm:w-fit mb-2 lg:mb-0" wire:model="dateRangeType" wire:change="setDateRange">
                            <option value="today">@lang('app.today')</option>
                            <option value="currentWeek">@lang('app.currentWeek')</option>
                            <option value="lastWeek">@lang('app.lastWeek')</option>
                            <option value="last7Days">@lang('app.last7Days')</option>
                            <option value="currentMonth">@lang('app.currentMonth')</option>
                            <option value="lastMonth">@lang('app.lastMonth')</option>
                            <option value="currentYear">@lang('app.currentYear')</option>
                            <option value="lastYear">@lang('app.lastYear')</option>
                        </x-select>

                        <div id="date-range-picker" date-rangepicker class="flex items-center w-full">
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20zM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2"/></svg>
                                </div>
                                <input id="datepicker-range-start" name="start" type="text" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" wire:model.change='startDate' placeholder="@lang('app.selectStartDate')">
                            </div>
                            <span class="mx-4 text-gray-500 dark:text-gray-100">@lang('app.to')</span>
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20zM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2"/></svg>
                                </div>
                                <input id="datepicker-range-end" name="end" type="text" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" wire:model.live='endDate' placeholder="@lang('app.selectEndDate')">
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Search and Filters -->
            <div class="flex flex-wrap gap-2">
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                        </svg>
                    </div>
                    <input type="text" wire:model.live="searchTerm" class="block p-2 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg w-80 bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="@lang('app.searchPrintJobs')">
                </div>

                <x-select wire:model.live="filterByStatus" class="block w-full sm:w-fit">
                    <option value="">@lang('app.allStatuses')</option>
                    <option value="pending">@lang('app.pending')</option>
                    <option value="printing">@lang('app.printing')</option>
                    <option value="done">@lang('app.done')</option>
                    <option value="failed">@lang('app.failed')</option>
                </x-select>

                <x-select wire:model.live="filterByPrinter" class="block w-full sm:w-fit">
                    <option value="">@lang('app.allPrinters')</option>
                    @foreach($printerStats as $printer)
                        <option value="{{ $printer->response_printer }}">{{ $printer->response_printer }}</option>
                    @endforeach
                </x-select>
            </div>
        </div>
    </div>

    <!-- Print Jobs Table -->
    <div class="p-4 bg-white dark:bg-gray-800">
        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-center">@lang('app.serialNumber')</th>
                        <th scope="col" class="px-6 py-3">@lang('app.printer')</th>
                        <th scope="col" class="px-6 py-3">@lang('app.responsePrinter')</th>
                        <th scope="col" class="px-6 py-3">@lang('app.status')</th>
                        <th scope="col" class="px-6 py-3 text-center">@lang('app.createdAt')</th>
                        <th scope="col" class="px-6 py-3 text-center">@lang('app.printedAt')</th>
                        <th scope="col" class="px-6 py-3">@lang('app.actions')</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $serialStart = ($printJobs->currentPage() - 1) * $printJobs->perPage();
                    @endphp
                    @forelse($printJobs as $index => $printJob)
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <td class="px-6 py-3 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                #{{ $printJobs->total() - ($serialStart + $index) }}
                            </td>

                            <td class="px-6 py-3">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $printJob->printer->name ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                {{ $printJob->response_printer ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                        'printing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                        'done' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                    ];
                                    $statusColor = $statusColors[$printJob->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
                                @endphp
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColor }}">
                                    {{ ucfirst($printJob->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-center">
                                @include('common.date-display', ['date' => $printJob->created_at])
                            </td>
                            <td class="px-6 py-3 text-center">
                                @include('common.date-display', ['date' => $printJob->printed_at])
                            </td>
                            <td class="px-6 py-3">
                                <button type="button" wire:click="showModalDetails({{ $printJob->id }})" wire:key='print-job-{{ $printJob->id }}' class="font-medium text-blue-600 dark:text-blue-500 hover:underline">
                                    @lang('app.viewDetails')
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                @lang('app.noPrintJobsFound')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div wire:key='print-job-paginate-{{ microtime() }}'
        class="p-4 bg-white dark:bg-gray-800">
        <div class="flex items-center mb-4 sm:mb-0 w-full">
            {{ $printJobs->links() }}
        </div>
    </div>

    </div>

    <!-- Printer Statistics -->
    @if($printerStats->count() > 0)
    <div class="p-4 bg-white dark:bg-gray-800 mt-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">@lang('modules.report.printerStatistics')</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($printerStats as $printer)
                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $printer->response_printer }}</span>
                        </div>
                        <span class="text-sm font-bold text-gray-600 dark:text-gray-300">{{ $printer->count }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

   @include('livewire.reports.print-job-details')

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize date range picker
        const dateRangePicker = document.getElementById('date-range-picker');
        if (dateRangePicker) {
            const startInput = document.getElementById('datepicker-range-start');
            const endInput = document.getElementById('datepicker-range-end');

            if (startInput && endInput) {
                startInput.addEventListener('change', function() {
                    @this.setStartDate(this.value);
                });

                endInput.addEventListener('change', function() {
                    @this.setEndDate(this.value);
                });
            }
        }
    });
</script>
@endpush
