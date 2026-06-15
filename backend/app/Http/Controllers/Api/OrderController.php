<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerVoucher;
use App\Services\RajaOngkirService;
use App\Services\TripayService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private TripayService $tripay, private RajaOngkirService $rajaongkir) {}

    public function index(Request $request)
    {
        $q = Order::where('user_id', $request->user()->id)
            ->with(['items', 'payment'])
            ->orderByDesc('id');
        if ($status = $request->query('status')) $q->where('status', $status);
        return response()->json($q->paginate(20));
    }

    public function activeCount(Request $request)
    {
        $count = Order::where('user_id', $request->user()->id)
            ->whereIn('status', ['PENDING_PAYMENT', 'PAID', 'PROCESSING', 'SHIPPED'])
            ->count();

        return response()->json(['count' => $count]);
    }

    public function show(Request $request, $id)
    {
        $order = Order::with(['items', 'payment', 'address', 'user:id,name,email'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
        return response()->json($order);
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.qty'               => 'required|integer|min:1',
            'items.*.variant_selection' => 'nullable|string|max:500',
            'items.*.voucher_code'      => 'nullable|string|max:30',
            'recipient'          => 'required|string|max:255',
            'phone'              => 'required|string|max:20',
            'country'            => 'nullable|string|max:80',
            'province'           => 'required|string|max:255',
            'province_id'        => 'nullable|string|max:20',
            'city'               => 'required|string|max:255',
            'city_id'            => 'nullable|string|max:20',
            'district'           => 'required|string|max:255',
            'district_id'        => 'nullable|string|max:20',
            'village'            => 'required|string|max:255',
            'village_id'         => 'nullable|string|max:20',
            'postal_code'        => 'required|string|max:10',
            'rajaongkir_destination_id' => 'nullable|integer',
            'latitude'           => 'nullable|numeric',
            'longitude'          => 'nullable|numeric',
            'full_address'       => 'required|string',
            'address_note'       => 'nullable|string|max:1000',
            'notes'              => 'nullable|string',
            'courier_name'       => 'required|string',
            'courier_code'       => 'nullable|string|max:50',
            'courier_service'    => 'nullable|string|max:100',
            'shipping_type'      => 'nullable|string|max:100',
            'courier_eta'        => 'required|string',
            'courier_cost'       => 'required|integer|min:0',
            'shipping_cashback'  => 'nullable|integer|min:0',
            'shipping_service_fee' => 'nullable|integer|min:0',
            'shipping_payload'   => 'nullable|array',
            'payment_method'     => 'required|string',
        ]);

        $method = $this->tripay->getMethodByCode($data['payment_method']);
        if (!$method) return response()->json(['message' => 'Metode pembayaran tidak valid'], 422);

        $userId = $request->user()->id;

        try {
            return DB::transaction(function () use ($data, $method, $userId, $request) {
            $productIds = collect($data['items'])->pluck('product_id');
            $products = Product::whereIn('id', $productIds)->where('is_active', true)
                ->with('vendor:id,name,moderation_mode')->get()->keyBy('id');
            // Block kalau ada produk dari toko yang sedang dimoderasi
            foreach ($products as $p) {
                if ($p->vendor && in_array($p->vendor->moderation_mode, ['LIMITED', 'DISABLED'])) {
                    abort(422, "Toko {$p->vendor->name} sedang tidak menerima pesanan.");
                }
            }

            $subtotal = 0;
            $discountTotal = 0;
            $itemsForTripay = [];
            $itemsForDb = [];
            foreach ($data['items'] as $it) {
                $p = $products->get($it['product_id']);
                if (!$p) abort(422, 'Produk tidak ditemukan');
                if ($it['qty'] > $p->stock) abort(422, "Stok {$p->name} hanya {$p->stock}");
                $lineSubtotal = $p->price * $it['qty'];
                $lineDiscount = 0;
                $voucherCode = !empty($it['voucher_code']) ? strtoupper(trim($it['voucher_code'])) : null;
                if ($voucherCode) {
                    $voucher = SellerVoucher::where('vendor_id', $p->vendor_id)->where('code', $voucherCode)->first();
                    if (!$voucher || !$voucher->isUsableFor($p, $lineSubtotal)) {
                        abort(422, "Voucher {$voucherCode} tidak valid untuk {$p->name}");
                    }
                    $lineDiscount = $voucher->discountFor($lineSubtotal);
                    $voucher->increment('used_count');
                }
                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscount;
                $itemsForDb[] = [
                    'product_id'        => $p->id,
                    'vendor_id'         => $p->vendor_id,
                    'product_name'      => $p->name,
                    'product_image'     => $p->image,
                    'price'             => $p->price,
                    'quantity'          => $it['qty'],
                    'variant_selection' => $it['variant_selection'] ?? null,
                    'voucher_code'      => $voucherCode,
                    'discount'          => $lineDiscount,
                ];
            }

            $shipping  = (int) $data['courier_cost'];
            $discountedSubtotal = max(0, $subtotal - $discountTotal);
            $insurance = $discountedSubtotal > 500_000 ? (int) round($discountedSubtotal * 0.002) : 0;
            $baseTotal = $discountedSubtotal + $shipping + $insurance;
            $fee       = $this->tripay->calcFee($method, $baseTotal);
            $grand     = $baseTotal + $fee;

            // Tripay validation: sum(items.price * qty) === amount
            $itemsForTripay[] = ['sku'=>'PRODUCTS', 'name'=>'Belanja Marketplace', 'price'=>$discountedSubtotal, 'quantity'=>1];
            if ($shipping  > 0) $itemsForTripay[] = ['sku'=>'SHIPPING', 'name'=>"Ongkir ({$data['courier_name']})", 'price'=>$shipping,  'quantity'=>1];
            if ($insurance > 0) $itemsForTripay[] = ['sku'=>'INSURANCE','name'=>'Asuransi Pengiriman',                'price'=>$insurance, 'quantity'=>1];

            $address = Address::create([
                'user_id'      => $userId,
                'recipient'    => $data['recipient'],
                'phone'        => $data['phone'],
                'country'      => $data['country'] ?? 'Indonesia',
                'province'     => $data['province'],
                'province_id'  => $data['province_id'] ?? null,
                'city'         => $data['city'],
                'city_id'      => $data['city_id'] ?? null,
                'district'     => $data['district'],
                'district_id'  => $data['district_id'] ?? null,
                'village'      => $data['village'],
                'village_id'   => $data['village_id'] ?? null,
                'postal_code'  => $data['postal_code'] ?? null,
                'rajaongkir_destination_id' => $data['rajaongkir_destination_id'] ?? null,
                'latitude'     => $data['latitude'] ?? null,
                'longitude'    => $data['longitude'] ?? null,
                'full_address' => $data['full_address'],
                'address_note' => $data['address_note'] ?? null,
            ]);

            $orderNumber = 'PRT-' . strtoupper(base_convert(time(), 10, 36)) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id'      => $userId,
                'address_id'   => $address->id,
                'subtotal'     => $subtotal,
                'shipping'     => $shipping,
                'insurance'    => $insurance,
                'payment_fee'  => $fee,
                'total'        => $grand,
                'courier_name' => $data['courier_name'],
                'courier_code' => $data['courier_code'] ?? null,
                'courier_service' => $data['courier_service'] ?? null,
                'shipping_type' => $data['shipping_type'] ?? null,
                'courier_eta'  => $data['courier_eta'],
                'shipping_cashback' => $data['shipping_cashback'] ?? 0,
                'shipping_service_fee' => $data['shipping_service_fee'] ?? 0,
                'shipping_payload' => $data['shipping_payload'] ?? null,
                'notes'        => $data['notes'] ?? null,
            ]);

            foreach ($itemsForDb as $row) {
                $order->items()->create($row);
            }
            foreach ($data['items'] as $it) {
                Product::where('id', $it['product_id'])->decrement('stock', $it['qty']);
                Product::where('id', $it['product_id'])->increment('sold', $it['qty']);
            }

                $trx = $this->tripay->createTransaction([
                    'merchant_ref'   => $orderNumber,
                    'method'         => $data['payment_method'],
                    'amount'         => $baseTotal,
                    'customer_name'  => $data['recipient'],
                    'customer_email' => $request->user()->email,
                    'customer_phone' => $data['phone'],
                    'order_items'    => $itemsForTripay,
                    'callback_url'   => url('/api/tripay/callback'),
                    'return_url'     => config('app.frontend_url', config('app.url')) . '/orders/' . $order->id,
                ]);

            Payment::create([
                'order_id'     => $order->id,
                'method'       => $trx['method'],
                'method_name'  => $trx['method_name'],
                'reference'    => $trx['reference'],
                'pay_code'     => $trx['pay_code'],
                'pay_url'      => $trx['pay_url'],
                'qr_string'    => $trx['qr_string'],
                'amount'       => $trx['amount'],
                'fee'          => $trx['fee'],
                'total'        => $trx['total'],
                'status'       => $trx['status'],
                'expired_at'   => date('Y-m-d H:i:s', $trx['expired_at']),
                'raw_response' => json_encode($trx['raw']),
            ]);

            // Kirim email konfirmasi pesanan (non-blocking; gagal ditangani service)
            $this->sendOrderEmail($order->fresh(), $request->user(), 'created');

                return response()->json([
                    'order_id'     => $order->id,
                    'order_number' => $orderNumber,
                ]);
            });
        } catch (HttpExceptionInterface $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Checkout tidak bisa dilanjutkan.',
            ], $e->getStatusCode());
        } catch (QueryException $e) {
            Log::error('Checkout database failed: ' . $e->getMessage());
            $message = $this->checkoutDbMessage($e);
            return response()->json(['message' => $message], 500);
        } catch (RuntimeException $e) {
            Log::warning('Checkout payment gateway failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Pembayaran belum bisa dibuat: ' . $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            Log::error('Checkout failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Checkout gagal diproses. Silakan coba lagi atau hubungi admin dengan menyertakan waktu kejadian.',
            ], 500);
        }
    }

    public function applyVoucher(Request $request)
    {
        $data = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'qty'          => 'required|integer|min:1',
            'voucher_code' => 'required|string|max:30',
        ]);

        $product = Product::where('is_active', true)
            ->with('vendor:id,name,moderation_mode')
            ->findOrFail($data['product_id']);

        if ($product->vendor && in_array($product->vendor->moderation_mode, ['LIMITED', 'DISABLED'])) {
            return response()->json(['message' => "Toko {$product->vendor->name} sedang tidak menerima pesanan."], 422);
        }

        if ($data['qty'] > $product->stock) {
            return response()->json(['message' => "Stok {$product->name} hanya {$product->stock}"], 422);
        }

        $lineSubtotal = $product->price * $data['qty'];
        $code = strtoupper(trim($data['voucher_code']));
        $voucher = SellerVoucher::where('vendor_id', $product->vendor_id)->where('code', $code)->first();

        if (!$voucher || !$voucher->isUsableFor($product, $lineSubtotal)) {
            return response()->json(['message' => "Voucher {$code} tidak valid untuk {$product->name}"], 422);
        }

        $discount = $voucher->discountFor($lineSubtotal);

        return response()->json([
            'code'          => $voucher->code,
            'type'          => $voucher->type,
            'value'         => $voucher->value,
            'min_subtotal'  => $voucher->min_subtotal,
            'max_discount'  => $voucher->max_discount,
            'discount'      => $discount,
            'line_subtotal' => $lineSubtotal,
            'line_total'    => max(0, $lineSubtotal - $discount),
            'label'         => $voucher->type === 'PERCENT'
                ? "Diskon {$voucher->value}%"
                : "Diskon Rp " . number_format($voucher->value, 0, ',', '.'),
        ]);
    }

    protected function sendOrderEmail($order, $user, string $event): void
    {
        if (!$user) return;
        // In-app notification
        $titleMap = [
            'created'   => 'Pesanan dibuat',
            'paid'      => 'Pembayaran berhasil',
            'shipped'   => 'Pesanan dikirim',
            'done'      => 'Pesanan selesai',
            'cancelled' => 'Pesanan dibatalkan',
        ];
        $sevMap = [
            'created' => 'INFO', 'paid' => 'SUCCESS', 'shipped' => 'INFO',
            'done' => 'SUCCESS', 'cancelled' => 'WARNING',
        ];
        $msg = "Pesanan #{$order->order_number} status diperbarui.";
        if ($order->tracking_no) $msg .= " Resi: {$order->tracking_no}.";
        try {
            \App\Models\UserNotification::send(
                $user->id, 'ORDER_' . strtoupper($event),
                $titleMap[$event] ?? 'Update pesanan',
                $msg,
                '/orders/' . $order->id,
                $sevMap[$event] ?? 'INFO',
                ['order_id' => $order->id, 'order_number' => $order->order_number]
            );
        } catch (\Throwable $e) {
            \Log::warning('Order notification failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
        if (!$user->email) return;
        try {
            $brevo = new \App\Services\BrevoService();
            $appName = \App\Models\Setting::get('app_name', 'MPSI');
            $frontUrl = rtrim(config('services.frontend_url', 'http://localhost:5173'), '/');
            $link = $frontUrl . '/orders/' . $order->id;
            $title = match ($event) {
                'created'    => 'Pesanan Diterima',
                'paid'       => 'Pembayaran Berhasil',
                'shipped'    => 'Pesanan Anda Sedang Dikirim',
                'done'       => 'Pesanan Selesai',
                'cancelled'  => 'Pesanan Dibatalkan',
                default      => 'Update Pesanan',
            };
            $intro = match ($event) {
                'created'   => "Terima kasih sudah berbelanja di {$appName}. Pesanan Anda <b>{$order->order_number}</b> telah dibuat dan menunggu pembayaran.",
                'paid'      => "Pembayaran untuk pesanan <b>{$order->order_number}</b> telah diterima. Penjual akan segera memproses.",
                'shipped'   => "Pesanan <b>{$order->order_number}</b> sudah dikirim" . ($order->tracking_no ? " dengan nomor resi <b>{$order->tracking_no}</b>" : '') . ".",
                'done'      => "Pesanan <b>{$order->order_number}</b> sudah selesai. Terima kasih atas kepercayaan Anda.",
                'cancelled' => "Pesanan <b>{$order->order_number}</b> dibatalkan.",
                default     => "Status pesanan <b>{$order->order_number}</b> diperbarui.",
            };
            $body = "<p>Hai <b>" . htmlspecialchars($user->name) . "</b>,</p>
                     <p>{$intro}</p>
                     <p>Total: <b>Rp " . number_format($order->total, 0, ',', '.') . "</b></p>";
            $brevo->send($user->email, $user->name, $title . ' #' . $order->order_number,
                $brevo->layout($title, $body, $link, 'Lihat Detail Pesanan')
            );
        } catch (\Throwable $e) {
            \Log::warning('Order email failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    private function checkoutDbMessage(QueryException $e): string
    {
        $text = $e->getMessage();
        if (str_contains($text, 'Base table or view not found') || str_contains($text, 'Unknown column')) {
            return 'Checkout gagal karena struktur database belum lengkap. Admin perlu menjalankan migrasi database terbaru.';
        }
        if (str_contains($text, 'No connection could be made') || str_contains($text, 'Connection refused')) {
            return 'Checkout gagal karena database tidak bisa dihubungi. Coba beberapa saat lagi atau hubungi admin.';
        }
        return 'Checkout gagal karena masalah database. Silakan coba lagi atau hubungi admin.';
    }

    public function refreshStatus(Request $request, $id)
    {
        $order = Order::with('payment')->where('user_id', $request->user()->id)->findOrFail($id);
        $p = $order->payment;
        if (!$p) return response()->json(['message' => 'Belum ada payment'], 404);
        if ($p->status === 'PAID') return response()->json(['changed' => false, 'status' => 'PAID', 'message' => 'Sudah dibayar']);
        if ($this->tripay->isMockMode()) return response()->json(['message' => 'Mock mode'], 422);

        $detail = $this->tripay->getTransactionDetail($p->reference);
        if (!($detail['success'] ?? false)) return response()->json(['message' => $detail['message'] ?? 'Gagal'], 502);

        $status = strtoupper($detail['data']['status'] ?? '');
        $changed = false;
        if ($status === 'PAID') {
            $p->update(['status' => 'PAID', 'paid_at' => now(), 'raw_response' => json_encode($detail)]);
            $order->update(['status' => 'PROCESSING', 'paid_at' => now()]);
            $changed = true;
            $this->sendOrderEmail($order->fresh()->load('user'), $order->user, 'paid');
        } elseif (in_array($status, ['EXPIRED', 'FAILED', 'REFUND'])) {
            $p->update(['status' => $status, 'raw_response' => json_encode($detail)]);
            $order->update(['status' => $status === 'EXPIRED' ? 'EXPIRED' : 'CANCELLED']);
            $changed = true;
            $this->sendOrderEmail($order->fresh()->load('user'), $order->user, 'cancelled');
        }
        return response()->json(['changed' => $changed, 'status' => $status]);
    }

    public function simulatePay(Request $request, $id)
    {
        if (!$this->tripay->isMockMode()) return response()->json(['message' => 'Hanya tersedia di mock mode'], 422);
        $order = Order::with('payment')->where('user_id', $request->user()->id)->findOrFail($id);
        $order->payment?->update(['status' => 'PAID', 'paid_at' => now()]);
        $order->update(['status' => 'PROCESSING', 'paid_at' => now()]);
        $this->sendOrderEmail($order->fresh(), $request->user(), 'paid');
        return response()->json(['ok' => true]);
    }

    public function markDone(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        $order->update(['status' => 'DONE', 'done_at' => now()]);
        $this->sendOrderEmail($order->fresh(), $request->user(), 'done');
        return response()->json(['ok' => true]);
    }

    public function paymentMethods()
    {
        // Kalau admin sudah set di DB, pakai itu. Kalau belum, fallback ke METHODS default.
        $db = \App\Models\PaymentMethod::where('is_active', true)->orderBy('group')->orderBy('sort_order')->get();
        if ($db->isNotEmpty()) return response()->json($db);
        return response()->json(\App\Services\TripayService::METHODS);
    }

    public function shippingRates(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'destination' => 'required|array',
            'destination.province' => 'nullable|string|max:255',
            'destination.city' => 'required|string|max:255',
            'destination.district' => 'nullable|string|max:255',
            'destination.village' => 'nullable|string|max:255',
            'destination.postal_code' => 'nullable|string|max:10',
            'destination.rajaongkir_destination_id' => 'nullable|integer',
            'destination.latitude' => 'nullable|numeric',
            'destination.longitude' => 'nullable|numeric',
        ]);

        $productIds = collect($data['items'])->pluck('product_id')->all();
        $products = Product::whereIn('id', $productIds)
            ->where('is_active', true)
            ->with('vendor:id,name,city,province,district,village,postal_code,full_address,latitude,longitude,rajaongkir_destination_id')
            ->get()
            ->keyBy('id');

        $weight = 0;
        $itemValue = 0;
        $originVendor = null;
        foreach ($data['items'] as $it) {
            $p = $products->get($it['product_id']);
            if (!$p) return response()->json(['message' => 'Produk tidak ditemukan'], 422);
            $qty = (int) $it['qty'];
            $weight += max(1, (int) $p->weight) * $qty;
            $itemValue += (int) $p->price * $qty;
            $originVendor ??= $p->vendor;
        }

        $originId = $this->rajaongkir->resolveDestinationId($originVendor);
        $destinationId = $this->rajaongkir->resolveDestinationId($data['destination']);
        if ($this->rajaongkir->isConfigured() && (!$originId || !$destinationId)) {
            return response()->json(['message' => 'Origin/destinasi RajaOngkir belum bisa dicocokkan. Lengkapi kelurahan dan kode pos toko serta alamat pembeli.'], 422);
        }

        $result = $this->rajaongkir->calculate([
            'shipper_destination_id' => $originId ?: 1,
            'receiver_destination_id' => $destinationId ?: 1,
            'weight_gram' => $weight,
            'item_value' => $itemValue,
            'origin_pin_point' => $originVendor?->latitude && $originVendor?->longitude ? "{$originVendor->latitude},{$originVendor->longitude}" : null,
            'destination_pin_point' => !empty($data['destination']['latitude']) && !empty($data['destination']['longitude'])
                ? "{$data['destination']['latitude']},{$data['destination']['longitude']}"
                : null,
            'cod' => 'no',
        ]);

        return response()->json([
            'configured' => $result['configured'],
            'origin_destination_id' => $originId,
            'receiver_destination_id' => $destinationId,
            'weight_gram' => $weight,
            'item_value' => $itemValue,
            'options' => $result['options'],
        ]);
    }

    public function shippingOptions()
    {
        $list = \App\Models\ShippingOption::where('is_active', true)->orderBy('sort_order')->get();
        if ($list->isEmpty()) {
            return response()->json([
                ['name'=>'JNE Reguler','eta'=>'2-4 hari','cost'=>12000],
                ['name'=>'J&T Express','eta'=>'2-3 hari','cost'=>14000],
                ['name'=>'SiCepat REG','eta'=>'2-4 hari','cost'=>11000],
                ['name'=>'AnterAja','eta'=>'1-3 hari','cost'=>13000],
                ['name'=>'GoSend Sameday','eta'=>'Hari Ini','cost'=>25000],
                ['name'=>'Pos Indonesia','eta'=>'3-5 hari','cost'=>9000],
            ]);
        }
        return response()->json($list);
    }

    public function requestReturn(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        if (!in_array($order->status, ['DONE', 'SHIPPED'])) {
            return response()->json(['message' => 'Hanya pesanan selesai/dikirim yang bisa di-return'], 422);
        }
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $r = \App\Models\OrderReturn::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
        ]);
        return response()->json($r);
    }
}
