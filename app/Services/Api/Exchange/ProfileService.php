<?php

namespace App\Services\Api\Exchange;

use App\Repositories\Api\Exchange\AssetRepository;
use App\Models\User;

class ProfileService
{
    public function __construct(protected AssetRepository $assetRepository) {}

    public function getProfile(int $userId): array
    {
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $assets = $this->assetRepository->listForUser($userId);

        return [
            'user_id' => (int) $user->id,
            'usd' => [
                'balance' => (float) $user->balance,
                'locked_balance' => (float) $user->locked_balance,
            ],
            'assets' => $assets,
        ];
    }
}
