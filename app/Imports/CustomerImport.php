<?php

namespace App\Imports;

use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class CustomerImport implements ToModel, WithHeadingRow, WithChunkReading, ShouldQueue
{
    protected $restaurantId;

    public function __construct($restaurantId)
    {
        $this->restaurantId = $restaurantId;
    }

    public function model(array $row)
    {
        // Check for duplicate customer by phone or email
        $existingCustomer = Customer::where('restaurant_id', $this->restaurantId)
                                    ->where(function($query) use ($row) {
                                        $query->where('phone', $row['phone'] ?? null)
                                            ->orWhere('email', $row['email'] ?? null);
                                    })
                                    ->first();

        // If customer already exists, return null to skip the import for this row
        if ($existingCustomer) {
            return null;
        }
        else{
            // Otherwise, create a new customer record
            return new Customer([
            'name'        => $row['name'] ?? null,
            'phone'       => $row['phone'] ?? null,
            'email'       => $row['email'] ?? null,
            'restaurant_id' => $this->restaurantId,
            ]);
        }



    }

    public function chunkSize(): int
    {
        return 500;
    }

}






