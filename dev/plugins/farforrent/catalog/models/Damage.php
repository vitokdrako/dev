<?php namespace Farforrent\Catalog\Models;

use Model;

/**
 * Damage Model
 * Tracks damaged, dirty, or lost items
 */
class Damage extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'farforrent_catalog_damages';

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
        'type',
        'qty',
        'amount',
        'happened_at',
        'description',
        'status',
        'photos',
        'manager_id'
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'order_id' => 'required|integer',
        'type' => 'required|in:dirty,repairable,lost,broken_irrecoverable,late',
        'amount' => 'required|numeric|min:0'
    ];

    /**
     * @var array Attributes that should be cast to dates
     */
    protected $dates = ['happened_at', 'created_at', 'updated_at'];

    /**
     * @var array JSON encoded attributes
     */
    protected $jsonable = ['photos'];

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
