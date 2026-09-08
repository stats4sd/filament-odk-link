<?php

use Illuminate\Support\Facades\Route;
use Stats4sd\FilamentOdkLink\Http\Controllers\SubmissionController;

// redirect user from root path to app panel login page
Route::get('/odk/submissions/{submission}/update', [SubmissionController::class, 'update'])->name('submission.update');
