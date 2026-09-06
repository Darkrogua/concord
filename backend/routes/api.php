<?php

use App\Http\Controllers\Api\AgreementController;
use App\Http\Controllers\Api\AgreementGroupController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\SignatureController;
use App\Http\Controllers\Api\UserGroupController;
use App\Http\Controllers\Api\UserSearchController;
use App\Http\Controllers\Api\VoteController;
use Illuminate\Support\Facades\Route;

Route::get('/agreements/public/{token}', [AgreementController::class, 'publicShow']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::delete('/user', [AuthController::class, 'destroy']);

    Route::get('/users/search', UserSearchController::class);

    Route::get('/signatures', [SignatureController::class, 'index']);
    Route::post('/signatures', [SignatureController::class, 'store']);
    Route::put('/signatures/{signature}', [SignatureController::class, 'update']);
    Route::delete('/signatures/{signature}', [SignatureController::class, 'destroy']);
    Route::post('/signatures/{signature}/activate', [SignatureController::class, 'activate']);

    Route::apiResource('projects', ProjectController::class)->except(['show']);

    Route::get('/agreements', [AgreementController::class, 'index']);
    Route::post('/agreements', [AgreementController::class, 'store']);
    Route::get('/agreements/{agreement}', [AgreementController::class, 'show']);
    Route::put('/agreements/{agreement}', [AgreementController::class, 'update']);
    Route::delete('/agreements/{agreement}', [AgreementController::class, 'destroy']);
    Route::post('/agreements/{agreement}/publish', [AgreementController::class, 'publish']);
    Route::post('/agreements/{agreement}/duplicate', [AgreementController::class, 'duplicate']);
    Route::post('/agreements/{agreement}/archive', [AgreementController::class, 'archive']);
    Route::post('/agreements/{agreement}/restore', [AgreementController::class, 'restore'])->withTrashed();
    Route::post('/agreements/{agreement}/restart', [AgreementController::class, 'restart']);
    Route::post('/agreements/{agreement}/favorite', [AgreementController::class, 'favorite']);

    Route::get('/agreements/{agreement}/sections', [SectionController::class, 'index']);
    Route::post('/agreements/{agreement}/sections', [SectionController::class, 'store']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
    Route::post('/sections/{section}/duplicate', [SectionController::class, 'duplicate']);
    Route::post('/sections/{section}/participants', [SectionController::class, 'addParticipants']);
    Route::post('/sections/{section}/participants/bulk', [SectionController::class, 'addFromGroup']);
    Route::delete('/sections/{section}/participants/{userId}', [SectionController::class, 'removeParticipant']);
    Route::post('/sections/{section}/blocks', [SectionController::class, 'storeBlock']);
    Route::put('/blocks/{informationBlock}', [SectionController::class, 'updateBlock']);
    Route::delete('/blocks/{informationBlock}', [SectionController::class, 'destroyBlock']);

    Route::post('/blocks/{informationBlock}/files', [FileController::class, 'store']);
    Route::get('/files/{storedFile}', [FileController::class, 'download']);
    Route::delete('/files/{storedFile}', [FileController::class, 'destroy']);

    Route::post('/sections/{section}/vote', [VoteController::class, 'store']);
    Route::get('/sections/{section}/votes', [VoteController::class, 'index']);

    Route::apiResource('user-groups', UserGroupController::class)->except(['show']);
    Route::get('/user-groups/{userGroup}/members', [UserGroupController::class, 'members']);

    Route::apiResource('agreement-groups', AgreementGroupController::class)->except(['show']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{userNotification}/read', [NotificationController::class, 'markRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
});
