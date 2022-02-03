<?php

namespace Asadbek\Paycom\Http\Controller;

use Asadbek\Paycom\Exceptions\PaycomException;
use Asadbek\Paycom\Http\Classes\PaycomApplication;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaycomController extends Controller
{
    /**
     * @throws PaycomException
     */
    public function index()
    {
        $application = new PaycomApplication([
            'merchant_id' => config('paycom.merchant'),
            'login' => config('paycom.login'),
            'key' => config('paycom.key'),
            'key_test' => config('paycom.key_test'),
            'is_test' => config('paycom.is_test'),

        ]);
        $application->run();
    }

}
