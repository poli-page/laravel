<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RenderController;
use Illuminate\Support\Facades\Route;

// Demo UI lands in Task 11; placeholder for now.
Route::get('/', fn (): string => 'Demo UI arrives in Task 11.');

Route::get('/api/render/pdf', [RenderController::class, 'pdf']);
Route::get('/api/render/stream', [RenderController::class, 'stream']);
Route::get('/api/render/preview', [RenderController::class, 'preview']);
Route::post('/api/documents', [RenderController::class, 'createDocument']);

Route::get('/api/documents/{id}', [DocumentController::class, 'get']);
Route::get('/api/documents/{id}/thumbnails', [DocumentController::class, 'thumbnails']);
Route::get('/api/documents/{id}/preview', [DocumentController::class, 'preview']);
Route::delete('/api/documents/{id}', [DocumentController::class, 'delete']);
Route::get('/api/errors/bad-version', [DocumentController::class, 'badVersion']);
