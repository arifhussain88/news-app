<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NewsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// For Fetching data from external APIs
Route::get('/fetchdata/{type}/{page?}', [NewsController::class, 'fetchApi']);

// For showing local API data
Route::get('/newsapi', [NewsController::class, 'index']);