<?php

namespace Farforrent\Rental\Models;

use Model;
use Carbon\Carbon;

class Reservation extends Model
{
    public $table = 'farforrent_rental_reservations';

    protected $fillable = [
        'order_id', 'order_item_id', 'variant_id', 'quantity', 
        'date_from', 'date_to', 'status', 'notes'
    ];

    protected $dates = ['date_from', 'date_to'];

    public $attributes = [
        'status' => 'active'
    ];

    public $belongsTo = [
        'order' => [Order::class],
        'orderItem' => [OrderProduct::class, 'key' => 'order_item_id'],
        'variant' => [ProductVariant::class]
    ];

    public function getDaysCountAttribute()
    {
        if ($this->date_from && $this->date_to) {
            return $this->date_from->diffInDays($this->date_to) + 1;
        }
        return 0;
    }

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'active' => 'Активне',
            'fulfilled' => 'Виконане',
            'cancelled' => 'Скасоване',
            'expired' => 'Прострочене'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function isActive()
    {
        return $this->status === 'active' && 
               $this->date_to >= Carbon::now()->startOfDay();
    }

    public function isOverlapping($startDate, $endDate)
    {
        return $this->date_from <= $endDate && $this->date_to >= $startDate;
    }
}