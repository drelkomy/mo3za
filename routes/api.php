<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Area;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

