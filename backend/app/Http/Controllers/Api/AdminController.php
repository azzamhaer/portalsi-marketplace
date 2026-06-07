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
        $q = User::with('vendor:id,user_id,name,verification_status')->orderByDesc('id');
        if ($s = $request->query('search')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%");
            });
        }
        if ($r = $request->query('role')) $q->where('role', $r);
        return response()->json($q->paginate(20));
    }

    public function updateUser(Request $request, $id)
    {
        $u = User::findOrFail($id);
        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'role'  => 'sometimes|in:BUYER,SELLER,ADMIN',
        ]);
        $u->update($data);
        return response()->json($u);
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
        $q = Vendor::with('user:id,name,email')->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('verification_status', $s);
        if ($k = $request->query('search')) $q->where('name', 'like', "%$k%");
        $vendors = $q->paginate(20);
        // include ktp_image manually for admin view
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
        return response()->json($q->paginate(20));
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
