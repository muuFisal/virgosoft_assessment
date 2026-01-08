<?php

namespace App\Repositories\Api\Exchange;

use App\Models\Trade;

class TradeRepository
{
    public function create(array $data): Trade
    {
        return Trade::create($data);
    }
}
