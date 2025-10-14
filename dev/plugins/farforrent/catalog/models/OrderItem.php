<?php namespace Farforrent\Catalog\Models;

use Model;

class OrderItem extends Model
{
    protected $table = 'farforrent_order_items';
    protected $fillable = ['product_id','qty','price','rent','deposit'];

    public $belongsTo = [
        'order'   => [Order::class],
        'product' => [Product::class]
    ];

    public function beforeSave()
    {
        // авто-логіка від групи/товару
        $p = $this->product; if (!$p) return;

        $groupPercent = (int) ($p->group->rent_percent ?? 10);
        $rateMap = ['regular'=>1.0,'rare'=>2.0,'vintage'=>3.0];
        $rate    = $rateMap[$p->group->rarity ?? 'regular'] ?? 1.0;

        $unitPrice = $this->price ?: (int) $p->price;
        $this->rent    = (int) round($unitPrice * ($groupPercent/100)) * (int)$this->qty;
        $this->deposit = (int) round($unitPrice * $rate) * (int)$this->qty;
    }
}
