<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    abort(403);
})->where('any', '.*');
