<?php

declare(strict_types=1);

use App\Http\Controllers\DemoController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RenderController;
use Illuminate\Support\Facades\Route;

Route::get('/', DemoController::class)->name('demo');

Route::get('/render/pdf', [RenderController::class, 'pdf']);
Route::get('/render/stream', [RenderController::class, 'stream']);
Route::post('/render/file', [RenderController::class, 'renderFile']);
Route::post('/documents', [RenderController::class, 'createDocument']);

Route::get('/documents/{id}', [DocumentController::class, 'get']);
Route::get('/documents/{id}/thumbnails', [DocumentController::class, 'thumbnails']);
Route::get('/documents/{id}/preview', [DocumentController::class, 'preview']);
Route::delete('/documents/{id}', [DocumentController::class, 'delete']);
Route::get('/errors/not-found', [DocumentController::class, 'notFound']);
