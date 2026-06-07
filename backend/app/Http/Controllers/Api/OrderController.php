<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\TripayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private TripayService $tripay) {}

    public function index(Request $request)
    {
        $q = Order::where('user_id', $request->user()->id)
            ->with(['items', 'payment'])
            ->orderByDesc('id');
        if ($status = $request->query('status')) $q->where('status', $status);
        return response()->json($q->paginate(20));
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
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|integer|min:1',
            'recipient'          => 'required|string|max:255',
            'phone'              => 'required|string|max:20',
            'city'               => 'required|string|max:255',
            'full_address'       => 'required|string',
            'notes'              => 'nullable|string',
            'courier_name'       => 'required|string',
            'courier_eta'        => 'required|string',
            'courier_cost'       => 'required|integer|min:0',
            'payment_method'     => 'required|string',
        ]);

        $method = $this->tripay->getMethodByCode($data['payment_method']);
        if (!$method) return response()->json(['message' => 'Metode pembayaran tidak valid'], 422);

        $userId = $request->user()->id;

        return DB::transaction(function () use ($data, $method, $userId, $request) {
            $productIds = collect($data['items'])->pluck('product_id');
            $products = Product::whereIn('id', $productIds)->where('is_active', true)->get()->keyBy('id');

            $subtotal = 0;
            $itemsForTripay = [];
            $itemsForDb = [];
            foreach ($data['items'] as $it) {
                $p = $products->get($it['product_id']);
                if (!$p) abort(422, 'Produk tidak ditemukan');
                if ($it['qty'] > $p->stock) abort(422, "Stok {$p->name} hanya {$p->stock}");
                $subtotal += $p->price * $it['qty'];
                $itemsForTripay[] = [
                    'sku'      => (string) $p->id,
                    'name'     => mb_substr($p->name, 0, 60),
                    'price'    => $p->price,
                    'quantity' => $it['qty'],
                ];
                $itemsForDb[] = [
                    'product_id'    => $p->id,
                    'vendor_id'     => $p->vendor_id,
                    'product_name'  => $p->name,
                    'product_image' => $p->image,
                    'price'         => $p->price,
                    'quantity'      => $it['qty'],
                ];
            }

            $shipping  = (int) $data['courier_cost'];
            $insurance = $subtotal > 500_000 ? (int) round($subtotal * 0.002) : 0;
            $baseTotal = $subtotal + $shipping + $insurance;
            $fee       = $this->tripay->calcFee($method, $baseTotal);
            $grand     = $baseTotal + $fee;

            // Tripay validation: sum(items.price * qty) === amount
            if ($shipping  > 0) $itemsForTripay[] = ['sku'=>'SHIPPING', 'name'=>"Ongkir ({$data['courier_name']})", 'price'=>$shipping,  'quantity'=>1];
            if ($insurance > 0) $itemsForTripay[] = ['sku'=>'INSURANCE','name'=>'Asuransi Pengiriman',                'price'=>$insurance, 'quantity'=>1];

            $address = Address::create([
                'user_id'      => $userId,
                'recipient'    => $data['recipient'],
                'phone'        => $data['phone'],
                'city'         => $data['city'],
                'full_address' => $data['full_address'],
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
                'courier_eta'  => $data['courier_eta'],
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

            return response()->json([
                'order_id'     => $order->id,
                'order_number' => $orderNumber,
            ]);
        });
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
        } elseif (in_array($status, ['EXPIRED', 'FAILED', 'REFUND'])) {
            $p->update(['status' => $status, 'raw_response' => json_encode($detail)]);
            $order->update(['status' => $status === 'EXPIRED' ? 'EXPIRED' : 'CANCELLED']);
            $changed = true;
        }
        return response()->json(['changed' => $changed, 'status' => $status]);
    }

    public function simulatePay(Request $request, $id)
    {
        if (!$this->tripay->isMockMode()) return response()->json(['message' => 'Hanya tersedia di mock mode'], 422);
        $order = Order::with('payment')->where('user_id', $request->user()->id)->findOrFail($id);
        $order->payment?->update(['status' => 'PAID', 'paid_at' => now()]);
        $order->update(['status' => 'PROCESSING', 'paid_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markDone(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        $order->update(['status' => 'DONE', 'done_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function paymentMethods()
    {
        return response()->json(\App\Services\TripayService::METHODS);
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
