<?php

namespace App\Http\Controllers\Api\Exchange;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Api\Exchange\ProfileService;

class ProfileController extends Controller
{
    public function __construct(protected ProfileService $profileService) {}

    public function show()
    {
        $userId = (int) auth('sanctum')->id();
        $data = $this->profileService->getProfile($userId);

        return ApiResponse::sendResponse(200, 'OK', $data);
    }
}
