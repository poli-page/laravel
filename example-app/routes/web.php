<?php

declare(strict_types=1);

use App\Http\Controllers\DemoController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RenderController;
use Illuminate\Support\Facades\Route;

Route::get('/', DemoController::class)->name('demo');

Route::get('/api/render/pdf', [RenderController::class, 'pdf']);
Route::get('/api/render/stream', [RenderController::class, 'stream']);
Route::post('/api/render/file', [RenderController::class, 'renderFile']);
Route::post('/api/documents', [RenderController::class, 'createDocument']);

Route::get('/api/documents/{id}', [DocumentController::class, 'get']);
Route::get('/api/documents/{id}/thumbnails', [DocumentController::class, 'thumbnails']);
Route::get('/api/documents/{id}/preview', [DocumentController::class, 'preview']);
Route::delete('/api/documents/{id}', [DocumentController::class, 'delete']);
Route::get('/api/errors/not-found', [DocumentController::class, 'notFound']);
