<?php namespace Farforrent\Catalog\Models;

use Model;

class ProductCache extends Model
{
    use \October\Rain\Database\Traits\Validation;

    protected $table = 'farforrent_products_cache';
    protected $guarded = ['id'];
    
    protected $casts = [
        'base_price' => 'decimal:2',
        'rent_price_per_day' => 'decimal:2',
        'deposit_amount' => 'decimal:2'
    ];
    
    public $rules = [
        'opencart_product_id' => 'required|unique:farforrent_products_cache',
        'name' => 'required',
        'base_price' => 'numeric|min:0'
    ];
    
    // Auto-calculate rental pricing
    public function beforeSave()
    {
        if (empty($this->rent_price_per_day) && $this->base_price > 0) {
            $this->rent_price_per_day = $this->base_price * 0.1; // 10% per day
        }
        
        if (empty($this->deposit_amount) && $this->base_price > 0) {
            $this->deposit_amount = $this->base_price * 0.5; // 50% deposit
        }
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }
    
    // Static method to sync from OpenCart
    public static function syncFromOpenCart($productId, $data)
    {
        return static::updateOrCreate(
            ['opencart_product_id' => $productId],
            [
                'name' => $data['name'] ?? '',
                'article' => $data['model'] ?? '',
                'image' => $data['image'] ?? '',
                'base_price' => $data['price'] ?? 0,
                'stock_quantity' => $data['quantity'] ?? 0,
                'status' => ($data['status'] ?? 1) ? 'active' : 'inactive'
            ]
        );
    }
    
    public function getImageUrl()
    {
        if ($this->image && strpos($this->image, 'http') === 0) {
            return $this->image;
        }
        
        return $this->image ? '/image/' . $this->image : '/assets/img/no-image.png';
    }
    
    public function calculateRentalTotal($quantity, $days)
    {
        return $quantity * $this->rent_price_per_day * $days;
    }
    
    public function calculateDepositTotal($quantity)
    {
        return $quantity * $this->deposit_amount;
    }
}