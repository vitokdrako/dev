<?php namespace Farforrent\Catalog\Models;

use Model;

/**
 * ReturnSheet Model
 * Tracks item returns and their condition
 */
class ReturnSheet extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'farforrent_catalog_return_sheets';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'order_id',
        'returned_at',
        'checked_by',
        'notes',
        'items_condition',
        'has_damages',
        'status'
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'order_id' => 'required|integer',
        'returned_at' => 'required|date'
    ];

    /**
     * @var array Attributes that should be cast to dates
     */
    protected $dates = ['returned_at', 'created_at', 'updated_at'];

    /**
     * @var array JSON encoded attributes
     */
    protected $jsonable = ['items_condition'];

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
