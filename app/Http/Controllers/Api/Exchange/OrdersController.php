<?php

namespace App\Http\Controllers\Api\Exchange;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Exchange\StoreOrderRequest;
use App\Repositories\Api\Exchange\OrderRepository;
use App\Services\Api\Exchange\OrderService;
use App\Services\Api\Exchange\MatchingService;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderService $orderService,
        protected MatchingService $matchingService,
    ) {}

    public function index(Request $request)
    {
        $userId = (int) auth('sanctum')->id();
        $symbol = $request->query('symbol');

        $orders = $this->orderRepository->listByUser($userId, $symbol);

        return ApiResponse::sendResponse(200, 'OK', $orders->items(), [
            'total' => $orders->total(),
            'per_page' => $orders->perPage(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'next_page_url' => $orders->nextPageUrl(),
            'prev_page_url' => $orders->previousPageUrl(),
        ]);
    }

    public function orderbook(Request $request)
    {
        $symbol = strtoupper((string) $request->query('symbol', 'BTC'));
        if (! in_array($symbol, ['BTC', 'ETH'], true)) {
            return ApiResponse::sendResponse(422, 'Invalid symbol', []);
        }

        $book = $this->orderRepository->getOrderbook($symbol);

        return ApiResponse::sendResponse(200, 'OK', [
            'symbol' => $symbol,
            'buy' => $book['buy'],
            'sell' => $book['sell'],
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        $userId = (int) auth('sanctum')->id();
        $response = $this->orderService->create($userId, $request->validated());

        return ApiResponse::sendResponse($response['status'], $response['message'], $response['data']);
    }

    public function cancel(int $id)
    {
        $userId = (int) auth('sanctum')->id();
        $response = $this->orderService->cancel($userId, $id);

        return ApiResponse::sendResponse($response['status'], $response['message'], $response['data']);
    }

    /**
     * Manual matching trigger (optional for testing). It will attempt to match the given order id.
     */
    public function match(int $id)
    {
        $match = $this->matchingService->matchOne($id);
        return ApiResponse::sendResponse(200, 'OK', ['match' => $match]);
    }
}
