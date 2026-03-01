<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\SubscriptionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Serve files
Route::get('/posts/{filename}', function ($filename) {
    $path = storage_path('app/public/posts/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});

Route::get('/school-logos/{filename}', function ($filename) {
    $path = storage_path('app/public/school-logos/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});

Route::get('/signatures/{filename}', function ($filename) {
    $path = storage_path('app/public/signatures/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});


Route::get('/student-passport/{filename}', function ($filename) {
    $path = storage_path('app/public/student-passport/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});


Route::get('/profile-images/{filename}', function ($filename) {
    $path = storage_path('app/public/profile-images/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});

Route::get('/teacher-signatures/{filename}', function ($filename) {
    $path = storage_path('app/public/teacher-signatures/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});


Route::post('/flutterwave/webhook', [WebhookController::class, 'handle'])->withoutMiddleware('csrf');
Route::get('/subscription/redirect', [SubscriptionController::class, 'handleRedirect']);
