<?php namespace Farforrent\Catalog\Models;

use Model;

/**
 * Fee Model
 * Handles late fees, cleaning fees, repair fees, and loss fees
 */
class Fee extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'farforrent_catalog_fees';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'order_id',
        'order_item_id',
        'fee_type',
        'amount',
        'reason',
        'created_at',
        'manager_id'
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'order_id' => 'required|integer',
        'fee_type' => 'required|in:late_fee,cleaning_fee,repair_fee,loss_fee',
        'amount' => 'required|numeric|min:0'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'order' => [
            'Farforrent\Catalog\Models\Order',
            'key' => 'order_id'
        ],
        'orderItem' => [
            'Farforrent\Catalog\Models\OrderItem',
            'key' => 'order_item_id'
        ]
    ];
}
