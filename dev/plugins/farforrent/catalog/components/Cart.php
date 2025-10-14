<?php namespace Farforrent\Catalog\Components;

use Cms\Classes\ComponentBase;
use Farforrent\Catalog\Models\Product;
use Session;

class Cart extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Кошик',
            'description' => 'Кошик для товарів'
        ];
    }

    public function onRun()
    {
        $this->page['cart'] = $this->getCart();
        $this->page['cartTotal'] = $this->getCartTotal();
        $this->page['cartCount'] = $this->getCartCount();
    }

    public function onAddToCart()
    {
        $productId = post('product_id');
        $quantity = post('quantity', 1);
        $rentalDays = post('rental_days', 1);
        
        $product = Product::find($productId);
        if (!$product) {
            return ['error' => 'Товар не знайдено'];
        }

        $cart = Session::get('rental_cart', []);
        
        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $cart[$productId] = [
                'product_id' => $productId,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => $quantity,
                'rental_days' => $rentalDays,
                'image' => $product->featured_image ? $product->featured_image->path : null
            ];
        }

        Session::put('rental_cart', $cart);
        
        $this->page['cart'] = $this->getCart();
        $this->page['cartTotal'] = $this->getCartTotal();
        $this->page['cartCount'] = $this->getCartCount();

        return [
            'success' => true,
            'message' => 'Товар додано до кошика',
            'cartCount' => $this->getCartCount()
        ];
    }

    public function onRemoveFromCart()
    {
        $productId = post('product_id');
        
        $cart = Session::get('rental_cart', []);
        unset($cart[$productId]);
        Session::put('rental_cart', $cart);
        
        $this->page['cart'] = $this->getCart();
        $this->page['cartTotal'] = $this->getCartTotal();
        $this->page['cartCount'] = $this->getCartCount();

        return ['success' => true];
    }

    public function onUpdateCart()
    {
        $productId = post('product_id');
        $quantity = post('quantity', 1);
        
        $cart = Session::get('rental_cart', []);
        
        if (isset($cart[$productId])) {
            if ($quantity > 0) {
                $cart[$productId]['quantity'] = $quantity;
            } else {
                unset($cart[$productId]);
            }
            Session::put('rental_cart', $cart);
        }
        
        $this->page['cart'] = $this->getCart();
        $this->page['cartTotal'] = $this->getCartTotal();
        $this->page['cartCount'] = $this->getCartCount();

        return ['success' => true];
    }

    protected function getCart()
    {
        return Session::get('rental_cart', []);
    }

    protected function getCartTotal()
    {
        $cart = $this->getCart();
        $total = 0;
        
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'] * $item['rental_days'];
        }
        
        return $total;
    }

    protected function getCartCount()
    {
        $cart = $this->getCart();
        return array_sum(array_column($cart, 'quantity'));
    }
}