<?php
use Illuminate\Support\Facades\Route;
use Modules\Glitter\Workflow\Http\Controllers\Admin\{ExecutionController,WorkflowController,WorkflowSettingsController};
Route::prefix('admin/workflows')->middleware(['auth:sanctum','throttle:600,1'])->name('admin.workflows.')->group(function(){
 Route::get('/',[WorkflowController::class,'index'])->middleware('permission:admin,glitter-workflow.workflows.read')->name('index');
 Route::post('/',[WorkflowController::class,'store'])->middleware('permission:admin,glitter-workflow.workflows.create')->name('store');
 Route::get('/{workflow}',[WorkflowController::class,'show'])->middleware('permission:admin,glitter-workflow.workflows.read')->name('show');
 Route::put('/{workflow}',[WorkflowController::class,'update'])->middleware('permission:admin,glitter-workflow.workflows.update')->name('update');
 Route::patch('/{workflow}',[WorkflowController::class,'update'])->middleware('permission:admin,glitter-workflow.workflows.update')->name('patch');
 Route::delete('/{workflow}',[WorkflowController::class,'destroy'])->middleware('permission:admin,glitter-workflow.workflows.delete')->name('destroy');
 Route::post('/{workflow}/activate',[WorkflowController::class,'activate'])->middleware('permission:admin,glitter-workflow.workflows.activate')->name('activate');
 Route::post('/{workflow}/deactivate',[WorkflowController::class,'deactivate'])->middleware('permission:admin,glitter-workflow.workflows.activate')->name('deactivate');
});
Route::prefix('admin/executions')->middleware(['auth:sanctum','throttle:600,1'])->name('admin.executions.')->group(function(){
 Route::get('/',[ExecutionController::class,'index'])->middleware('permission:admin,glitter-workflow.executions.read')->name('index');
 Route::get('/{execution}',[ExecutionController::class,'show'])->middleware('permission:admin,glitter-workflow.executions.read')->name('show');
});
Route::get('admin/settings',[WorkflowSettingsController::class,'show'])
 ->middleware(['auth:sanctum','throttle:600,1','permission:admin,glitter-workflow.settings.read'])
 ->name('admin.settings.show');
Route::put('admin/settings',[WorkflowSettingsController::class,'update'])
 ->middleware(['auth:sanctum','throttle:600,1','permission:admin,glitter-workflow.settings.update'])
 ->name('admin.settings.update');
