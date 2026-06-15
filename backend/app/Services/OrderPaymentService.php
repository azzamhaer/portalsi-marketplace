<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderPaymentService
{
    public function markPaid(Order $order, ?array $rawResponse = null): bool
    {
        return DB::transaction(function () use ($order, $rawResponse) {
            $lockedOrder = Order::with(['items', 'payment'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shouldAdjustStock = $lockedOrder->status === 'PENDING_PAYMENT';
            $paidAt = $lockedOrder->paid_at ?: now();

            if ($shouldAdjustStock) {
                foreach ($lockedOrder->items as $item) {
                    $qty = max(0, (int) $item->quantity);
                    if ($qty < 1) continue;

                    $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                    if (!$product) continue;

                    $product->stock = max(0, (int) $product->stock - $qty);
                    $product->sold = (int) $product->sold + $qty;
                    $product->save();
                }
            }

            if ($lockedOrder->payment) {
                $paymentPayload = [
                    'status' => 'PAID',
                    'paid_at' => $paidAt,
                ];
                if ($rawResponse !== null) {
                    $paymentPayload['raw_response'] = json_encode($rawResponse);
                }
                $lockedOrder->payment->update($paymentPayload);
            }

            $lockedOrder->update([
                'status' => 'PROCESSING',
                'paid_at' => $paidAt,
            ]);

            return $shouldAdjustStock;
        });
    }
}
