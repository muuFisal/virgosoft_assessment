<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            // مترجم
            'site_name' => [
                'en' => 'Checkeout',
                'ar' => 'Checkeout',
            ],
            'site_title' => [
                'en' => 'Checkeout — Simple, Secure Payments',
                'ar' => 'تشيك آوت — مدفوعات سهلة وآمنة',
            ],
            'site_desc' => [
                'en' => 'Accept cards and wallets with a fast, reliable checkout for one-time and recurring payments.',
                'ar' => 'اقبل البطاقات والمحافظ عبر بوابة دفع سريعة وموثوقة للمدفوعات الفورية والمتكررة.',
            ],
            'site_address' => [
                'en' => 'Cairo, Egypt',
                'ar' => 'القاهرة، مصر',
            ],
            'meta_key' => [
                'en' => 'payments, checkout, online payments, gateway, cards, wallet, subscriptions',
                'ar' => 'مدفوعات, تشيك آوت, دفع أونلاين, بوابة دفع, بطاقات, محفظة, اشتراكات',
            ],
            'meta_desc' => [
                'en' => 'All-in-one checkout for cards and wallets with fraud protection and clear reporting.',
                'ar' => 'بوابة دفع متكاملة للبطاقات والمحافظ مع حماية من الاحتيال وتقارير واضحة.',
            ],

            // غير مترجم
            'site_phone'    => '+201234567890',
            'site_email'    => 'info@checkeout.com',
            'email_support' => 'support@checkeout.com',

            // سوشيال
            'facebook' => 'https://facebook.com/checkeout',
            'x_url'    => 'https://x.com/checkeout',
            'youtube'  => 'https://youtube.com/@checkeout',
            'instagram' => 'https://instagram.com/checkeout',
            'tiktok'   => 'https://tiktok.com/@checkeout',
            'linkedin' => 'https://linkedin.com/company/checkeout',
            'whatsapp' => '+201234567890',

            // ميديا
            'logo'    => 'uploads/images/logo.png',
            'favicon' => 'uploads/images/logo.png',

            // أخرى
            'site_copyright' => '© ' . now()->year . ' Checkeout. All rights reserved.',
            'promotion_url'  => 'https://checkeout.com/promotion',
        ];

        // تحديث أول سجل إن وُجد أو إنشاء واحد جديد
        $existing = Setting::query()->first();
        if ($existing) {
            $existing->update($data);
        } else {
            Setting::query()->create($data);
        }
    }
}
