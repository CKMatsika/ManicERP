<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Procurement\Models\StoresItem;
use App\Modules\Procurement\Models\StoresTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CentralStoresService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function createItem(array $data, ?User $user = null): StoresItem
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $item = StoresItem::create([
                'item_code' => $data['item_code'],
                'name' => $data['name'],
                'category' => $data['category'],
                'unit_of_measure' => $data['unit_of_measure'],
                'reorder_level' => (int) ($data['reorder_level'] ?? 10),
                'reorder_quantity' => (int) ($data['reorder_quantity'] ?? 50),
                'quantity_on_hand' => (float) ($data['quantity_on_hand'] ?? 0),
                'average_unit_cost_minor' => (int) ($data['average_unit_cost_minor'] ?? 0),
                'total_valuation_minor' => (int) round(((float) ($data['quantity_on_hand'] ?? 0)) * ((int) ($data['average_unit_cost_minor'] ?? 0))),
                'bin_location' => $data['bin_location'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            return $item;
        });
    }

    public function receiveGoods(
        StoresItem $item,
        float $quantity,
        int $unitCostMinor,
        string $supplierOrPo,
        ?string $authorizedBy = null,
        ?User $user = null
    ): StoresTransaction {
        if ($quantity <= 0) {
            throw new \DomainException('Received quantity must be positive.');
        }

        return DB::connection('tenant')->transaction(function () use ($item, $quantity, $unitCostMinor, $supplierOrPo, $authorizedBy) {
            $year = date('Y');
            $ref = 'GRV-' . $year . '-' . strtoupper(Str::random(6));
            $totalCostMinor = (int) round($quantity * $unitCostMinor);

            // Calculate new Weighted Average Cost
            $currentQty = (float) $item->quantity_on_hand;
            $currentVal = (int) $item->total_valuation_minor;

            $newQty = $currentQty + $quantity;
            $newVal = $currentVal + $totalCostMinor;
            $newWac = $newQty > 0 ? (int) round($newVal / $newQty) : $unitCostMinor;

            $tx = StoresTransaction::create([
                'transaction_number' => $ref,
                'type' => 'grv',
                'stores_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_cost_minor' => $unitCostMinor,
                'total_cost_minor' => $totalCostMinor,
                'work_order_ref' => $supplierOrPo,
                'authorized_by' => $authorizedBy ?? 'Central Stores Controller',
                'notes' => 'Goods Received Voucher via Procurement delivery.',
            ]);

            $item->update([
                'quantity_on_hand' => $newQty,
                'average_unit_cost_minor' => $newWac,
                'total_valuation_minor' => $newVal,
            ]);

            $this->audit->record(
                action: 'stores.goods_received',
                subject: $item,
                after: ['grv' => $ref, 'qty' => $quantity, 'new_stock' => $newQty],
                module: 'procurement',
            );

            return $tx;
        });
    }

    public function issueGoods(
        StoresItem $item,
        float $quantity,
        string $department,
        string $workOrder,
        ?string $issuedBy = null,
        ?string $receivedBy = null,
        ?User $user = null
    ): StoresTransaction {
        if ($quantity <= 0) {
            throw new \DomainException('Issue quantity must be positive.');
        }

        if ((float) $item->quantity_on_hand < $quantity) {
            throw new \DomainException("Insufficient stock in Central Stores. Available: {$item->quantity_on_hand} {$item->unit_of_measure}.");
        }

        return DB::connection('tenant')->transaction(function () use ($item, $quantity, $department, $workOrder, $issuedBy, $receivedBy) {
            $year = date('Y');
            $ref = 'SIN-' . $year . '-' . strtoupper(Str::random(6));
            $unitCostMinor = $item->average_unit_cost_minor;
            $totalCostMinor = (int) round($quantity * $unitCostMinor);

            $newQty = (float) $item->quantity_on_hand - $quantity;
            $newVal = (int) round($newQty * $unitCostMinor);

            $tx = StoresTransaction::create([
                'transaction_number' => $ref,
                'type' => 'issue',
                'stores_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_cost_minor' => $unitCostMinor,
                'total_cost_minor' => $totalCostMinor,
                'department_recipient' => $department,
                'work_order_ref' => $workOrder,
                'issued_by' => $issuedBy ?? 'Stores Officer',
                'received_by' => $receivedBy ?? 'Department Artisan',
                'notes' => 'Stores Issue Note for municipal operations.',
            ]);

            $item->update([
                'quantity_on_hand' => $newQty,
                'total_valuation_minor' => $newVal,
            ]);

            $this->audit->record(
                action: 'stores.goods_issued',
                subject: $item,
                after: ['sin' => $ref, 'department' => $department, 'qty' => $quantity, 'remaining' => $newQty],
                module: 'procurement',
            );

            return $tx;
        });
    }

    public function getStoresSummary(): array
    {
        $items = StoresItem::orderBy('item_code')->get();
        $totalValuation = $items->sum('total_valuation_minor');
        $lowStockItems = $items->filter(fn ($it) => $it->isLowStock());

        $recentTransactions = StoresTransaction::with('item')
            ->orderByDesc('created_at')
            ->take(15)
            ->get();

        return [
            'total_items_count' => $items->count(),
            'total_inventory_valuation_usd' => number_format($totalValuation / 100, 2),
            'low_stock_count' => $lowStockItems->count(),
            'items' => $items->map(function ($it) {
                return [
                    'id' => $it->id,
                    'item_code' => $it->item_code,
                    'name' => $it->name,
                    'category' => $it->category,
                    'unit' => $it->unit_of_measure,
                    'stock' => (float) $it->quantity_on_hand,
                    'reorder_level' => $it->reorder_level,
                    'reorder_qty' => $it->reorder_quantity,
                    'unit_cost_usd' => number_format($it->average_unit_cost_minor / 100, 2),
                    'total_valuation_usd' => number_format($it->total_valuation_minor / 100, 2),
                    'bin' => $it->bin_location,
                    'is_low_stock' => $it->isLowStock(),
                ];
            })->all(),
            'recent_transactions' => $recentTransactions->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'ref' => $tx->transaction_number,
                    'type' => $tx->type,
                    'item_name' => $tx->item?->name,
                    'item_code' => $tx->item?->item_code,
                    'quantity' => (float) $tx->quantity,
                    'total_usd' => number_format($tx->total_cost_minor / 100, 2),
                    'department' => $tx->department_recipient,
                    'work_order' => $tx->work_order_ref,
                    'date' => $tx->created_at?->toDateTimeString(),
                ];
            })->all(),
        ];
    }
}

