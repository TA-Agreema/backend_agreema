<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-contract/{id}', function ($id) {
    $contract = \App\Models\Contract::find($id);
    dd([
        'signed_document_path' => $contract->signed_document_path,
        'document_path'        => $contract->document_path,
        'full_path'            => storage_path('app/public/' . $contract->signed_document_path),
        'file_exists'          => file_exists(storage_path('app/public/' . $contract->signed_document_path)),
    ]);
});
