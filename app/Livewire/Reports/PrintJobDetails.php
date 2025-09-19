<?php

namespace App\Livewire\Reports;

use App\Models\PrintJob;
use Livewire\Component;
use Livewire\Attributes\On;

class PrintJobDetails extends Component
{
    public $showModal = false;
    public $printJob = null;
    public $printJobId = null;

    #[On('showPrintJobDetails')]
    public function showDetails($id = null)
    {
        dd('showDetails called in PrintJobDetails', ['data' => $id]);
        logger()->info('showDetails called in PrintJobDetails', ['data' => $id]);
        // Accept either array or scalar ID
        $printJobId = is_array($id) ? ($id['printJobId'] ?? null) : $id;
        logger()->info('Resolved printJobId', ['printJobId' => $printJobId]);
        if (!$printJobId) {
            $this->printJob = null;
            $this->showModal = false;
            return;
        }
        $this->printJobId = $printJobId;
        $this->printJob = PrintJob::with(['restaurant', 'branch', 'printer'])
            ->where('restaurant_id', restaurant()->id)
            ->where('branch_id', branch()->id)
            ->find($this->printJobId);
        $this->showModal = (bool) $this->printJob;

        logger()->info('PrintJob loaded', ['showModal' => $this->showModal, 'printJob' => $this->printJob]);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->printJob = null;
        $this->printJobId = null;
    }

    public function render()
    {
        return view('livewire.reports.print-job-details');
    }
}
