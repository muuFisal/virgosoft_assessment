<?php

namespace App\Repositories\Api\Exchange;

use App\Models\Asset;

class AssetRepository
{
    public function getForUpdate(int $userId, string $symbol): ?Asset
    {
        return Asset::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();
    }

    public function getOrCreateForUpdate(int $userId, string $symbol): Asset
    {
        $asset = $this->getForUpdate($userId, $symbol);
        if ($asset) {
            return $asset;
        }

        // Create then re-lock
        Asset::create([
            'user_id' => $userId,
            'symbol' => $symbol,
            'amount' => 0,
            'locked_amount' => 0,
        ]);

        return $this->getForUpdate($userId, $symbol);
    }

    public function listForUser(int $userId)
    {
        return Asset::where('user_id', $userId)
            ->orderBy('symbol')
            ->get();
    }
}
