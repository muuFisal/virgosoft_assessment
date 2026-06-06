<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'exchange')->name('login');
Route::view('/exchange', 'exchange');
