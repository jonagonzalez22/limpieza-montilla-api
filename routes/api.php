<?php

use App\Http\Controllers\Api\Catalog\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', CategoryController::class);
