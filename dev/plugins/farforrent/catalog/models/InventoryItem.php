<?php

namespace Farforrent\Rental\Models;

use Model;

class InventoryItem extends Model
{
    public $table = 'farforrent_rental_inventory_items';

    protected $fillable = [
        'variant_id', 'serial_number', 'barcode', 'status', 'warehouse_id',
        'condition_notes', 'last_qc_at', 'qc_status'
    ];

    protected $dates = ['last_qc_at'];

    public $attributes = [
        'status' => 'available',
        'qc_status' => 'ok'
    ];

    public $belongsTo = [
        'variant' => [ProductVariant::class],
        'warehouse' => [Warehouse::class]
    ];

    public $hasMany = [
        'stockMoves' => [StockMove::class]
    ];

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'available' => 'Доступний',
            'reserved' => 'Зарезервований',
            'allocated' => 'Виділений',
            'picked' => 'Зібраний',
            'issued' => 'Виданий (в оренді)',
            'returned_pending_qc' => 'Повернутий, чекає перевірки',
            'cleaning' => 'В чистці',
            'qc_ok' => 'Готовий',
            'damaged_repairable' => 'Пошкоджений (ремонтопридатний)',
            'damaged_irrepairable' => 'Пошкоджений (списання)',
            'lost' => 'Втрачений'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function getQcStatusLabelAttribute()
    {
        $statuses = [
            'ok' => 'ОК',
            'needs_cleaning' => 'Потрібна чистка',
            'minor_damage' => 'Дрібний дефект',
            'major_damage' => 'Критична шкода'
        ];

        return $statuses[$this->qc_status] ?? $this->qc_status;
    }

    public function beforeSave()
    {
        if (!$this->serial_number) {
            $this->serial_number = $this->generateSerialNumber();
        }

        if (!$this->barcode) {
            $this->barcode = $this->variant->barcode . '-' . str_pad($this->id ?: 1, 4, '0', STR_PAD_LEFT);
        }
    }

    protected function generateSerialNumber()
    {
        $prefix = 'FR';
        $year = date('Y');
        $sequence = str_pad((static::whereYear('created_at', $year)->count() + 1), 6, '0', STR_PAD_LEFT);
        
        return $prefix . $year . $sequence;
    }
}