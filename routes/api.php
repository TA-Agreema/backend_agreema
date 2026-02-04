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
        Route::get('/', [UserController::class, 'index'])->middleware(('permission:read.all.users')); //melihat daftar keseluruhan user
        Route::post('/add-user', [UserController::class, 'store'])->middleware(('permission:create.user')); //menambah user baru
        Route::get('/show-user/{id}', [UserController::class, 'show'])->middleware(('permission:read.user')); //melihat detail user
        Route::patch('/update-user/{id}', [UserController::class, 'update'])->middleware(('permission:update.user')); //mengupdate detail user
        Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->middleware(('permission:delete.user')); //menghapus user
        Route::patch('/update-user-roles/{id}', [UserController::class, 'updateUserRoles'])->middleware(('permission:update.user.roles')); //mengupdate roles user

        Route::get('/roles', [UserRoleController::class, 'index'])->middleware(('permission:read.all.roles|read.permissions')); //melihat daftar keseluruhan roles
        Route::post('/add-roles', [UserRoleController::class, 'store'])->middleware(('permission:create.role')); //menambah roles baru
        Route::get('/show-roles/{id}', [UserRoleController::class, 'show'])->middleware(('permission:read.role')); //melihat detail roles
        Route::patch('/update-roles/{id}', [UserRoleController::class, 'update'])->middleware(('permission:update.role')); //mengupdate detail roles
        Route::delete('/delete-roles/{id}', [UserRoleController::class, 'destroy'])->middleware(('permission:delete.role')); //menghapus roles
        Route::get('/permissions', [PermissionController::class, 'index'])->middleware(('permission:read.permission')); //melihat daftar permission
    });
});



