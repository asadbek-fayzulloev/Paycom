<?php

namespace Asadbek\Paycom\Models;

use Asadbek\Paycom\Exceptions\PaycomException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $table = 'orders';
    /** Order is available for sell, anyone can buy it. */
    const STATUS_MODERATING = 'moderating';
    const STATUS_PROCEED = 'proceed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ACCEPTED = 'accepted';

    const PAID_UZCARD = 'uzcard';
    const PAID_VISA = 'visa';

    public $request_id;
    public $params;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('paycom.table.orders');

    }

    public static function statuses()
    {
        return [
            static::STATUS_MODERATING,
            static::STATUS_PROCEED,
            static::STATUS_REJECTED,
            static::STATUS_ACCEPTED,
        ];
    }

    public function canPay()
    {
        return $this->status == static::STATUS_ACCEPTED && !$this->paid;
    }

    public function canCancelPay()
    {
        return false;
    }

}
