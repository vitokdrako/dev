<?php namespace Farforrent\Catalog\Models;

use Model;

/**
 * Task Model
 * Manages daily tasks for the manager dashboard
 */
class Task extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'farforrent_catalog_tasks';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'title',
        'description',
        'task_type',
        'order_id',
        'scheduled_at',
        'completed_at',
        'status',
        'priority',
        'assigned_to'
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'title' => 'required|string|max:255',
        'task_type' => 'required|in:issue,return,call,other',
        'scheduled_at' => 'required|date',
        'status' => 'required|in:pending,completed,cancelled'
    ];

    /**
     * @var array Attributes that should be cast to dates
     */
    protected $dates = ['scheduled_at', 'completed_at', 'created_at', 'updated_at'];

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
