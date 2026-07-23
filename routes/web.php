<?php

use App\Http\Controllers\Site\InquiryController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PublicUploadController;
use App\Http\Controllers\Site\VerifyLetterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/how-surplus-funds-work', [PageController::class, 'howItWorks'])->name('site.how-it-works');
Route::get('/our-process', [PageController::class, 'process'])->name('site.process');
Route::get('/faq', [PageController::class, 'faq'])->name('site.faq');
Route::get('/about', [PageController::class, 'about'])->name('site.about');
Route::get('/why-we-contacted-you', [PageController::class, 'whyContacted'])->name('site.why-contacted');
Route::get('/schedule-a-call', [PageController::class, 'schedule'])->name('site.schedule');
Route::get('/contact', [PageController::class, 'contact'])->name('site.contact');
Route::post('/contact', [PageController::class, 'contactSubmit'])->middleware('throttle:5,1')->name('site.contact.submit');
Route::get('/professional-referrals', [PageController::class, 'referrals'])->name('site.referrals');
Route::get('/accessibility', [PageController::class, 'accessibility'])->name('site.accessibility');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('site.privacy');
Route::get('/terms-of-use', [PageController::class, 'terms'])->name('site.terms');
Route::get('/legal-disclaimer', [PageController::class, 'disclaimer'])->name('site.disclaimer');
Route::get('/electronic-communications-consent', [PageController::class, 'eConsent'])->name('site.e-consent');
Route::get('/sms-terms', [PageController::class, 'smsTerms'])->name('site.sms-terms');
Route::get('/document-security', [PageController::class, 'documentSecurity'])->name('site.document-security');
Route::get('/scam-awareness', [PageController::class, 'scamAwareness'])->name('site.scam-awareness');

Route::get('/check-for-possible-funds', [InquiryController::class, 'create'])->name('site.check');
Route::post('/check-for-possible-funds', [InquiryController::class, 'store'])->middleware('throttle:5,1')->name('site.check.store');

Route::get('/verify-a-letter', [VerifyLetterController::class, 'show'])->name('site.verify');
Route::post('/verify-a-letter', [VerifyLetterController::class, 'check'])->middleware('throttle:10,1')->name('site.verify.check');

Route::get('/upload-documents', [PublicUploadController::class, 'show'])->name('site.upload');
Route::post('/upload-documents/verify', [PublicUploadController::class, 'verify'])->middleware('throttle:10,1')->name('site.upload.verify');
Route::post('/upload-documents', [PublicUploadController::class, 'store'])->middleware('throttle:10,1')->name('site.upload.store');
