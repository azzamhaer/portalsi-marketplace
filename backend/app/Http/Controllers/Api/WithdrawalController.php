<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    /**
     * Hitung saldo yang bisa ditarik oleh vendor.
     * = total dari OrderItem pada Order berstatus DONE
     *   dikurangi platform commission %
     *   dikurangi withdrawal yang sudah dibuat (PENDING/APPROVED/PAID)
     */
    public function balance(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        if ($vendor->verification_status !== 'APPROVED') {
            return response()->json(['message' => 'Toko belum terverifikasi'], 403);
        }

        $commissionPct = (float) (Setting::get('commission_percent', 5));

        $grossEarning = (int) OrderItem::where('vendor_id', $vendor->id)
            ->whereHas('order', fn($q) => $q->where('status', 'DONE'))
            ->sum(DB::raw('price * quantity'));

        $commission  = (int) round($grossEarning * $commissionPct / 100);
        $netEarning  = $grossEarning - $commission;

        $withdrawn = (int) Withdrawal::where('vendor_id', $vendor->id)
            ->whereIn('status', ['PENDING','APPROVED','PAID'])
            ->sum('amount');

        $available = max(0, $netEarning - $withdrawn);

        return response()->json([
            'commission_percent' => $commissionPct,
            'gross_earning'      => $grossEarning,
            'commission'         => $commission,
            'net_earning'        => $netEarning,
            'withdrawn'          => $withdrawn,
            'available'          => $available,
            'bank' => [
                'name'    => $vendor->bank_name,
                'account' => $vendor->bank_account,
                'holder'  => $vendor->bank_holder,
            ],
            'history' => Withdrawal::where('vendor_id', $vendor->id)->orderByDesc('id')->take(50)->get(),
        ]);
    }

    public function request(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        if ($vendor->verification_status !== 'APPROVED') return response()->json(['message' => 'Toko belum terverifikasi'], 403);
        if (!$vendor->bank_name || !$vendor->bank_account || !$vendor->bank_holder)
            return response()->json(['message' => 'Lengkapi data bank di Profil Toko dulu'], 422);

        $data = $request->validate(['amount' => 'required|integer|min:10000']);

        $commissionPct = (float) (Setting::get('commission_percent', 5));
        $grossEarning = (int) OrderItem::where('vendor_id', $vendor->id)
            ->whereHas('order', fn($q) => $q->where('status', 'DONE'))
            ->sum(DB::raw('price * quantity'));
        $netEarning = $grossEarning - (int) round($grossEarning * $commissionPct / 100);

        $withdrawn = (int) Withdrawal::where('vendor_id', $vendor->id)
            ->whereIn('status', ['PENDING','APPROVED','PAID'])->sum('amount');
        $available = max(0, $netEarning - $withdrawn);

        if ($data['amount'] > $available)
            return response()->json(['message' => "Saldo tidak cukup. Tersedia: Rp " . number_format($available, 0, ',', '.')], 422);

        $w = Withdrawal::create([
            'vendor_id'    => $vendor->id,
            'amount'       => $data['amount'],
            'bank_name'    => $vendor->bank_name,
            'bank_account' => $vendor->bank_account,
            'bank_holder'  => $vendor->bank_holder,
            'status'       => 'PENDING',
        ]);

        return response()->json($w, 201);
    }

    public function cancel(Request $request, $id)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        if ($vendor->verification_status !== 'APPROVED') return response()->json(['message' => 'Toko belum terverifikasi'], 403);
        $w = Withdrawal::where('vendor_id', $vendor?->id)->findOrFail($id);
        if ($w->status !== 'PENDING') return response()->json(['message' => 'Hanya request PENDING yang bisa dibatalkan'], 422);
        $w->delete();
        return response()->json(['ok' => true]);
    }

    /* ========== ADMIN ========== */

    public function adminList(Request $request)
    {
        $q = Withdrawal::with('vendor:id,name,user_id,bank_name,bank_account,bank_holder')
                       ->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('status', $s);
        return response()->json($q->paginate(30));
    }

    public function adminProcess(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:APPROVED,REJECTED,PAID',
            'admin_note' => 'nullable|string',
        ]);
        $w = Withdrawal::with('vendor.user')->findOrFail($id);
        $w->update([
            'status'       => $data['status'],
            'admin_note'   => $data['admin_note'] ?? null,
            'processed_at' => now(),
        ]);
        if ($w->vendor?->user_id) {
            $sev = match($data['status']) { 'PAID' => 'SUCCESS', 'REJECTED' => 'DANGER', default => 'INFO' };
            \App\Models\UserNotification::send(
                $w->vendor->user_id, 'WITHDRAW_' . $data['status'],
                "Penarikan Rp " . number_format($w->amount, 0, ',', '.') . " — {$data['status']}",
                "Permintaan penarikan Anda sebesar Rp " . number_format($w->amount, 0, ',', '.') . " telah diubah ke status {$data['status']}." . ($data['admin_note'] ? "\nCatatan: " . $data['admin_note'] : ''),
                '/seller/withdraw', $sev,
                ['withdrawal_id' => $w->id]
            );
        }
        return response()->json($w);
    }
}
