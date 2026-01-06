<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Admin\UserRoleController;
use App\Http\Controllers\API\Admin\PermissionController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware(('permission:read.all.users'));
        Route::post('/add-user', [UserController::class, 'store'])->middleware(('permission:create.user'));
        Route::get('/show-user/{id}', [UserController::class, 'show'])->middleware(('permission:read.user'));
        Route::patch('/update-user/{id}', [UserController::class, 'update'])->middleware(('permission:update.user'));
        Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->middleware(('permission:delete.user'));
        Route::patch('/update-user-roles/{id}', [UserController::class, 'updateUserRoles'])->middleware(('permission:update.user.roles'));

        Route::get('/roles', [UserRoleController::class, 'index']); 
        Route::post('/add-roles', [UserRoleController::class, 'store'])->middleware(('permission:create.role'));
        Route::get('/show-roles/{id}', [UserRoleController::class, 'show'])->middleware(('permission:read.role'));
        Route::patch('/update-roles/{id}', [UserRoleController::class, 'update'])->middleware(('permission:update.role'));
        Route::delete('/delete-roles/{id}', [UserRoleController::class, 'destroy'])->middleware(('permission:delete.role'));

        Route::get('/permissions', [PermissionController::class, 'index'])->middleware(('permission:read.permission'));
    });
});



