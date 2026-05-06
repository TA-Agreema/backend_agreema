<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Admin\UserRoleController;
use App\Http\Controllers\API\Admin\PermissionController;
use App\Http\Controllers\API\Admin\CategoryController;
use App\Http\Controllers\API\Admin\TemplateController;
use App\Http\Controllers\API\Admin\FieldDefinitionController;
use App\Http\Controllers\API\Hrd\ContractController;

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

        Route::get('/roles', [UserRoleController::class, 'index'])->middleware(('permission:read.all.roles|read.permission')); //melihat daftar keseluruhan roles
        Route::post('/add-roles', [UserRoleController::class, 'store'])->middleware(('permission:create.role')); //menambah roles baru
        Route::get('/show-roles/{id}', [UserRoleController::class, 'show'])->middleware(('permission:read.role')); //melihat detail roles
        Route::patch('/update-roles/{id}', [UserRoleController::class, 'update'])->middleware(('permission:update.role')); //mengupdate detail roles
        Route::delete('/delete-roles/{id}', [UserRoleController::class, 'destroy'])->middleware(('permission:delete.role')); //menghapus roles
        Route::get('/permissions', [PermissionController::class, 'index'])->middleware(('permission:read.permission')); //melihat daftar permission
    });

    Route::prefix('/category')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->middleware('permission:read.contract_category');
        Route::post('/', [CategoryController::class, 'store'])->middleware('permission:create.contract_category');
        Route::get('/{id}', [CategoryController::class, 'show'])->middleware('permission:read.contract_category');
        Route::patch('/{id}', [CategoryController::class, 'update'])->middleware('permission:update.contract_category');
        Route::delete('/{id}', [CategoryController::class, 'destroy'])->middleware('permission:delete.contract_category');
        Route::patch('/{id}/toggle-status', [CategoryController::class, 'toggleStatus'])->middleware('permission:update.contract_category');
    });

    // Contracts: daftar kontrak 
    Route::prefix('contracts')->group(function () {
        Route::get('/', [ContractController::class, 'index'])->middleware('permission:read.contracts');
        Route::post('/', [ContractController::class, 'store'])->middleware('permission:create.contract');
        Route::get('/{id}', [ContractController::class, 'show'])->middleware('permission:read.contracts');
        Route::patch('/{id}', [ContractController::class, 'update'])->middleware('permission:update.contract');
        Route::delete('/{id}', [ContractController::class, 'destroy'])->middleware('permission:delete.contract');
        Route::patch('/{id}/toggle-status', [ContractController::class, 'toggleStatus'])->middleware('permission:update.contract');
    });

    // Templates
    Route::prefix('templates')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->middleware('permission:read.template');
        Route::get('/{id}', [TemplateController::class, 'show'])->middleware('permission:read.template');
        Route::post('/', [TemplateController::class, 'store'])->middleware('permission:create.template');
        Route::patch('/{id}', [TemplateController::class, 'update'])->middleware('permission:update.template');
        Route::delete('/{id}', [TemplateController::class, 'destroy'])->middleware('permission:delete.template');
        Route::patch('/{id}/toggle-status', [TemplateController::class, 'toggleStatus'])->middleware('permission:update.template');
    });

    Route::get('/field-definitions', [FieldDefinitionController::class, 'index']);
});
