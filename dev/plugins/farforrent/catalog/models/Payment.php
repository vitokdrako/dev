<?php namespace Farforrent\Catalog\Models;

use Model;

/**
 * Payment Model
 * Handles rental payments, deposits, and refunds
 */
class Payment extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'farforrent_catalog_payments';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'order_id',
        'type',
        'amount',
        'paid_at',
        'payment_method',
        'notes',
        'manager_id'
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'order_id' => 'required|integer',
        'type' => 'required|in:rent_payment,deposit_hold,deposit_refund,invoice_payment,prepayment',
        'amount' => 'required|numeric|min:0',
        'paid_at' => 'required|date'
    ];

    /**
     * @var array Attributes that should be cast to dates
     */
    protected $dates = ['paid_at', 'created_at', 'updated_at'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'order' => [
            'Farforrent\Catalog\Models\Order',
            'key' => 'order_id'
        ]
    ];
}
