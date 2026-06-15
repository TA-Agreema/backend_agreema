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
use App\Http\Controllers\API\Hrd\SignerController;
use App\Http\Controllers\API\Hrd\ContractAddendumController;
use App\Http\Controllers\API\Hrd\ContractTerminationController;
use App\Http\Controllers\API\Manager\ContractReviewController;
use App\Http\Controllers\API\External\ExternalContractController;
use App\Http\Controllers\API\DashboardController;

Route::post('/login', [AuthController::class, 'login']);

// External Routes (No auth required)
Route::prefix('external/contracts')->group(function () {
    Route::get('/preview', [ExternalContractController::class, 'preview']);
    Route::post('/review', [ExternalContractController::class, 'review']);
    Route::post('/sign', [ExternalContractController::class, 'sign']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

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

    // Signers
    Route::prefix('signers')->group(function () {
        Route::get('/internal', [SignerController::class, 'internalSigners'])->middleware('permission:create.contract');
    });

    // Parties (partners)
    Route::get('/partners', [\App\Http\Controllers\API\Hrd\PartyController::class, 'index'])->middleware('permission:read.contracts');

    // Contracts: daftar kontrak
    Route::prefix('contracts')->group(function () {
        Route::get('/generate-number', [ContractController::class, 'generateNumber'])->middleware('permission:create.contract');
        Route::get('/', [ContractController::class, 'index'])->middleware('permission:read.contracts');
        Route::post('/', [ContractController::class, 'store'])->middleware('permission:create.contract');
        Route::get('/{id}/download', [ContractController::class, 'download'])->middleware('permission:read.contracts');
        Route::get('/{id}', [ContractController::class, 'show'])->middleware('permission:read.contracts');
        Route::patch('/{id}', [ContractController::class, 'update'])->middleware('permission:update.contract');
        Route::delete('/{id}', [ContractController::class, 'destroy'])->middleware('permission:delete.contract');
        Route::patch('/{id}/toggle-status', [ContractController::class, 'toggleStatus'])->middleware('permission:update.contract');
        Route::post('/{id}/submit', [ContractController::class, 'submit'])->middleware('permission:update.contract');

        // Addendum Routes (HRD only)
        Route::get('/{id}/addendums', [ContractAddendumController::class, 'index'])->middleware('permission:read.contracts');
        Route::post('/{id}/addendums', [ContractAddendumController::class, 'store'])->middleware('permission:create.addendum|create.contract_addendum');
        Route::delete('/{contractId}/addendums/{addendumId}', [ContractAddendumController::class, 'destroy'])->middleware('permission:create.addendum|create.contract_addendum');

        // Termination Routes (HRD only)
        Route::get('/{id}/terminations', [ContractTerminationController::class, 'index'])->middleware('permission:read.contracts');
        Route::post('/{id}/terminations', [ContractTerminationController::class, 'store'])->middleware('permission:create.terminate|terminate.contract');
        Route::delete('/{contractId}/terminations/{terminationId}', [ContractTerminationController::class, 'destroy'])->middleware('permission:create.terminate|terminate.contract');
    });

    // Manager Review Routes
    Route::prefix('manager/contracts')->middleware('permission:read.contracts')->group(function () {
        Route::get('/', [ContractReviewController::class, 'index']);
        Route::get('/{id}', [ContractReviewController::class, 'show']);
        Route::post('/{id}/review', [ContractReviewController::class, 'review']);
        Route::post('/{id}/sign', [ContractReviewController::class, 'sign']);
        Route::get('/{id}/download', [ContractReviewController::class, 'download']);
        Route::post('/{id}/upload-signed', [ContractReviewController::class, 'uploadSignedDocument']);
    });

    // Templates
    Route::prefix('templates')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->middleware('permission:read.template');
        Route::get('/{id}/download', [TemplateController::class, 'download'])->middleware('permission:read.template');
        Route::get('/{id}', [TemplateController::class, 'show'])->middleware('permission:read.template');
        Route::post('/', [TemplateController::class, 'store'])->middleware('permission:create.template');
        Route::patch('/{id}', [TemplateController::class, 'update'])->middleware('permission:update.template');
        Route::delete('/{id}', [TemplateController::class, 'destroy'])->middleware('permission:delete.template');
        Route::patch('/{id}/toggle-status', [TemplateController::class, 'toggleStatus'])->middleware('permission:update.template');
    });

    // Field Definitions
    Route::prefix('field-definitions')->group(function () {
        Route::get('/', [FieldDefinitionController::class, 'index']);
        Route::post('/', [FieldDefinitionController::class, 'store']);
        Route::patch('/{id}', [FieldDefinitionController::class, 'update']);
        Route::delete('/{id}', [FieldDefinitionController::class, 'destroy']);
    });
});
