<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerVoucher;
use App\Models\Tag;
use App\Models\Vendor;
use App\Support\ReservedUsernames;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    public function register(Request $request)
    {
        $user = $request->user();
        if ($user->vendor()->exists()) return response()->json(['message' => 'Anda sudah punya toko'], 422);

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'country'      => 'nullable|string|max:80',
            'province'     => 'required|string|max:255',
            'city'         => 'required|string|max:255',
            'district'     => 'required|string|max:255',
            'village'      => 'required|string|max:255',
            'postal_code'  => 'required|string|max:10',
            'description'  => 'required|string',
            'bank_name'    => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_holder'  => 'nullable|string|max:255',
            'ktp_image'    => 'required|string', // base64 data URI
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'full_address' => 'required|string',
            'address_note' => 'nullable|string|max:1000',
        ]);

        $color = '#' . substr(md5($data['name']), 0, 6);

        // Generate unique username dari nama toko
        $username = $this->generateUsername($data['name']);

        $vendor = Vendor::create([
            'user_id'     => $user->id,
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
            'username'    => $username,
            'country'     => $data['country'] ?? 'Indonesia',
            'province'    => $data['province'],
            'city'        => $data['city'],
            'district'    => $data['district'],
            'village'     => $data['village'],
            'postal_code' => $data['postal_code'] ?? null,
            'latitude'    => $data['latitude'] ?? null,
            'longitude'   => $data['longitude'] ?? null,
            'full_address'=> $data['full_address'] ?? null,
            'address_note'=> $data['address_note'] ?? null,
            'description' => $data['description'],
            'avatar'      => $this->makeAvatar($data['name'][0] ?? 'S', $color),
            'banner'      => $this->makeBanner($data['name'], $color),
            'ktp_image'   => $data['ktp_image'],
            'verification_status' => 'PENDING',
            'bank_name'   => $data['bank_name'] ?? null,
            'bank_account'=> $data['bank_account'] ?? null,
            'bank_holder' => $data['bank_holder'] ?? null,
        ]);
        $user->update(['role' => 'SELLER']);

        // Notifikasi ke SEMUA admin agar review pendaftaran ini
        $admins = \App\Models\User::where('role', 'ADMIN')->pluck('id');
        foreach ($admins as $adminId) {
            \App\Models\UserNotification::send(
                $adminId,
                'VENDOR_PENDING_APPROVAL',
                'Pendaftaran vendor baru',
                "Toko \"{$vendor->name}\" (oleh {$user->name}) menunggu approval verifikasi KTP.",
                '/admin/vendors?status=PENDING',
                'INFO',
                ['vendor_id' => $vendor->id, 'user_id' => $user->id]
            );
        }

        return response()->json($vendor, 201);
    }

    public function dashboard(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);

        if ($vendor->verification_status !== 'APPROVED') {
            return response()->json([
                'vendor' => $vendor,
                'stats' => [
                    'orders_24h' => 0,
                    'revenue_30d' => 0,
                    'active_products' => 0,
                    'rating' => $vendor->rating,
                ],
                'recent_orders' => [],
            ]);
        }

        return response()->json([
            'vendor' => $vendor,
            'stats' => [
                'orders_24h'  => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))->where('created_at', '>=', now()->subDay())->count(),
                'revenue_30d' => OrderItem::where('vendor_id', $vendor->id)
                                  ->whereHas('order', fn($q) => $q->whereIn('status', ['PROCESSING','SHIPPED','DONE'])->where('created_at', '>=', now()->subDays(30)))
                                  ->sum(\DB::raw('price * quantity')),
                'active_products' => Product::where('vendor_id', $vendor->id)->where('is_active', true)->count(),
                'rating'          => $vendor->rating,
            ],
            'recent_orders' => OrderItem::where('vendor_id', $vendor->id)->with('order:id,order_number,status,created_at')->orderByDesc('id')->take(10)->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'country'      => 'nullable|string|max:80',
            'province'     => 'sometimes|string|max:255',
            'city'         => 'sometimes|string|max:255',
            'district'     => 'sometimes|string|max:255',
            'village'      => 'sometimes|string|max:255',
            'postal_code'  => 'nullable|string|max:10',
            'description'  => 'sometimes|string',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'full_address' => 'nullable|string',
            'address_note' => 'nullable|string|max:1000',
            'avatar'       => 'nullable|string',
            'banner'       => 'nullable|string',
            'bank_name'    => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_holder'  => 'nullable|string|max:255',
        ]);

        foreach (['avatar' => 2, 'banner' => 3] as $field => $maxMb) {
            if (array_key_exists($field, $data) && $data[$field]) {
                $this->validateInlineImage($data[$field], $field === 'avatar' ? 'Foto profil' : 'Banner', $maxMb);
            }
        }

        $vendor->update($data);
        return response()->json($vendor);
    }

    public function products(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        return response()->json(Product::where('vendor_id', $vendor->id)->with(['tagModels:id,slug'])->orderByDesc('id')->get());
    }

    public function storeProduct(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'required|string',
            'price'          => 'required|integer|min:1',
            'original_price' => 'nullable|integer|min:0',
            'stock'          => 'required|integer|min:0',
            'image'          => 'nullable|string',
            'images'         => 'nullable|array|max:8',
            'images.*'       => 'string',
            'variants'       => 'nullable|array',
            'variants.*'     => 'array',
            'variants.*.*'   => 'string|max:50',
            'is_active'      => 'sometimes|boolean',
            'tags'           => 'required|array|min:1',
            'tags.*'         => 'string|max:50',
        ]);

        $images = $data['images'] ?? [];
        $coverImage = $data['image'] ?: ($images[0] ?? $this->makeProductImage($data['name'], '#0a0a0a'));

        $product = Product::create([
            'vendor_id'  => $vendor->id,
            'category_id'=> 'elektronik',
            'name'       => $data['name'],
            'slug'       => $this->makeProductSlug($data['name']),
            'description'=> $data['description'],
            'price'      => $data['price'],
            'original_price' => $data['original_price'] ?? null,
            'stock'      => $data['stock'],
            'image'      => $coverImage,
            'images'     => $images,
            'variants'   => $data['variants'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
        ]);

        $this->syncTags($product, $data['tags']);
        return response()->json($product->load('tagModels'), 201);
    }

    public function updateProduct(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $product = Product::where('vendor_id', $vendor?->id)->findOrFail($id);
        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'description'    => 'sometimes|string',
            'price'          => 'sometimes|integer|min:1',
            'original_price' => 'nullable|integer|min:0',
            'stock'          => 'sometimes|integer|min:0',
            'image'          => 'nullable|string',
            'images'         => 'nullable|array|max:8',
            'images.*'       => 'string',
            'variants'       => 'nullable|array',
            'variants.*'     => 'array',
            'variants.*.*'   => 'string|max:50',
            'is_active'      => 'sometimes|boolean',
            'tags'           => 'sometimes|array',
            'tags.*'         => 'string|max:50',
        ]);
        $tags = $data['tags'] ?? null;
        unset($data['tags']);
        $product->update($data);
        if ($tags !== null) $this->syncTags($product, $tags);
        return response()->json($product->load('tagModels'));
    }

    public function deleteProduct(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $product = Product::where('vendor_id', $vendor?->id)->findOrFail($id);
        if (OrderItem::where('product_id', $product->id)->exists()) {
            $product->update(['is_active' => false, 'stock' => 0]);
            return response()->json(['ok' => true, 'soft' => true]);
        }
        $product->delete();
        return response()->json(['ok' => true]);
    }

    public function orders(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        return response()->json(
            OrderItem::where('vendor_id', $vendor->id)
                ->with(['order.user:id,name,email', 'order.address:id,recipient,city,phone', 'order.payment:id,order_id,method_name,status'])
                ->orderByDesc('id')->paginate(30)
        );
    }

    public function vouchers(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        return response()->json(
            SellerVoucher::where('vendor_id', $vendor->id)
                ->with('products:id,name,image,price')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function storeVoucher(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        $data = $this->validateVoucher($request);
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);
        $data['vendor_id'] = $vendor->id;
        $data['code'] = strtoupper(trim($data['code']));

        if (SellerVoucher::where('vendor_id', $vendor->id)->where('code', $data['code'])->exists()) {
            return response()->json(['message' => 'Kode voucher sudah dipakai'], 422);
        }

        $voucher = SellerVoucher::create($data);
        $this->syncVoucherProducts($voucher, $vendor->id, $productIds);
        return response()->json($voucher->load('products:id,name,image,price'), 201);
    }

    public function updateVoucher(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $voucher = SellerVoucher::where('vendor_id', $vendor->id)->findOrFail($id);
        $data = $this->validateVoucher($request, true);
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids']);
        if (isset($data['code'])) $data['code'] = strtoupper(trim($data['code']));
        if (isset($data['code']) && SellerVoucher::where('vendor_id', $vendor->id)->where('code', $data['code'])->where('id', '!=', $voucher->id)->exists()) {
            return response()->json(['message' => 'Kode voucher sudah dipakai'], 422);
        }
        $voucher->update($data);
        if ($productIds !== null) $this->syncVoucherProducts($voucher, $vendor->id, $productIds);
        return response()->json($voucher->load('products:id,name,image,price'));
    }

    public function deleteVoucher(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        SellerVoucher::where('vendor_id', $vendor->id)->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function shipOrder(Request $request, $id)
    {
        $vendor = $this->requireApprovedVendor($request);
        $order = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor?->id))->with('user')->findOrFail($id);
        $tracking = $this->makeTrackingNumber($order->courier_name ?? 'JNE');
        $order->update(['status' => 'SHIPPED', 'shipped_at' => now(), 'tracking_no' => $tracking]);

        // Email notif ke pembeli
        if ($order->user) {
            $brevo = new \App\Services\BrevoService();
            $front = rtrim(config('services.frontend_url', 'http://localhost:5173'), '/');
            $body = "<p>Hai <b>" . htmlspecialchars($order->user->name) . "</b>,</p>
                     <p>Pesanan <b>{$order->order_number}</b> sudah dikirim dengan nomor resi <b>{$tracking}</b>.</p>";
            $brevo->send($order->user->email, $order->user->name, "Pesanan Dikirim #{$order->order_number}",
                $brevo->layout('Pesanan Dikirim', $body, $front . '/orders/' . $order->id, 'Cek Detail Pesanan')
            );
        }

        return response()->json(['ok' => true, 'tracking_no' => $tracking]);
    }

    public function dismissWarning(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        $vendor->update(['warning_dismissed_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function finishTour(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);
        if (!$vendor->tour_completed_at) {
            $vendor->update(['tour_completed_at' => now()]);
        }
        return response()->json(['ok' => true]);
    }

    public function updateUsername(Request $request)
    {
        $vendor = $this->requireApprovedVendor($request);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
        ], [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, garis bawah, dan tanda hubung. Harus mulai dengan huruf/angka.',
        ]);

        $newUsername = strtolower(trim($data['username']));

        if (ReservedUsernames::isReserved($newUsername)) {
            return response()->json(['message' => 'Username ini tidak diperbolehkan (dipakai untuk halaman sistem).'], 422);
        }

        if (Vendor::where('username', $newUsername)->where('id', '!=', $vendor->id)->exists()) {
            return response()->json(['message' => 'Username sudah dipakai toko lain.'], 422);
        }

        // Cooldown 1 minggu (kecuali belum pernah set / username masih default auto-generated)
        if ($vendor->username !== $newUsername && $vendor->username_changed_at) {
            $next = $vendor->username_changed_at->copy()->addDays(7);
            if ($next->isFuture()) {
                $diff = now()->diffForHumans($next, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]);
                return response()->json([
                    'message'     => "Username terakhir diubah {$vendor->username_changed_at->diffForHumans()}. Coba lagi dalam {$diff}.",
                    'next_change' => $next->toIso8601String(),
                ], 422);
            }
        }

        $vendor->update([
            'username'            => $newUsername,
            'username_changed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'username' => $newUsername]);
    }

    private function makeTrackingNumber(string $courierName): string
    {
        // Map courier → prefix kode
        $name = strtoupper($courierName);
        $prefix = match (true) {
            str_contains($name, 'JNE')      => 'JNE',
            str_contains($name, 'J&T') || str_contains($name, 'JNT') => 'JNT',
            str_contains($name, 'SICEPAT')  => 'SCP',
            str_contains($name, 'ANTERAJA') => 'ATJ',
            str_contains($name, 'POS')      => 'POS',
            str_contains($name, 'GOSEND') || str_contains($name, 'GO-JEK') => 'GSD',
            str_contains($name, 'GRAB')     => 'GRB',
            str_contains($name, 'NINJA')    => 'NJV',
            str_contains($name, 'TIKI')     => 'TKI',
            str_contains($name, 'WAHANA')   => 'WHN',
            default                         => 'EXP',
        };
        $number = str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }

    private function requireApprovedVendor(Request $request): Vendor
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) abort(404, 'Belum punya toko');
        if ($vendor->verification_status === 'PENDING') {
            abort(403, 'Toko Anda masih menunggu verifikasi admin');
        }
        if ($vendor->verification_status === 'REJECTED') {
            abort(403, 'Verifikasi toko ditolak' . ($vendor->verification_note ? ': ' . $vendor->verification_note : ''));
        }
        if ($vendor->verification_status !== 'APPROVED') {
            abort(403, 'Toko belum terverifikasi');
        }
        return $vendor;
    }

    private function validateVoucher(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : '';
        return $request->validate([
            'code' => $sometimes . 'required|string|max:30|regex:/^[A-Za-z0-9_-]+$/',
            'type' => $sometimes . 'required|in:FIXED,PERCENT',
            'value' => $sometimes . 'required|integer|min:1',
            'min_subtotal' => 'sometimes|integer|min:0',
            'max_discount' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);
    }

    private function syncVoucherProducts(SellerVoucher $voucher, int $vendorId, array $productIds): void
    {
        $validIds = Product::where('vendor_id', $vendorId)->whereIn('id', $productIds)->pluck('id')->all();
        $voucher->products()->sync($validIds);
    }

    private function makeProductSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        if (strlen($base) > 50) $base = substr($base, 0, 50);
        do {
            $candidate = $base . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Product::where('slug', $candidate)->exists());
        return $candidate;
    }

    private function generateUsername(string $name): string
    {
        $base = preg_replace('/[^a-z0-9\-_]/', '', Str::slug($name)) ?: 'toko';
        if (strlen($base) < 3) $base = 'toko-' . $base;
        if (strlen($base) > 28) $base = substr($base, 0, 28);
        if (ReservedUsernames::isReserved($base)) $base = $base . '-id';
        $candidate = $base;
        $n = 1;
        while (Vendor::where('username', $candidate)->exists()) {
            $n++;
            $suffix = '-' . $n;
            $candidate = substr($base, 0, 30 - strlen($suffix)) . $suffix;
        }
        return $candidate;
    }

    private function syncTags(Product $product, array $rawTags): void
    {
        $oldIds = $product->tagModels()->pluck('tags.id')->all();
        $ids = [];
        foreach ($rawTags as $raw) {
            $slug = Str::slug(strtolower(trim($raw)));
            if (!$slug) continue;
            $tag = Tag::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $ids[] = $tag->id;
        }
        $product->tagModels()->sync(array_unique($ids));
        // Update product_count
        Tag::whereIn('id', array_unique(array_merge($oldIds, $ids)))->each(function ($t) {
            $t->product_count = $t->products()->count();
            $t->save();
        });
    }

    private function makeAvatar(string $initial, string $color): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='50' fill='{$color}'/><text x='50' y='65' text-anchor='middle' font-size='42' font-weight='800' fill='#fff' font-family='Inter,sans-serif'>" . htmlspecialchars($initial) . "</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
    private function makeBanner(string $name, string $color): string
    {
        // Cakep gradient + soft pattern dots, tidak butuh upload manual
        $hash = md5($name);
        $c1 = '#' . substr($hash, 0, 6);
        $c2 = '#' . substr($hash, 6, 6);
        $label = htmlspecialchars(mb_substr($name, 0, 24));
        $svg = <<<SVG
<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 300'>
  <defs>
    <linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>
      <stop offset='0%' stop-color='{$c1}'/>
      <stop offset='100%' stop-color='{$c2}'/>
    </linearGradient>
    <pattern id='dots' x='0' y='0' width='40' height='40' patternUnits='userSpaceOnUse'>
      <circle cx='20' cy='20' r='1.5' fill='rgba(255,255,255,0.18)'/>
    </pattern>
  </defs>
  <rect width='1200' height='300' fill='url(#g)'/>
  <rect width='1200' height='300' fill='url(#dots)'/>
  <circle cx='1050' cy='80' r='180' fill='rgba(255,255,255,0.08)'/>
  <circle cx='1150' cy='250' r='90'  fill='rgba(0,0,0,0.10)'/>
  <text x='80' y='170' font-size='52' font-weight='900' fill='#fff' font-family='Inter,sans-serif' letter-spacing='-1.2'>{$label}</text>
</svg>
SVG;
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
    private function makeProductImage(string $name, string $color): string
    {
        $label = htmlspecialchars(mb_substr($name, 0, 16));
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'><rect width='400' height='400' fill='{$color}'/><text x='200' y='220' text-anchor='middle' font-size='28' font-weight='700' fill='#fff' font-family='Inter,sans-serif'>{$label}</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    private function validateInlineImage(string $value, string $label, int $maxMb): void
    {
        if (str_starts_with($value, 'data:image/svg+xml;utf8,')) {
            if (strlen($value) > 200 * 1024) {
                throw ValidationException::withMessages([
                    strtolower(str_replace(' ', '_', $label)) => "{$label} terlalu besar.",
                ]);
            }
            return;
        }

        if (!preg_match('#^data:(image/(?:jpeg|jpg|png|webp|gif));base64,([A-Za-z0-9+/=\r\n]+)$#i', $value, $m)) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} harus berupa gambar JPG, PNG, WebP, atau GIF.",
            ]);
        }

        $bytes = base64_decode($m[2], true);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} tidak bisa dibaca. Coba pilih file lain.",
            ]);
        }

        if (strlen($bytes) > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} maksimal {$maxMb}MB.",
            ]);
        }

        if (@getimagesizefromstring($bytes) === false) {
            throw ValidationException::withMessages([
                strtolower(str_replace(' ', '_', $label)) => "{$label} bukan file gambar yang valid.",
            ]);
        }
    }
}
