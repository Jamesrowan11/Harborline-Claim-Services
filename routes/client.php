<?php

use App\Http\Controllers\Client\CaseController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
Route::post('/cases/{case}/documents', [CaseController::class, 'uploadDocument'])->middleware('throttle:20,1')->name('cases.documents.store');
Route::get('/cases/{case}/documents/{document}/download', [CaseController::class, 'downloadDocument'])->name('cases.documents.download');
Route::post('/cases/{case}/messages', [CaseController::class, 'sendMessage'])->middleware('throttle:20,1')->name('cases.messages.store');
Route::get('/cases/{case}/agreements/{agreement}', [CaseController::class, 'showAgreement'])->name('cases.agreements.show');
Route::post('/cases/{case}/agreements/{agreement}/sign', [CaseController::class, 'signAgreement'])->middleware('throttle:10,1')->name('cases.agreements.sign');

Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::post('/profile/withdraw-sms', [ProfileController::class, 'withdrawSmsConsent'])->name('profile.withdraw-sms');
Route::post('/profile/stop-contact', [ProfileController::class, 'requestStopContact'])->name('profile.stop-contact');
Route::post('/profile/close-account', [ProfileController::class, 'requestAccountClosure'])->name('profile.close-account');
