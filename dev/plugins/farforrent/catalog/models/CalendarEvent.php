<?php namespace Farforrent\Catalog\Models;

use Model;

/**
 * CalendarEvent Model
 * Stores calendar events for the manager dashboard
 */
class CalendarEvent extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'farforrent_catalog_calendar_events';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'order_id',
        'title',
        'event_type',
        'start_date',
        'end_date',
        'all_day',
        'color',
        'description'
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'title' => 'required|string|max:255',
        'event_type' => 'required|in:issue,return,booking,other',
        'start_date' => 'required|date'
    ];

    /**
     * @var array Attributes that should be cast to dates
     */
    protected $dates = ['start_date', 'end_date', 'created_at', 'updated_at'];

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
