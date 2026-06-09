<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ShippingOption;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function stats()
    {
        return response()->json([
            'users'      => User::count(),
            'vendors'    => Vendor::count(),
            'pending_vendors' => Vendor::where('verification_status', 'PENDING')->count(),
            'orders'     => Order::count(),
            'orders_today' => Order::where('created_at', '>=', now()->startOfDay())->count(),
            'revenue'    => Order::whereIn('status', ['PROCESSING','SHIPPED','DONE'])->sum('total'),
            'pending_returns' => OrderReturn::where('status', 'PENDING')->count(),
        ]);
    }

    /* ---------- Users ---------- */
    public function users(Request $request)
    {
        $q = User::with('vendor:id,user_id,name,username,verification_status')->orderByDesc('id');
        if ($s = trim((string) $request->query('search'))) {
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', "%$s%")
                   ->orWhere('email', 'like', "%$s%")
                   ->orWhere('phone', 'like', "%$s%")
                   ->orWhereHas('vendor', fn($v) => $v->where('username', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
            });
        }
        if ($r = $request->query('role')) $q->where('role', $r);
        $per = min(50, max(5, (int) $request->query('per_page', 20)));
        return response()->json($q->paginate($per));
    }

    public function updateUser(Request $request, $id)
    {
        $u = User::findOrFail($id);
        // Role tidak bisa diubah dari API (security): hanya via DB manual / verify vendor flow
        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);
        $u->update($data);
        return response()->json($u);
    }

    public function userDetail($id)
    {
        $user = User::with('vendor')->findOrFail($id);
        $orders = \App\Models\Order::where('user_id', $user->id)
            ->with('payment:id,order_id,method_name,status', 'items:id,order_id,product_name,quantity,price,variant_selection')
            ->orderByDesc('id')->take(50)->get();
        $vendorProducts = collect();
        $vendorOrders = collect();
        if ($user->vendor) {
            $vendorProducts = \App\Models\Product::where('vendor_id', $user->vendor->id)
                ->with('tagModels:id,slug')->withCount('reviews')->orderByDesc('id')->take(50)->get();
            $vendorOrders = \App\Models\OrderItem::where('vendor_id', $user->vendor->id)
                ->with('order:id,order_number,status,created_at')
                ->orderByDesc('id')->take(50)->get();
        }
        return response()->json([
            'user'           => $user,
            'orders'         => $orders,
            'vendor_products'=> $vendorProducts,
            'vendor_orders'  => $vendorOrders,
        ]);
    }

    public function deleteUser($id)
    {
        $u = User::findOrFail($id);
        if ($u->role === 'ADMIN') return response()->json(['message' => 'Tidak bisa hapus admin'], 422);
        $u->delete();
        return response()->json(['ok' => true]);
    }

    /* ---------- Vendors ---------- */
    public function vendors(Request $request)
    {
        $q = Vendor::with('user:id,name,email,phone')->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('verification_status', $s);
        if ($k = trim((string) $request->query('search'))) {
            $q->where(function ($qq) use ($k) {
                $qq->where('name', 'like', "%$k%")
                   ->orWhere('username', 'like', "%$k%")
                   ->orWhere('city', 'like', "%$k%")
                   ->orWhereHas('user', fn($u) => $u->where('name','like',"%$k%")->orWhere('email','like',"%$k%")->orWhere('phone','like',"%$k%"));
            });
        }
        $per = min(50, max(5, (int) $request->query('per_page', 20)));
        $vendors = $q->paginate($per);
        $vendors->getCollection()->each(function ($v) {
            $v->setHidden([]);
            $v->makeVisible('ktp_image');
        });
        return response()->json($vendors);
    }

    public function verifyVendor(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:APPROVED,REJECTED',
            'note' => 'nullable|string',
        ]);
        $v = Vendor::findOrFail($id);
        $v->update(['verification_status' => $data['status'], 'verification_note' => $data['note'] ?? null]);
        return response()->json($v);
    }

    public function setBadge(Request $request, $id)
    {
        $data = $request->validate([
            'badge' => 'nullable|in:VERIFIED,MALL,STAR',
        ]);
        $v = Vendor::findOrFail($id);
        $v->update(['badge' => $data['badge'] ?? null]);
        return response()->json($v);
    }

    public function deleteVendor($id)
    {
        Vendor::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    /* ---------- Orders ---------- */
    public function orders(Request $request)
    {
        $q = Order::with(['user:id,name,email', 'address:id,city,recipient', 'payment:id,order_id,method_name,status', 'items'])
                  ->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('status', $s);
        if ($k = trim((string) $request->query('search'))) {
            $q->where(function ($qq) use ($k) {
                $qq->where('order_number', 'like', "%$k%")
                   ->orWhere('tracking_no', 'like', "%$k%")
                   ->orWhereHas('user', fn($u) => $u->where('name','like',"%$k%")->orWhere('email','like',"%$k%"));
            });
        }
        $per = min(50, max(5, (int) $request->query('per_page', 20)));
        return response()->json($q->paginate($per));
    }

    public function updateOrder(Request $request, $id)
    {
        $o = Order::findOrFail($id);
        $data = $request->validate([
            'status' => 'sometimes|in:PENDING_PAYMENT,PAID,PROCESSING,SHIPPED,DONE,CANCELLED,EXPIRED',
            'tracking_no' => 'nullable|string',
        ]);
        $o->update($data);
        return response()->json($o);
    }

    /* ---------- Returns ---------- */
    public function returns(Request $request)
    {
        return response()->json(OrderReturn::with(['order:id,order_number,total,status', 'user:id,name,email'])->orderByDesc('id')->paginate(30));
    }

    public function approveReturn(Request $request, $id)
    {
        $data = $request->validate(['status' => 'required|in:APPROVED,REJECTED,REFUNDED', 'admin_note' => 'nullable|string']);
        $r = OrderReturn::findOrFail($id);
        $r->update($data);
        return response()->json($r);
    }

    /* ---------- Shipping options ---------- */
    public function shippingOptions()
    {
        return response()->json(ShippingOption::orderBy('sort_order')->get());
    }

    public function saveShippingOptions(Request $request)
    {
        $items = $request->validate(['items' => 'required|array', 'items.*.name' => 'required|string', 'items.*.eta' => 'required|string', 'items.*.cost' => 'required|integer|min:0', 'items.*.is_active' => 'sometimes|boolean'])['items'];
        DB::transaction(function () use ($items) {
            ShippingOption::query()->delete();
            foreach ($items as $i => $it) {
                ShippingOption::create(array_merge($it, ['sort_order' => $i, 'is_active' => $it['is_active'] ?? true]));
            }
        });
        return response()->json(['ok' => true]);
    }
}
