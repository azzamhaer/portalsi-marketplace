<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Vendor;
use App\Support\ReservedUsernames;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SellerController extends Controller
{
    public function register(Request $request)
    {
        $user = $request->user();
        if ($user->vendor()->exists()) return response()->json(['message' => 'Anda sudah punya toko'], 422);

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'city'         => 'required|string|max:255',
            'description'  => 'required|string',
            'bank_name'    => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_holder'  => 'nullable|string|max:255',
            'ktp_image'    => 'required|string', // base64 data URI
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'full_address' => 'nullable|string',
        ]);

        $color = '#' . substr(md5($data['name']), 0, 6);

        // Generate unique username dari nama toko
        $username = $this->generateUsername($data['name']);

        $vendor = Vendor::create([
            'user_id'     => $user->id,
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
            'username'    => $username,
            'city'        => $data['city'],
            'latitude'    => $data['latitude'] ?? null,
            'longitude'   => $data['longitude'] ?? null,
            'full_address'=> $data['full_address'] ?? null,
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
        return response()->json($vendor, 201);
    }

    public function dashboard(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);

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
        $vendor = $request->user()->vendor;
        if (!$vendor) abort(404);
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'city'         => 'sometimes|string|max:255',
            'description'  => 'sometimes|string',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'full_address' => 'nullable|string',
            'avatar'       => 'nullable|string',
            'banner'       => 'nullable|string',
            'bank_name'    => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_holder'  => 'nullable|string|max:255',
        ]);

        $vendor->update($data);
        return response()->json($vendor);
    }

    public function products(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        return response()->json(Product::where('vendor_id', $vendor->id)->with(['tagModels:id,slug'])->orderByDesc('id')->get());
    }

    public function storeProduct(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 422);
        if ($vendor->verification_status === 'PENDING') return response()->json(['message' => 'Toko Anda menunggu verifikasi admin'], 422);
        if ($vendor->verification_status === 'REJECTED') return response()->json(['message' => 'Verifikasi toko ditolak: '.$vendor->verification_note], 422);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'required|string',
            'price'          => 'required|integer|min:1',
            'original_price' => 'nullable|integer|min:0',
            'stock'          => 'required|integer|min:0',
            'image'          => 'nullable|string',
            'is_active'      => 'sometimes|boolean',
            'tags'           => 'required|array|min:1',
            'tags.*'         => 'string|max:50',
        ]);

        $product = Product::create([
            'vendor_id'  => $vendor->id,
            'category_id'=> 'elektronik', // default fallback - catalogues now use tags
            'name'       => $data['name'],
            'slug'       => Str::slug($data['name']) . '-' . Str::random(4),
            'description'=> $data['description'],
            'price'      => $data['price'],
            'original_price' => $data['original_price'] ?? null,
            'stock'      => $data['stock'],
            'image'      => $data['image'] ?: $this->makeProductImage($data['name'], '#0a0a0a'),
            'is_active'  => $data['is_active'] ?? true,
        ]);

        $this->syncTags($product, $data['tags']);
        return response()->json($product->load('tagModels'), 201);
    }

    public function updateProduct(Request $request, $id)
    {
        $vendor = $request->user()->vendor;
        $product = Product::where('vendor_id', $vendor?->id)->findOrFail($id);
        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'description'    => 'sometimes|string',
            'price'          => 'sometimes|integer|min:1',
            'original_price' => 'nullable|integer|min:0',
            'stock'          => 'sometimes|integer|min:0',
            'image'          => 'nullable|string',
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
        $vendor = $request->user()->vendor;
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
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        return response()->json(
            OrderItem::where('vendor_id', $vendor->id)
                ->with(['order.user:id,name,email', 'order.address:id,recipient,city,phone', 'order.payment:id,order_id,method_name,status'])
                ->orderByDesc('id')->paginate(30)
        );
    }

    public function shipOrder(Request $request, $id)
    {
        $vendor = $request->user()->vendor;
        $order = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor?->id))->findOrFail($id);
        $tracking = 'JNE' . random_int(1_000_000_000, 9_999_999_999);
        $order->update(['status' => 'SHIPPED', 'shipped_at' => now(), 'tracking_no' => $tracking]);
        return response()->json(['ok' => true, 'tracking_no' => $tracking]);
    }

    public function updateUsername(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);

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
        $ids = [];
        foreach ($rawTags as $raw) {
            $slug = Str::slug(strtolower(trim($raw)));
            if (!$slug) continue;
            $tag = Tag::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $ids[] = $tag->id;
        }
        $product->tagModels()->sync(array_unique($ids));
        // Update product_count
        Tag::whereIn('id', $ids)->each(function ($t) {
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
}
