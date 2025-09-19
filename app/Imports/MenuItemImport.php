<?php

namespace App\Imports;

use App\Models\MenuItem;
use App\Models\ItemCategory;
use App\Models\Menu;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Support\Facades\Log;

class MenuItemImport implements ToModel, WithHeadingRow, WithChunkReading, WithValidation, SkipsOnError, SkipsOnFailure, WithBatchInserts
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $restaurantId;
    protected $branchId;
    protected $kitchenId;
    protected $results = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'categories_created' => 0,
        'menus_created' => 0
    ];
    protected $errors = [];

    public function __construct($restaurantId, $branchId, $kitchenId = null)
    {
        $this->restaurantId = $restaurantId;
        $this->branchId = $branchId;
        $this->kitchenId = $kitchenId;
    }

    public function model(array $row)
    {
        $this->results['total']++;

        try {
            // Find category by name (using JSON query for translatable field)
            $category = ItemCategory::where('branch_id', $this->branchId)
                ->whereRaw("JSON_EXTRACT(category_name, '$.en') = ?", [$row['category_name'] ?? ''])
                ->first();


            if (!$category) {
                // Auto-create the category if it doesn't exist
                $category = ItemCategory::create([
                    'category_name' => ['en' => $row['category_name']],
                    'branch_id' => $this->branchId,
                    'is_active' => true,
                ]);
                $this->results['categories_created']++;
                Log::info("Auto-created category: {$row['category_name']} for branch {$this->branchId}");
            }

            // Find menu by name (using JSON query for translatable field)
            $menu = Menu::where('branch_id', $this->branchId)
                ->whereRaw("JSON_EXTRACT(menu_name, '$.en') = ?", [$row['menu_name'] ?? ''])
                ->first();


            if (!$menu) {
                // Auto-create the menu if it doesn't exist
                $menu = Menu::create([
                    'menu_name' => ['en' => $row['menu_name']],
                    'branch_id' => $this->branchId,
                    'is_active' => true,
                ]);
                $this->results['menus_created']++;
                Log::info("Auto-created menu: {$row['menu_name']} for branch {$this->branchId}");
            }

            // Check for duplicate menu item by name and category
            $existingMenuItem = MenuItem::where('branch_id', $this->branchId)
                ->where('item_name', $row['item_name'] ?? '')
                ->where('item_category_id', $category->id)
                ->first();

            if ($existingMenuItem) {
                Log::info("Menu item already exists: " . ($row['item_name'] ?? ''));
                $this->results['skipped']++;
                return null;
            }

            // Prepare the data
            $data = [
                'item_name' => $row['item_name'] ?? '',
                'description' => $row['description'] ?? '',
                'price' => floatval($row['price'] ?? 0),
                'item_category_id' => $category->id,
                'menu_id' => $menu->id,
                'type' => $this->mapItemType($row['type'] ?? 'veg'),
                'is_available' => 1, // Default to available (1 = yes, 0 = no)
                'show_on_customer_site' => $this->mapBoolean($row['show_on_customer_site'] ?? 'yes'),
                'branch_id' => $this->branchId,
                'kot_place_id' => $this->kitchenId,
            ];

            $this->results['success']++;
            return new MenuItem($data);
        } catch (\Exception $e) {
            Log::error("Error importing menu item: " . $e->getMessage(), ['row' => $row]);
            $this->results['failed']++;
            return null;
        }
    }

    public function rules(): array
    {
        return [
            'item_name' => 'required|string|max:255',
            'category_name' => 'required|string|max:255',
            'menu_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'type' => 'nullable|string|in:veg,non-veg,egg',
            'show_on_customer_site' => 'nullable|string|in:yes,no,1,0,true,false',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function batchSize(): int
    {
        return 100;
    }

    private function mapItemType($type)
    {
        $type = strtolower(trim($type));

        switch ($type) {
            case 'non-veg':
            case 'nonveg':
            case 'non_veg':
            case 'non veg':
                return MenuItem::NONVEG;
            case 'egg':
                return MenuItem::EGG;
            case 'veg':
            case 'vegetarian':
            default:
                return MenuItem::VEG;
        }
    }

    private function mapBoolean($value)
    {
        $value = strtolower(trim($value));

        return in_array($value, ['yes', '1', 'true', 'y']) ? 1 : 0;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
