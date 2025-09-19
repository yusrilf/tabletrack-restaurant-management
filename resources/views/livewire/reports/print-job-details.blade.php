 {{-- Modal Markup --}}
 @if($showModal && $selectedPrintJob)
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
     <div class="relative w-full max-w-4xl mx-4 bg-white rounded-lg shadow-xl dark:bg-gray-800" wire:click.stop>
         <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
             <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                 @lang('modules.report.printJobDetails') #{{ $selectedPrintJob->id }}
             </h3>
             <button type="button" wire:click="closeModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white">
                 <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                     <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                 </svg>
                 <span class="sr-only">@lang('app.close')</span>
             </button>
         </div>

         <div class="p-6">
             <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                 <div class="space-y-4">
                     <h4 class="text-lg font-medium text-gray-900 dark:text-white">@lang('modules.report.basicInformation')</h4>
                     <div class="space-y-3">
                         <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                             <span class="text-sm font-medium text-gray-600 dark:text-gray-400">@lang('app.id')</span>
                             <span class="text-sm text-gray-900 dark:text-white font-semibold">#{{ $selectedPrintJob->id }}</span>
                         </div>
                         <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                             <span class="text-sm font-medium text-gray-600 dark:text-gray-400">@lang('app.status')</span>
                             @php
                                 $statusColors = [
                                     'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                     'printing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                     'done' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                     'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                 ];
                                 $statusColor = $statusColors[$selectedPrintJob->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
                             @endphp
                             <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColor }}">
                                 {{ ucfirst($selectedPrintJob->status) }}
                             </span>
                         </div>
                         <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                             <span class="text-sm font-medium text-gray-600 dark:text-gray-400">@lang('app.printer')</span>
                             <span class="text-sm text-gray-900 dark:text-white">{{ $selectedPrintJob->response_printer ?? 'N/A' }}</span>
                         </div>
                         <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                             <span class="text-sm font-medium text-gray-600 dark:text-gray-400">@lang('app.createdAt')</span>
                             <span class="text-sm text-gray-900 dark:text-white">{{ $selectedPrintJob->created_at->format('M d, Y H:i:s') }}</span>
                         </div>
                         <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                             <span class="text-sm font-medium text-gray-600 dark:text-gray-400">@lang('app.printedAt')</span>
                             <span class="text-sm text-gray-900 dark:text-white">{{ $selectedPrintJob->printed_at ? $selectedPrintJob->printed_at->format('M d, Y H:i:s') : 'N/A' }}</span>
                         </div>
                         @if($selectedPrintJob->printer)
                         <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                             <span class="text-sm font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.name')</span>
                             <span class="text-sm text-gray-900 dark:text-white">{{ $selectedPrintJob->printer->name ?? 'N/A' }}</span>
                         </div>
                         @endif
                     </div>
                 </div>
                 <div class="space-y-4">
                     <h4 class="text-lg font-medium text-gray-900 dark:text-white">@lang('modules.report.printContent')</h4>
                     <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                         <div class="max-h-96 overflow-y-auto">
                             @php
                                 $payload = $selectedPrintJob->payload;
                                 if (is_string($payload)) {
                                     $payload = json_decode($payload, true) ?? $payload;
                                 }
                             @endphp
                             @if(is_array($payload) && isset($payload['text']))
                                 <div>
                                     <div class="mb-2 font-semibold text-gray-700 dark:text-gray-200">Thermal Printer Output:</div>
                                     <pre class="text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap bg-white dark:bg-gray-800 p-2 rounded">
                                        {!! $selectedPrintJob->getHtml() !!}
                                     </pre>

                                 </div>
                             @elseif($payload)
                                 <pre class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ is_array($payload) ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $payload }}</pre>
                             @else
                                 <p class="text-sm text-gray-500 dark:text-gray-400">@lang('modules.report.noContentAvailable')</p>
                             @endif
                         </div>
                     </div>
                 </div>
             </div>
             <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                 <button type="button" wire:click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                     @lang('app.close')
                 </button>
             </div>
         </div>
     </div>
 </div>
@endif
