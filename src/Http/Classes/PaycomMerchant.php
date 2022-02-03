<?php
/**
 * Created by PhpStorm.
 * User: irock
 * Date: 07.05.2019
 * Time: 15:36
 */

namespace Asadbek\Paycom\Http\Classes;


use Asadbek\Paycom\Exceptions\PaycomException;

class PaycomMerchant
{
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param $request_id
     * @return bool
     * @throws PaycomException
     */
    public function Authorize($request_id)
    {
        $key = $this->config["is_test"]=="true" ? $this->config['key_test'] :$this->config['key'];
        $headers = getallheaders();
        if (!$headers || !isset($headers['Authorization']) ||
            !preg_match('/^\s*Basic\s+(\S+)\s*$/i', $headers['Authorization'], $matches) ||
            base64_decode($matches[1]) != $this->config['login'] . ":" . $key
        ) {
            throw new PaycomException(
                $request_id,
                'Insufficient privilege to perform this method.',
                PaycomException::ERROR_INSUFFICIENT_PRIVILEGE
            );
        }

        return true;
    }
}
