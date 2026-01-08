<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SettingsResource;

class SettingsController extends Controller
{
    public function index(){
        $settings = Setting::first();
        if (!$settings) {
            return ApiResponse::sendResponse(200, __('front.somthing-went-wrong'), []);
        }
        return ApiResponse::sendResponse(200, __('front.Settings-retrieved-successfully'), SettingsResource::collection([$settings]));
    }
}
