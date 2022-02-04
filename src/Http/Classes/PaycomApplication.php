<?php
/**
 * Created by PhpStorm.
 * User: irock
 * Date: 07.05.2019
 * Time: 15:36
 */

namespace Asadbek\Paycom\Http\Classes;


use Asadbek\Paycom\Exceptions\PaycomException;
use Asadbek\Paycom\Models\PaycomTransaction;
use Asadbek\Paycom\Helpers\FormatHelper;
use Asadbek\Paycom\Models\Order;
use Illuminate\Support\Facades\DB;

class PaycomApplication
{
    public array $config;
    public PaycomRequest $request;
    public PaycomResponse $response;
    public PaycomMerchant $merchant;

    /**
     * PaycomApplication constructor.
     *
     * @param array $config configuration array with 'merchant_id', 'login', 'key' keys.
     *
     * @throws PaycomException
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->request = new PaycomRequest();
        $this->response = new PaycomResponse($this->request);
        $this->merchant = new PaycomMerchant($this->config);
    }


    /**
     * Authorizes session and handles requests.
     */
    public function run()
    {
        try {
            $this->merchant->Authorize($this->request->id);

            switch ($this->request->method) {
                case 'CheckPerformTransaction':
                    $this->CheckPerformTransaction();
                    break;
                case 'CheckTransaction':
                    $this->CheckTransaction();
                    break;
                case 'CreateTransaction':
                    $this->CreateTransaction();
                    break;
                case 'PerformTransaction':
                    $this->PerformTransaction();
                    break;
                case 'CancelTransaction':
                    $this->CancelTransaction();
                    break;
                case 'ChangePassword':
                    $this->ChangePassword();
                    break;
                case 'GetStatement':
                    $this->GetStatement();
                    break;
                default:
                    $this->response->error(
                        PaycomException::ERROR_METHOD_NOT_FOUND,
                        'Method not found.',
                        $this->request->method
                    );
                    break;
            }
        } catch (PaycomException $exception) {
            $exception->send();
        }
    }

    /**
     * @throws PaycomException
     */
    private function CheckPerformTransaction()
    {
        $this->validateOrder();
        $this->validateTransaction();
        $this->response->send(['allow' => true]);
    }

    /**
     * @throws PaycomException
     */
    private function CheckTransaction()
    {
        $transaction = $this->findTransaction();
        if (!$transaction) {
            $this->response->error(
                PaycomException::ERROR_TRANSACTION_NOT_FOUND,
                'Transaction not found.'
            );
        }

        $this->response->send([
            'create_time' => FormatHelper::datetime2timestamp($transaction->create_time),
            'perform_time' => FormatHelper::datetime2timestamp($transaction->perform_time ?? 0),
            'cancel_time' => FormatHelper::datetime2timestamp($transaction->cancel_time ?? 0),
            'transaction' => $transaction->paycom_transaction_id,
            'state' => $transaction->state,
            'reason' => isset($transaction->reason) ? 1 * $transaction->reason : null,
        ]);
    }

    /**
     * @throws PaycomException
     */
    private function CreateTransaction()
    {
        $this->validateOrder();
        $transaction = $this->findTransaction();
        if ($transaction) {
            if (($transaction->state == PaycomTransaction::STATE_CREATED
                    || $transaction->state == PaycomTransaction::STATE_COMPLETED)
                && $transaction->paycom_transaction_id != $this->request->params['id']) {
                $this->response->error(
                    PaycomException::ERROR_INVALID_ACCOUNT,
                    'There is other active/completed transaction for this order.'
                );
            }
        }

        if ($transaction) {
            if ($transaction->state != PaycomTransaction::STATE_CREATED) {
                $this->response->error(
                    PaycomException::ERROR_COULD_NOT_PERFORM,
                    'Transaction transaction, but is not active.'
                );
            } elseif ($transaction->isExpired()) {
                $transaction->cancel(PaycomTransaction::REASON_CANCELLED_BY_TIMEOUT);
                $this->response->error(
                    PaycomException::ERROR_COULD_NOT_PERFORM,
                    'Transaction is expired.'
                );
            } else {
                $this->response->send([
                    'create_time' => FormatHelper::datetime2timestamp($transaction->create_time),
                    'transaction' => $transaction->paycom_transaction_id,
                    'state' => $transaction->state,
                    'receivers' => $transaction->receivers,
                ]);
            }
        } else {

            if (FormatHelper::timestamp2milliseconds(1 * $this->request->params['time']) - FormatHelper::timestamp(true) >= PaycomTransaction::TIMEOUT) {
                $this->response->error(
                    PaycomException::ERROR_INVALID_ACCOUNT,
                    PaycomException::message(
                        'С даты создания транзакции прошло ' . PaycomTransaction::TIMEOUT . 'мс',
                        'Tranzaksiya yaratilgan sanadan ' . PaycomTransaction::TIMEOUT . 'ms o`tgan',
                        'Since create time of the transaction passed ' . PaycomTransaction::TIMEOUT . 'ms'
                    ),
                    'time'
                );
            }
            DB::beginTransaction();
            try {
                $create_time = FormatHelper::timestamp(true);
                $transaction = new PaycomTransaction();
                $transaction->paycom_transaction_id = $this->request->params['id'];
                $transaction->paycom_time = $this->request->params['time'];
                $transaction->paycom_time_datetime = FormatHelper::timestamp2datetime($this->request->params['time']);
                $transaction->create_time = FormatHelper::timestamp2datetime($create_time);
                $transaction->state = PaycomTransaction::STATE_CREATED;
                $transaction->amount = $this->request->amount;
                $transaction->order_id = $this->request->account('order_id');
                $transaction->save();
            } catch (\Exception $exception){
                DB::rollBack();
                $this->response->error(
                    PaycomException::ERROR_SERVER,
                    PaycomException::message(
                        'Ошибка сервера!',
                        'Serverda xatolik!',
                        'Server error!'
                    ),
                    'error'.$exception->getMessage()
                );
            }
            DB::commit();

            $this->response->send([
                'create_time' => $create_time,
                'transaction' => $transaction->paycom_transaction_id,
                'state' => $transaction->state,
                'receivers' => null,
            ]);
        }
    }


    /**
     * @throws PaycomException
     */
    private function PerformTransaction()
    {
        $transaction = $this->findTransaction();

        if (!$transaction) {
            $this->response->error(PaycomException::ERROR_TRANSACTION_NOT_FOUND, 'Transaction not found.');
        }

        switch ($transaction->state) {
            case PaycomTransaction::STATE_CREATED:
                if ($transaction->isExpired()) {
                    $transaction->cancel(PaycomTransaction::REASON_CANCELLED_BY_TIMEOUT);
                    $this->response->error(
                        PaycomException::ERROR_COULD_NOT_PERFORM,
                        'Transaction is expired.'
                    );
                } else {
                    //todo IMPORTTANT
                    // todo: Mark transaction as completed
                    $perform_time = FormatHelper::timestamp(true);
                    $transaction->state = PaycomTransaction::STATE_COMPLETED;
                    $transaction->perform_time = FormatHelper::timestamp2datetime($perform_time);
                    $transaction->save();


                    $this->response->send([
                        'transaction' => $transaction->paycom_transaction_id,
                        'perform_time' => $perform_time,
                        'state' => $transaction->state,
                    ]);
                }
                break;

            case PaycomTransaction::STATE_COMPLETED: // handle complete transaction
                // todo: If transaction completed, just return it
                $this->response->send([
                    'transaction' => $transaction->paycom_transaction_id,
                    'perform_time' => FormatHelper::datetime2timestamp($transaction->perform_time),
                    'state' => $transaction->state,
                ]);
                break;

            default:
                // unknown situation
                $this->response->error(
                    PaycomException::ERROR_COULD_NOT_PERFORM,
                    'Could not perform this operation.'
                );
                break;
        }
    }


    /**
     * @throws PaycomException
     */
    private function CancelTransaction()
    {
        $transaction = $this->findTransaction();

        if (!$transaction) {
            $this->response->error(PaycomException::ERROR_TRANSACTION_NOT_FOUND, 'Transaction not found.');
        }

        switch ($transaction->state) {
            case PaycomTransaction::STATE_CANCELLED:
            case PaycomTransaction::STATE_CANCELLED_AFTER_COMPLETE:
                $this->response->send([
                    'transaction' => $transaction->paycom_transaction_id,
                    'cancel_time' => FormatHelper::datetime2timestamp($transaction->cancel_time),
                    'state' => $transaction->state,
                ]);
                break;

            case PaycomTransaction::STATE_CREATED:
                $transaction->cancel(1 * $this->request->params['reason']);

                Order::where('id', $transaction->order_id)->update(['paid' => null]);

                $this->response->send([
                    'transaction' => $transaction->paycom_transaction_id,
                    'cancel_time' => FormatHelper::datetime2timestamp($transaction->cancel_time),
                    'state' => $transaction->state,
                ]);
                break;

            case PaycomTransaction::STATE_COMPLETED:
                $order = Order::find($transaction->order_id);
                if ($order->canCancelPay()) {
                    // cancel and change state to cancelled
                    $transaction->cancel(1 * $this->request->params['reason']);
                    // after $transaction->cancel(), cancel_time and state properties populated with data

                    Order::where('id', $transaction->order_id)->update(['paid' => null]);

                    // send response
                    $this->response->send([
                        'transaction' => $transaction->paycom_transaction_id,
                        'cancel_time' => FormatHelper::datetime2timestamp($transaction->cancel_time),
                        'state' => $transaction->state,
                    ]);
                } else {
                    // todo: If cancelling after performing transaction is not possible, then return error -31007
                    $this->response->error(
                        PaycomException::ERROR_COULD_NOT_CANCEL,
                        'Could not cancel transaction. Order is delivered/Service is completed.'
                    );
                }
                break;
        }
    }


    /**
     * @return bool
     * @throws PaycomException
     */
    private function validateOrder()
    {
        if (!isset($this->request->params['amount']) || !is_numeric($this->request->params['amount'])) {
            throw new PaycomException(
                $this->request->id,
                'Incorrect amount.',
                PaycomException::ERROR_INVALID_AMOUNT
            );
        }

        $order = Order::where('id', $this->request->params['account']['order_id'])->first();
        return $order;
        if (!$order || !$order->id) {
            throw new PaycomException(
                $this->request->id,
                PaycomException::message(
                    'Неверный код заказа.',
                    'Harid kodida xatolik.',
                    'Incorrect order code.'
                ),
                PaycomException::ERROR_INVALID_ACCOUNT,
                'order_id'
            );
        }

        if ((100 * ($order->amount)) != (1 * $this->request->params['amount'])) {
            throw new PaycomException(
                $this->request->id,
                'Incorrect amount.',
                PaycomException::ERROR_INVALID_AMOUNT
            );
        }

        if ($order->paid || $order->status != Order::STATUS_ACCEPTED || !$order->canPay()) {
            throw new PaycomException(
                $this->request->id,
                'Order state is invalid.',
                PaycomException::ERROR_COULD_NOT_PERFORM
            );
        }

        return true;
    }


    /**
     * @throws PaycomException
     */
    private function ChangePassword()
    {
        // validate, password is specified, otherwise send error
        if (!isset($this->request->params['password']) || !trim($this->request->params['password'])) {
            $this->response->error(PaycomException::ERROR_INVALID_ACCOUNT, 'New password not specified.', 'password');
        }

        // if current password specified as new, then send error
        if ($this->merchant->config['key'] == $this->request->params['password']) {
            $this->response->error(PaycomException::ERROR_INSUFFICIENT_PRIVILEGE, 'Insufficient privilege. Incorrect new password.');
        }

        // example implementation, that saves new password into file specified in the configuration
//        if (!file_put_contents($this->config['keyFile'], $this->request->params['password'])) {
//            $this->response->error(PaycomException::ERROR_INTERNAL_SYSTEM, 'Internal System Error.');
//        }

        // if control is here, then password is saved into data store
        // send success response
        $this->response->send(['success' => true]);
    }


    /**
     * @throws PaycomException
     */
    private function GetStatement()
    {
        // validate 'from'
        if (!isset($this->request->params['from'])) {
            $this->response->error(PaycomException::ERROR_INVALID_ACCOUNT, 'Incorrect period.', 'from');
        }

        // validate 'to'
        if (!isset($this->request->params['to'])) {
            $this->response->error(PaycomException::ERROR_INVALID_ACCOUNT, 'Incorrect period.', 'to');
        }

        // validate period
        if (1 * $this->request->params['from'] >= 1 * $this->request->params['to']) {
            $this->response->error(PaycomException::ERROR_INVALID_ACCOUNT, 'Incorrect period. (from >= to)', 'from');
        }

        $transactions = $this->report($this->request->params['from'], $this->request->params['to']);

        // send results back
        $this->response->send(['transactions' => $transactions]);
    }

    /**
     * @throws PaycomException
     */
    private function validateTransaction()
    {
        $transaction = $this->findTransaction();
        if ($transaction && ($transaction->state == PaycomTransaction::STATE_CREATED || $transaction->state == PaycomTransaction::STATE_COMPLETED)) {
            $this->response->error(
                PaycomException::ERROR_COULD_NOT_PERFORM,
                'There is other active/completed transaction for this order.'
            );
        }
        return $transaction;
    }

    /**
     * @throws PaycomException
     */
    private function findTransaction()
    {

        if (isset($this->request->params['account'], $this->request->params['account']['order_id'])) {
            $transaction = PaycomTransaction::where('order_id', $this->request->params['account']['order_id'])
                ->whereIn('state', [1, 2])->first();
        } else if (isset($this->request->params['id'])) {
            $transaction = PaycomTransaction::where('paycom_transaction_id', $this->request->params['id'])->first();
        } else {
            throw new PaycomException(
                $this->request->id,
                'Parameter to find a transaction is not specified.',
                PaycomException::ERROR_INTERNAL_SYSTEM
            );
        }
        return $transaction;
    }


    public function report($from_date, $to_date)
    {
        $from_date = FormatHelper::timestamp2datetime($from_date);
        $to_date = FormatHelper::timestamp2datetime($to_date);

        $transactions = PaycomTransaction::whereBetween('paycom_time_datetime', [$from_date, $to_date])->orderBy('paycom_time_datetime')->get();

        $result = [];
        foreach ($transactions as $row) {
            $result[] = [
                'id' => $row['paycom_transaction_id'], // paycom transaction id
                'time' => 1 * $row['paycom_time'], // paycom transaction timestamp as is
                'amount' => 1 * $row['amount'],
                'account' => [
                    'order_id' => 1 * $row['order_id'], // account parameters to identify client/order/service
                    // ... additional parameters may be listed here, which are belongs to the account
                ],
                'create_time' => FormatHelper::datetime2timestamp($row['create_time']),
                'perform_time' => FormatHelper::datetime2timestamp($row['perform_time']),
                'cancel_time' => FormatHelper::datetime2timestamp($row['cancel_time']),
                'transaction' => 1 * $row['id'],
                'state' => 1 * $row['state'],
                'reason' => isset($row['reason']) ? 1 * $row['reason'] : null,
                'receivers' => isset($row['receivers']) ? json_decode($row['receivers'], true) : null,
            ];
        }

        return $result;

    }

}
