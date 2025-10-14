<?php namespace Farforrent\Catalog\Models;

use Model;

/**
 * Deposit Model
 * Handles security deposits for rentals
 */
class Deposit extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'farforrent_catalog_deposits';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'order_id',
        'amount',
        'held_at',
        'refunded_at',
        'status',
        'notes'
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'order_id' => 'required|integer',
        'amount' => 'required|numeric|min:0',
        'status' => 'required|in:held,refunded,applied'
    ];

    /**
     * @var array Attributes that should be cast to dates
     */
    protected $dates = ['held_at', 'refunded_at', 'created_at', 'updated_at'];

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
