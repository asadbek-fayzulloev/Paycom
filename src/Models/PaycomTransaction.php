<?php

namespace Asadbek\Paycom\Models;


use Asadbek\Paycom\Helpers\FormatHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaycomTransaction extends Model
{
    use HasFactory;
    const TIMEOUT = 43200000;

    const STATE_CREATED                  = 1;
    const STATE_COMPLETED                = 2;
    const STATE_CANCELLED                = -1;
    const STATE_CANCELLED_AFTER_COMPLETE = -2;

    const REASON_RECEIVERS_NOT_FOUND         = 1;
    const REASON_PROCESSING_EXECUTION_FAILED = 2;
    const REASON_EXECUTION_FAILED            = 3;
    const REASON_CANCELLED_BY_TIMEOUT        = 4;
    const REASON_FUND_RETURNED               = 5;
    const REASON_UNKNOWN                     = 10;

    protected $guarded = ['id'];
    public $timestamps = false;
    /**
     * @var mixed
     */
    private $paycom_transaction_id;

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function isExpired() :int
    {
        // for example, if transaction is active and passed TIMEOUT milliseconds after its creation, then it is expired
        return $this->state == self::STATE_CREATED && FormatHelper::timestamp(true) - FormatHelper::datetime2timestamp($this->create_time) > self::TIMEOUT;
    }

    public function cancel($reason) :void
    {
        $this->cancel_time = FormatHelper::timestamp2datetime(FormatHelper::timestamp());

        if ($this->state == self::STATE_COMPLETED) {
            // Scenario: CreateTransaction -> PerformTransaction -> CancelTransaction
            $this->state = self::STATE_CANCELLED_AFTER_COMPLETE;
        } else {
            // Scenario: CreateTransaction -> CancelTransaction
            $this->state = self::STATE_CANCELLED;
        }

        $this->reason = $reason;

        $this->save();
    }
}
