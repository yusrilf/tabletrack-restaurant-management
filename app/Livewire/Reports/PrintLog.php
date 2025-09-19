<?php

namespace App\Livewire\Reports;

use Carbon\Carbon;
use App\Models\PrintJob;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

class PrintLog extends Component
{
    use WithPagination;

    public $dateRangeType = 'currentWeek';
    public $startDate;
    public $endDate;
    public $startTime = '00:00';
    public $endTime = '23:59';
    public $filterByStatus = '';
    public $filterByPrinter = '';
    public $searchTerm = '';
    public $perPage = 15;
    public $showModal = false;
    public $selectedPrintJob = null;



    public function mount()
    {
        abort_unless(in_array('Report', restaurant_modules()), 403);
        abort_unless(user_can('Show Reports'), 403);

        $this->dateRangeType = request()->cookie('print_log_date_range_type', 'currentWeek');
        $this->setDateRange();
    }

    public function setDateRange()
    {
        $ranges = [
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'lastWeek' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'last7Days' => [now()->subDays(7), now()->endOfDay()],
            'currentMonth' => [now()->startOfMonth(), now()->endOfDay()],
            'lastMonth' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'currentYear' => [now()->startOfYear(), now()->endOfDay()],
            'lastYear' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            'currentWeek' => [now()->startOfWeek(), now()->endOfWeek()],
        ];

        [$start, $end] = $ranges[$this->dateRangeType] ?? $ranges['currentWeek'];
        $this->startDate = $start->format('m/d/Y');
        $this->endDate = $end->format('m/d/Y');
    }

    #[On('setStartDate')]
    public function setStartDate($start)
    {
        $this->startDate = $start;
    }

    #[On('setEndDate')]
    public function setEndDate($end)
    {
        $this->endDate = $end;
    }

    private function prepareDateTimeData()
    {
        $timezone = timezone();
        $offset = Carbon::now($timezone)->format('P');

        $startDateTime = Carbon::createFromFormat('m/d/Y H:i', $this->startDate . ' ' . $this->startTime, $timezone)
            ->setTimezone('UTC')->toDateTimeString();

        $endDateTime = Carbon::createFromFormat('m/d/Y H:i', $this->endDate . ' ' . $this->endTime, $timezone)
            ->setTimezone('UTC')->toDateTimeString();

        return compact('timezone', 'offset', 'startDateTime', 'endDateTime');
    }

    public function updatedDateRangeType($value)
    {
        cookie()->queue(cookie('print_log_date_range_type', $value, 60 * 24 * 30)); // 30 days
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }

    public function updatedFilterByStatus()
    {
        $this->resetPage();
    }

    public function updatedFilterByPrinter()
    {
        $this->resetPage();
    }

    public function showModalDetails($printJobId)
    {
        $this->selectedPrintJob = PrintJob::with(['restaurant', 'branch', 'printer'])
            ->where('restaurant_id', restaurant()->id)
            ->where('branch_id', branch()->id)
            ->find($printJobId);
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedPrintJob = null;
    }

    public function getPrintJobsProperty()
    {
        $dateTimeData = $this->prepareDateTimeData();

        $query = PrintJob::with(['restaurant', 'branch', 'printer'])
            ->where('restaurant_id', restaurant()->id)
            ->where('branch_id', branch()->id)
            ->whereBetween('created_at', [$dateTimeData['startDateTime'], $dateTimeData['endDateTime']]);

        // Filter by status
        if ($this->filterByStatus) {
            $query->where('status', $this->filterByStatus);
        }

        // Filter by printer
        if ($this->filterByPrinter) {
            $query->where('response_printer', 'like', '%' . $this->filterByPrinter . '%');
        }

        // Search term
        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('response_printer', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('status', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('id', 'like', '%' . $this->searchTerm . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
    }

    public function getStatusCountsProperty()
    {
        $dateTimeData = $this->prepareDateTimeData();

        return PrintJob::where('restaurant_id', restaurant()->id)
            ->where('branch_id', branch()->id)
            ->whereBetween('created_at', [$dateTimeData['startDateTime'], $dateTimeData['endDateTime']])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function getPrinterStatsProperty()
    {
        $dateTimeData = $this->prepareDateTimeData();

        return PrintJob::where('restaurant_id', restaurant()->id)
            ->where('branch_id', branch()->id)
            ->whereBetween('created_at', [$dateTimeData['startDateTime'], $dateTimeData['endDateTime']])
            ->whereNotNull('response_printer')
            ->select('response_printer', DB::raw('count(*) as count'))
            ->groupBy('response_printer')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.reports.print-log', [
            'printJobs' => $this->printJobs,
            'statusCounts' => $this->statusCounts,
            'printerStats' => $this->printerStats,
        ]);
    }
}
