<?php


use Asadbek\Paycom\Http\Controller\PaycomController;

Route::group([
    'middleware' => 'web',
    'prefix' => 'paycom',
    'as' => 'paycom.',
    'namespace' => 'Asadbek\Paycom\Http\Controllers'
], function () {
    
    Route::post('paycom', [PaycomController::class,'index'])->name('paycom');
});
