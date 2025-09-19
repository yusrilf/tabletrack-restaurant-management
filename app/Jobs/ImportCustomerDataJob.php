<?php

namespace App\Jobs;

use App\Imports\CustomerImport;
use App\Imports\CustomersImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportCustomerDataJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $filePath;
    protected $restaurantId;

    public function __construct($filePath, $restaurantId)
    {
        $this->filePath = $filePath;
        $this->restaurantId = $restaurantId;
    }

    public function handle()
    {
        if (!Storage::exists($this->filePath)) {
            return;
        }

        Excel::import(new CustomerImport($this->restaurantId), Storage::path($this->filePath));

        Storage::delete($this->filePath);
    }

}
