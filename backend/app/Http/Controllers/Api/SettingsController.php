<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /** Settings publik (untuk siapa saja, dipakai branding frontend) */
    public function publicSettings()
    {
        return response()->json([
            'appName'   => Setting::get('app_name', ''),
            'logo'      => Setting::get('logo_url', ''),
            'palette'   => Setting::get('palette', 'mono'),
            'primary'   => Setting::get('primary_color', '#0a0a0a'),
            'primaryFg' => Setting::get('primary_fg', '#ffffff'),
            'accent'    => Setting::get('accent_color', '#6366f1'),
            'tagline'   => Setting::get('tagline', 'Marketplace untuk semua'),
            // Custom page content
            'heroTitle'     => Setting::get('hero_title', 'Belanja yang membuat hidup lebih mudah.'),
            'heroSubtitle'  => Setting::get('hero_subtitle', 'Ribuan produk pilihan dari toko terverifikasi. Pembayaran aman, pengiriman cepat.'),
            'heroCtaLabel'  => Setting::get('hero_cta_label', 'Mulai belanja'),
            'heroCtaHref'   => Setting::get('hero_cta_href', '/products'),
            'heroImage'     => Setting::get('hero_image', ''),
            'paymentIntro'  => Setting::get('payment_intro', ''),
            'helpIntro'     => Setting::get('help_intro', ''),
        ]);
    }

    /** Settings admin (lengkap dengan tripay env) */
    public function adminSettings()
    {
        return response()->json([
            'app_name'      => Setting::get('app_name', 'MPSI'),
            'logo_url'      => Setting::get('logo_url', ''),
            'palette'       => Setting::get('palette', 'mono'),
            'primary_color' => Setting::get('primary_color', '#0a0a0a'),
            'primary_fg'    => Setting::get('primary_fg', '#ffffff'),
            'accent_color'  => Setting::get('accent_color', '#6366f1'),
            'tagline'       => Setting::get('tagline', 'Marketplace untuk semua'),
            'tripay_mode'   => Setting::get('tripay_mode', config('services.tripay.mode')),
            'tripay_api_key'=> Setting::get('tripay_api_key', ''),
            'tripay_private_key' => Setting::get('tripay_private_key', ''),
            'tripay_merchant_code' => Setting::get('tripay_merchant_code', ''),
            'commission_percent' => (float) Setting::get('commission_percent', 5),
            // Brevo (email)
            'brevo_api_key'      => Setting::get('brevo_api_key', ''),
            'brevo_sender_email' => Setting::get('brevo_sender_email', ''),
            'brevo_sender_name'  => Setting::get('brevo_sender_name', ''),
            // Custom page content
            'hero_title'    => Setting::get('hero_title', 'Belanja yang membuat hidup lebih mudah.'),
            'hero_subtitle' => Setting::get('hero_subtitle', 'Ribuan produk pilihan dari toko terverifikasi. Pembayaran aman, pengiriman cepat.'),
            'hero_cta_label'=> Setting::get('hero_cta_label', 'Mulai belanja'),
            'hero_cta_href' => Setting::get('hero_cta_href', '/products'),
            'hero_image'    => Setting::get('hero_image', ''),
            'payment_intro' => Setting::get('payment_intro', ''),
            'help_intro'    => Setting::get('help_intro', ''),
            'palettes' => self::PALETTES,
        ]);
    }

    public const PALETTES = [
        ['key'=>'mono',    'name'=>'Mono Black',     'primary'=>'#0a0a0a', 'primaryFg'=>'#ffffff', 'accent'=>'#6366f1'],
        ['key'=>'indigo',  'name'=>'Royal Indigo',   'primary'=>'#4f46e5', 'primaryFg'=>'#ffffff', 'accent'=>'#f59e0b'],
        ['key'=>'forest',  'name'=>'Forest Green',   'primary'=>'#166534', 'primaryFg'=>'#ffffff', 'accent'=>'#eab308'],
        ['key'=>'sunset',  'name'=>'Warm Sunset',    'primary'=>'#ea580c', 'primaryFg'=>'#ffffff', 'accent'=>'#0891b2'],
        ['key'=>'rose',    'name'=>'Rose Romance',   'primary'=>'#be185d', 'primaryFg'=>'#ffffff', 'accent'=>'#0ea5e9'],
        ['key'=>'midnight','name'=>'Midnight Blue',  'primary'=>'#1e3a8a', 'primaryFg'=>'#ffffff', 'accent'=>'#22d3ee'],
    ];

    public function adminSave(Request $request)
    {
        $data = $request->validate([
            'app_name'      => 'sometimes|string|max:50',
            'palette'       => 'sometimes|string',
            'primary_color' => 'sometimes|string',
            'primary_fg'    => 'sometimes|string',
            'accent_color'  => 'sometimes|string',
            'tagline'       => 'sometimes|string|max:200',
            'tripay_mode'   => 'sometimes|in:sandbox,production',
            'tripay_api_key'=> 'sometimes|string',
            'tripay_private_key' => 'sometimes|string',
            'tripay_merchant_code' => 'sometimes|string',
            'commission_percent'   => 'sometimes|numeric|min:0|max:50',
            'brevo_api_key'        => 'sometimes|nullable|string',
            'brevo_sender_email'   => 'sometimes|nullable|email',
            'brevo_sender_name'    => 'sometimes|nullable|string|max:100',
            'hero_title'    => 'sometimes|string|max:200',
            'hero_subtitle' => 'sometimes|string|max:500',
            'hero_cta_label'=> 'sometimes|string|max:50',
            'hero_cta_href' => 'sometimes|string|max:200',
            'hero_image'    => 'sometimes|nullable|string',
            'payment_intro' => 'sometimes|nullable|string|max:5000',
            'help_intro'    => 'sometimes|nullable|string|max:5000',
        ]);

        // Apply preset palette if provided
        if (!empty($data['palette'])) {
            foreach (self::PALETTES as $p) {
                if ($p['key'] === $data['palette']) {
                    $data['primary_color'] = $p['primary'];
                    $data['primary_fg']    = $p['primaryFg'];
                    $data['accent_color']  = $p['accent'];
                    break;
                }
            }
        }
        foreach ($data as $k => $v) Setting::put($k, $v);
        return response()->json(['ok' => true]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|file|image|max:1024']);
        $file = $request->file('logo');
        $contents = base64_encode(file_get_contents($file->getRealPath()));
        $mime = $file->getMimeType();
        $dataUri = "data:{$mime};base64,{$contents}";
        Setting::put('logo_url', $dataUri);
        return response()->json(['logo_url' => $dataUri]);
    }

    public function uploadHero(Request $request)
    {
        $request->validate(['hero' => 'required|file|image|max:4096']);
        $file = $request->file('hero');
        $contents = base64_encode(file_get_contents($file->getRealPath()));
        $mime = $file->getMimeType();
        $dataUri = "data:{$mime};base64,{$contents}";
        Setting::put('hero_image', $dataUri);
        return response()->json(['hero_image' => $dataUri]);
    }
}
