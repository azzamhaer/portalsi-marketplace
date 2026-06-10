<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Vendor;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories()
    {
        return response()->json(Category::orderBy('sort_order')->get());
    }

    /** Suggestion live untuk search bar — produk + toko + tag */
    public function searchSuggest(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (strlen($q) < 1) {
            return response()->json(['products' => [], 'vendors' => [], 'tags' => []]);
        }
        $products = Product::where('is_active', true)
            ->where('name', 'like', "%$q%")
            ->select('id', 'name', 'slug', 'image', 'price')
            ->take(6)->get();
        $vendors = Vendor::where('verification_status', 'APPROVED')
            ->where(fn($w) => $w->where('name', 'like', "%$q%")->orWhere('username', 'like', "%$q%"))
            ->select('id', 'name', 'username', 'avatar', 'city')
            ->take(4)->get();
        $tags = Tag::where('slug', 'like', "%$q%")
            ->orderByDesc('product_count')
            ->select('slug', 'name', 'product_count')
            ->take(6)->get();
        return response()->json([
            'products' => $products,
            'vendors'  => $vendors,
            'tags'     => $tags,
        ]);
    }

    public function products(Request $request)
    {
        $q = Product::query()->where('is_active', true)
            ->whereHas('vendor', fn($v) => $v->where('moderation_mode', '!=', 'DISABLED')->where('verification_status', 'APPROVED'))
            ->with(['vendor:id,name,slug,username,city,is_official,moderation_mode', 'tagModels:id,slug'])
            ->withCount('reviews');

        if ($cat = $request->query('category')) {
            $q->whereHas('category', fn($c) => $c->where('slug', $cat));
        }
        if ($tag = $request->query('tag')) {
            $q->whereHas('tagModels', fn($t) => $t->where('slug', $tag));
        }
        if ($vendor = $request->query('vendor')) {
            $q->where('vendor_id', $vendor);
        }
        if ($search = $request->query('search')) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%$search%")
                   ->orWhere('description', 'like', "%$search%");
            });
        }
        if ($request->boolean('flash')) $q->where('is_flash_sale', true);
        if ($ids = $request->query('ids')) $q->whereIn('id', explode(',', $ids));

        $sort = $request->query('sort', 'popular');
        match ($sort) {
            'cheap'   => $q->orderBy('price'),
            'exp'     => $q->orderByDesc('price'),
            'rating'  => $q->orderByDesc('rating'),
            'newest'  => $q->orderByDesc('id'),
            default   => $q->orderByDesc('sold'),
        };

        $perPage = min(100, (int) $request->query('per_page', 24));
        return response()->json($q->paginate($perPage));
    }

    public function product($idOrSlug)
    {
        $query = Product::with([
            'vendor',
            'category',
            'tagModels:id,slug',
            'reviews' => fn($q) => $q->latest()->with('user:id,name'),
        ])->withCount('reviews');
        $product = is_numeric($idOrSlug)
            ? $query->find((int) $idOrSlug)
            : $query->where('slug', $idOrSlug)->first();
        if (!$product) abort(404, 'Produk tidak ditemukan');
        $related = Product::where('id', '!=', $product->id)
            ->where('is_active', true)
            ->whereHas('tagModels', function ($q) use ($product) {
                $q->whereIn('id', $product->tagModels->pluck('id'));
            })
            ->with('tagModels:id,slug')
            ->orderByDesc('sold')
            ->take(12)
            ->get();
        return response()->json(['product' => $product, 'related' => $related]);
    }

    public function vendors(Request $request)
    {
        $q = Vendor::query();
        if ($request->query('filter') === 'official') $q->where('is_official', true);
        match ($request->query('filter')) {
            'rating' => $q->orderByDesc('rating'),
            'sold'   => $q->orderByDesc('total_sold'),
            default  => $q->orderByDesc('is_official')->orderByDesc('total_sold'),
        };
        // Hanya tampilkan vendor APPROVED & tidak DISABLED ke publik
        $q->where('verification_status', 'APPROVED')->where('moderation_mode', '!=', 'DISABLED');
        return response()->json($q->get());
    }

    public function vendor(Request $request, $idOrUsername)
    {
        $key = strtolower((string) $idOrUsername);
        $vendor = is_numeric($idOrUsername)
            ? Vendor::find((int) $idOrUsername)
            : Vendor::where('username', $key)->orWhere('slug', $key)->first();

        if (!$vendor) abort(404, 'Toko tidak ditemukan');

        // Block halaman publik kalau vendor belum APPROVED atau DISABLED total — kecuali admin atau pemilik toko
        $viewer = auth('sanctum')->user();
        $isOwner = $viewer && $viewer->id === $vendor->user_id;
        $isAdmin = $viewer && $viewer->role === 'ADMIN';
        if (($vendor->verification_status !== 'APPROVED' || $vendor->moderation_mode === 'DISABLED') && !$isOwner && !$isAdmin) {
            abort(404, 'Toko tidak ditemukan');
        }

        // Rating toko = AVG rating semua produk yang punya review
        $avgRating = (float) \App\Models\Product::where('vendor_id', $vendor->id)
            ->whereHas('reviews')->avg('rating');
        if (abs($vendor->rating - $avgRating) > 0.01) {
            $vendor->update(['rating' => round($avgRating, 2)]);
        }

        $products = $vendor->products()->where('is_active', true)->with('tagModels:id,slug')->withCount('reviews')->orderByDesc('sold')->get();
        // Try to detect auth (sanctum guard) silently
        $user = auth('sanctum')->user();
        $isFollowing = false;
        if ($user) {
            $isFollowing = \App\Models\VendorFollower::where('vendor_id', $vendor->id)
                            ->where('user_id', $user->id)->exists();
        }
        return response()->json([
            'vendor' => $vendor,
            'products' => $products,
            'is_following' => $isFollowing,
        ]);
    }

    public function homeData()
    {
        return response()->json([
            'tags'        => Tag::orderByDesc('product_count')->take(20)->get(['slug', 'name', 'product_count as count']),
            'flashSale'   => Product::where('is_flash_sale', true)->where('is_active', true)->with('tagModels:id,slug')->withCount('reviews')->take(12)->get(),
            'recommended' => Product::where('is_active', true)->with('tagModels:id,slug')->withCount('reviews')->orderByDesc('sold')->take(24)->get(),
            'official'    => Vendor::where('is_official', true)->where('verification_status', 'APPROVED')->take(6)->get(),
        ]);
    }
}
