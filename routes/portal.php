<?php

use App\Http\Controllers\Portal\AdminController;
use App\Http\Controllers\Portal\AuditController;
use App\Http\Controllers\Portal\AutomationController;
use App\Http\Controllers\Portal\CalendarController;
use App\Http\Controllers\Portal\CaseController;
use App\Http\Controllers\Portal\CommunicationController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DocumentController;
use App\Http\Controllers\Portal\LeadController;
use App\Http\Controllers\Portal\PaymentController;
use App\Http\Controllers\Portal\PrintCenterController;
use App\Http\Controllers\Portal\ReportController;
use App\Http\Controllers\Portal\SearchController;
use App\Http\Controllers\Portal\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/search', SearchController::class)->name('search');

Route::middleware('permission:leads.view')->group(function () {
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->middleware('permission:leads.create')->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->middleware('permission:leads.create')->name('leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->middleware('permission:leads.update')->name('leads.update');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('permission:cases.create')->name('leads.convert');
    Route::post('/leads/{lead}/mark-duplicate', [LeadController::class, 'markDuplicate'])->middleware('permission:leads.update')->name('leads.duplicate');
});

Route::middleware('permission:cases.view')->group(function () {
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [CaseController::class, 'create'])->middleware('permission:cases.create')->name('cases.create');
    Route::post('/cases', [CaseController::class, 'store'])->middleware('permission:cases.create')->name('cases.store');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::put('/cases/{case}', [CaseController::class, 'update'])->middleware('permission:cases.update')->name('cases.update');
    Route::post('/cases/{case}/stage', [CaseController::class, 'changeStage'])->middleware('permission:cases.change_stage')->name('cases.stage');
    Route::post('/cases/{case}/notes', [CaseController::class, 'addNote'])->middleware('permission:notes.manage')->name('cases.notes.store');
    Route::get('/pipeline', [CaseController::class, 'pipeline'])->name('pipeline');
});

Route::middleware('permission:tasks.view')->group(function () {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/create', [TaskController::class, 'create'])->middleware('permission:tasks.manage')->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->middleware('permission:tasks.manage')->name('tasks.store');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->middleware('permission:tasks.manage')->name('tasks.complete');
    Route::get('/calendar', CalendarController::class)->name('calendar');
});

Route::middleware('permission:documents.view')->group(function () {
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->middleware('permission:documents.upload')->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->middleware('permission:documents.download')->name('documents.download');
    Route::post('/documents/{document}/review', [DocumentController::class, 'review'])->middleware('permission:documents.approve')->name('documents.review');
});

Route::middleware('permission:communications.view')->group(function () {
    Route::get('/communications', [CommunicationController::class, 'index'])->name('communications.index');
    Route::get('/communications/compose', [CommunicationController::class, 'compose'])->middleware('permission:communications.send')->name('communications.compose');
    Route::post('/communications', [CommunicationController::class, 'store'])->middleware('permission:communications.send')->name('communications.store');
    Route::post('/communications/{communication}/approve', [CommunicationController::class, 'approve'])->middleware('permission:communications.approve')->name('communications.approve');
    Route::post('/communications/{communication}/send', [CommunicationController::class, 'send'])->middleware('permission:communications.send')->name('communications.send');
});

Route::middleware('permission:automations.view')->group(function () {
    Route::get('/automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::get('/automations/create', [AutomationController::class, 'create'])->middleware('permission:automations.manage')->name('automations.create');
    Route::post('/automations', [AutomationController::class, 'store'])->middleware('permission:automations.manage')->name('automations.store');
    Route::get('/automations/{automation}', [AutomationController::class, 'edit'])->name('automations.edit');
    Route::put('/automations/{automation}', [AutomationController::class, 'update'])->middleware('permission:automations.manage')->name('automations.update');
    Route::post('/automations/{automation}/mode', [AutomationController::class, 'changeMode'])->middleware('permission:automations.approve')->name('automations.mode');
    Route::post('/automations/emergency-stop', [AutomationController::class, 'emergencyStop'])->middleware('permission:automations.approve')->name('automations.stop');
});

Route::middleware('permission:reports.view')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});

Route::middleware('permission:reports.export')->group(function () {
    Route::get('/print', [PrintCenterController::class, 'index'])->name('print.index');
    Route::get('/print/{report}', [PrintCenterController::class, 'show'])->name('print.show');
    Route::get('/print/{report}/export', [PrintCenterController::class, 'export'])->name('print.export');
});

Route::middleware('permission:payments.view')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:payments.manage')->name('payments.store');
});

Route::middleware('permission:admin.settings')->group(function () {
    Route::get('/admin', [AdminController::class, 'settings'])->name('admin.settings');
    Route::post('/admin/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
    Route::get('/admin/users', [AdminController::class, 'users'])->middleware('permission:admin.users')->name('admin.users');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->middleware('permission:admin.users')->name('admin.users.store');
    Route::get('/admin/stages', [AdminController::class, 'stages'])->name('admin.stages');
    Route::post('/admin/stages/{stage}', [AdminController::class, 'updateStage'])->name('admin.stages.update');
    Route::get('/admin/templates', [AdminController::class, 'templates'])->name('admin.templates');
    Route::post('/admin/templates/{template}', [AdminController::class, 'updateTemplate'])->name('admin.templates.update');
    Route::post('/admin/templates/{template}/approve', [AdminController::class, 'approveTemplate'])->middleware('permission:templates.approve')->name('admin.templates.approve');
    Route::get('/admin/retention', [AdminController::class, 'retention'])->middleware('permission:admin.retention')->name('admin.retention');
    Route::post('/admin/retention', [AdminController::class, 'updateRetention'])->middleware('permission:admin.retention')->name('admin.retention.update');
});

Route::get('/audit', [AuditController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');
