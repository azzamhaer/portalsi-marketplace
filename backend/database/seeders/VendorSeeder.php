<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            ['key'=>'v1', 'name'=>'TechMart Official',     'city'=>'Jakarta Pusat',  'desc'=>'Toko resmi gadget & elektronik terlengkap. Garansi resmi 100%.',         'official'=>true,  'rating'=>4.9, 'sold'=>18420, 'fol'=>32100, 'color'=>'#0A0A0A', 'em'=>'📱'],
            ['key'=>'v2', 'name'=>'Fashion Hub Indonesia', 'city'=>'Bandung',        'desc'=>'Trend fashion pria & wanita import original.',                            'official'=>true,  'rating'=>4.8, 'sold'=>24310, 'fol'=>51230, 'color'=>'#1F1F1F', 'em'=>'👗'],
            ['key'=>'v3', 'name'=>'Beauty Box ID',         'city'=>'Surabaya',       'desc'=>'Skincare & makeup BPOM, ready stock fast response.',                     'official'=>false, 'rating'=>4.9, 'sold'=>9821,  'fol'=>14500, 'color'=>'#374151', 'em'=>'💄'],
            ['key'=>'v4', 'name'=>'SehatSelalu Store',     'city'=>'Yogyakarta',     'desc'=>'Vitamin, alat kesehatan, & herbal terpercaya.',                          'official'=>false, 'rating'=>4.7, 'sold'=>6150,  'fol'=>8800,  'color'=>'#0A0A0A', 'em'=>'💊'],
            ['key'=>'v5', 'name'=>'SportZone Indonesia',   'city'=>'Jakarta Barat',  'desc'=>'Distributor resmi peralatan olahraga & jersey original.',                'official'=>true,  'rating'=>4.8, 'sold'=>12200, 'fol'=>19400, 'color'=>'#1F1F1F', 'em'=>'⚽'],
            ['key'=>'v6', 'name'=>'AutoPro Garage',        'city'=>'Bekasi',         'desc'=>'Sparepart motor & mobil + aksesoris berkualitas.',                       'official'=>false, 'rating'=>4.6, 'sold'=>4320,  'fol'=>6700,  'color'=>'#0A0A0A', 'em'=>'🏍️'],
            ['key'=>'v7', 'name'=>'Foodie Paradise',       'city'=>'Tangerang',      'desc'=>'Snack import, makanan beku, & minuman kekinian.',                        'official'=>false, 'rating'=>4.9, 'sold'=>15800, 'fol'=>22100, 'color'=>'#374151', 'em'=>'🍔'],
            ['key'=>'v8', 'name'=>'Buku Pintar Store',     'city'=>'Depok',          'desc'=>'Buku original, alat tulis & perlengkapan sekolah lengkap.',              'official'=>true,  'rating'=>4.9, 'sold'=>8400,  'fol'=>11200, 'color'=>'#1F1F1F', 'em'=>'📚'],
            ['key'=>'v9', 'name'=>'Rumah Idaman',          'city'=>'Semarang',       'desc'=>'Furniture, dekorasi & perlengkapan rumah modern.',                       'official'=>false, 'rating'=>4.7, 'sold'=>5230,  'fol'=>7100,  'color'=>'#0A0A0A', 'em'=>'🛋️'],
            ['key'=>'v10','name'=>'Gadget Galaxy',         'city'=>'Medan',          'desc'=>'Aksesoris HP, charger fast charging, & smartwatch.',                     'official'=>false, 'rating'=>4.8, 'sold'=>3120,  'fol'=>4800,  'color'=>'#374151', 'em'=>'📱'],
            ['key'=>'v11','name'=>'Toys Wonderland',       'city'=>'Bali',           'desc'=>'Mainan edukasi anak & perlengkapan bayi premium.',                       'official'=>false, 'rating'=>4.9, 'sold'=>6800,  'fol'=>9300,  'color'=>'#0A0A0A', 'em'=>'🧸'],
            ['key'=>'v12','name'=>'Voucher Express',       'city'=>'Jakarta Selatan','desc'=>'Top up game, pulsa, paket data, & voucher digital instan.',              'official'=>true,  'rating'=>5.0, 'sold'=>42100, 'fol'=>38500, 'color'=>'#1F1F1F', 'em'=>'🎟️'],
        ];

        // Demo seller acc owns Fashion Hub (v2)
        $demoSeller = User::create([
            'name'     => 'Demo Seller',
            'email'    => 'seller@portalsi.id',
            'password' => Hash::make('seller123'),
            'role'     => 'SELLER',
            'phone'    => '08222222222',
        ]);

        $map = [];
        foreach ($vendors as $v) {
            $userId = ($v['key'] === 'v2')
                ? $demoSeller->id
                : User::create([
                    'name'     => 'Owner ' . $v['name'],
                    'email'    => "owner-{$v['key']}@portalsi.id",
                    'password' => Hash::make('seller123'),
                    'role'     => 'SELLER',
                    'phone'    => '08000000' . substr($v['key'], 1),
                ])->id;

            $created = Vendor::create([
                'user_id'     => $userId,
                'name'        => $v['name'],
                'slug'        => Str::slug($v['name']),
                'username'    => Str::slug($v['name']),
                'city'        => $v['city'],
                'description' => $v['desc'],
                'avatar'      => Helpers::avatar(mb_substr($v['name'], 0, 1), $v['color']),
                'banner'      => Helpers::banner($v['em'], $v['color'], '#1f1f1f', $v['name'], substr($v['desc'], 0, 60)),
                'bank_name'   => 'BCA',
                'bank_account'=> '1234567890',
                'bank_holder' => $v['name'],
                // Rating real — start 0, naik saat ada review pembeli
                'rating'      => 0,
                'total_sold'  => $v['sold'],
                'followers'   => $v['fol'],
                'is_official' => $v['official'],
                'verification_status' => 'APPROVED',
            ]);
            $map[$v['key']] = $created->id;
        }

        cache()->forever('seed.vendor_map', $map);
    }
}
