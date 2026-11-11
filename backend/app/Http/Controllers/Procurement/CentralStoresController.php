<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\StoresItem;
use App\Modules\Procurement\Services\CentralStoresService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CentralStoresController extends Controller
{
    public function __construct(
        private readonly CentralStoresService $stores,
    ) {}

    public function index(): Response
    {
        $summary = $this->stores->getStoresSummary();

        return Inertia::render('Stores/CentralStores', [
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:48'],
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'unit_of_measure' => ['required', 'string', 'max:32'],
            'reorder_level' => ['required', 'integer', 'min:1'],
            'reorder_quantity' => ['required', 'integer', 'min:1'],
            'quantity_on_hand' => ['nullable', 'numeric', 'min:0'],
            'average_unit_cost_minor' => ['required', 'integer', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $this->stores->createItem($data, $request->user());

        return redirect()->back()->with('success', 'Central Stores stock item added successfully.');
    }

    public function receive(Request $request)
    {
        $data = $request->validate([
            'stores_item_id' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost_minor' => ['required', 'integer', 'min:1'],
            'work_order_ref' => ['required', 'string', 'max:80'],
            'authorized_by' => ['nullable', 'string', 'max:120'],
        ]);

        $item = StoresItem::findOrFail($data['stores_item_id']);
        $this->stores->receiveGoods(
            $item,
            (float) $data['quantity'],
            (int) $data['unit_cost_minor'],
            $data['work_order_ref'],
            $data['authorized_by'] ?? null,
            $request->user()
        );

        return redirect()->back()->with('success', "Goods Received Voucher recorded for {$item->name}. Inventory updated.");
    }

    public function issue(Request $request)
    {
        $data = $request->validate([
            'stores_item_id' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'department_recipient' => ['required', 'string', 'max:80'],
            'work_order_ref' => ['required', 'string', 'max:80'],
            'issued_by' => ['nullable', 'string', 'max:120'],
            'received_by' => ['nullable', 'string', 'max:120'],
        ]);

        $item = StoresItem::findOrFail($data['stores_item_id']);

        try {
            $this->stores->issueGoods(
                $item,
                (float) $data['quantity'],
                $data['department_recipient'],
                $data['work_order_ref'],
                $data['issued_by'] ?? null,
                $data['received_by'] ?? null,
                $request->user()
            );
        } catch (\DomainException $e) {
            return redirect()->back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', "Stores Issue Note created for {$item->name}. Department charged.");
    }
}

