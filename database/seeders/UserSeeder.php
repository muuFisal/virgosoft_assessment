<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyer = User::create([
            'image' => 'uploads/images/image.png',
            'name' => 'Buyer',
            'email' => 'buyer@gmail.com',
            'phone' => '1234567890',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'status' => 1,
            'balance' => 10000,
        ]);

        $seller = User::create([
            'image' => 'uploads/images/image.png',
            'name' => 'Seller',
            'email' => 'seller@gmail.com',
            'phone' => '0987654321',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'status' => 1,
            'balance' => 0,
        ]);

        // Seller owns some crypto to sell
        Asset::updateOrCreate(
            ['user_id' => $seller->id, 'symbol' => 'BTC'],
            ['amount' => 1.5, 'locked_amount' => 0]
        );
        Asset::updateOrCreate(
            ['user_id' => $seller->id, 'symbol' => 'ETH'],
            ['amount' => 10, 'locked_amount' => 0]
        );
    }
}
