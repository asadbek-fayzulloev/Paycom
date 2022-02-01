<?php
/**
 * Created by PhpStorm.
 * User: irock
 * Date: 07.05.2019
 * Time: 15:45
 */

namespace Asadbek\Paycom\Http\Classes;



use Asadbek\Paycom\Exceptions\PaycomException;
class PaycomResponse
{

    protected $request;

    /**
     * Response constructor.
     * @param PaycomRequest $request request object.
     */
    public function __construct(PaycomRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Sends response with the given result and error.
     * @param mixed $result result of the request.
     * @param mixed|null $error error.
     */
    public function send($result, $error = null)
    {
        header('Content-Type: application/json; charset=UTF-8');

        $response['jsonrpc'] = '2.0';
        $response['id']      = $this->request->id;
        $response['result']  = $result;
        $response['error']   = $error;

        echo json_encode($response);
    }

    /**
     * Generates PaycomException exception with given parameters.
     * @param int $code error code.
     * @param string|array $message error message.
     * @param string $data parameter name, that resulted to this error.
     * @throws PaycomException
     */
    public function error($code, $message = null, $data = null)
    {
        throw new PaycomException($this->request->id, $message, $code, $data);
    }
}
