<?php namespace Farforrent\Catalog\Components;

use Cms\Classes\ComponentBase;
use Farforrent\Catalog\Models\Order;
use Farforrent\Catalog\Models\OrderProduct;
use Farforrent\Catalog\Models\Product;
use Session;
use Validator;
use Carbon\Carbon;

class Checkout extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Оформлення замовлення',
            'description' => 'Форма оформлення замовлення'
        ];
    }

    public function onRun()
    {
        $this->cart = Session::get('rental_cart', []);
        $this->cartTotal = $this->calculateCartTotal();
    }

    public function onCreateOrder()
    {
        $data = post();
        
        // Валідація
        $validator = Validator::make($data, [
            'customer_firstname' => 'required|max:255',
            'customer_lastname' => 'required|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|max:20',
            'rental_start_date' => 'required|date|after:today',
            'rental_end_date' => 'required|date|after:rental_start_date',
            'delivery_address' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            throw new \ValidationException($validator);
        }

        $cart = Session::get('rental_cart', []);
        if (empty($cart)) {
            return ['error' => 'Кошик порожній'];
        }

        // Розрахунок днів та загальної суми
        $startDate = Carbon::parse($data['rental_start_date']);
        $endDate = Carbon::parse($data['rental_end_date']);
        $rentalDays = $startDate->diffInDays($endDate) + 1;
        
        $total = $this->calculateCartTotal($rentalDays);

        // Створення замовлення
        $order = Order::create([
            'customer_firstname' => $data['customer_firstname'],
            'customer_lastname' => $data['customer_lastname'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'],
            'rental_start_date' => $startDate,
            'rental_end_date' => $endDate,
            'rental_days' => $rentalDays,
            'delivery_address' => $data['delivery_address'] ?? null,
            'comment' => $data['comment'] ?? null,
            'total' => $total,
            'status' => 'pending',
            'payment_status' => 'pending'
        ]);

        // Додавання товарів до замовлення
        foreach ($cart as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_model' => $product->model,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'total' => $product->price * $item['quantity'] * $rentalDays,
                    'rental_days' => $rentalDays
                ]);

                // Зменшити кількість товару
                $product->quantity -= $item['quantity'];
                $product->save();
            }
        }

        // Очистити кошик
        Session::forget('rental_cart');

        return [
            'success' => true,
            'message' => 'Замовлення успішно створено!',
            'order_number' => $order->order_number,
            'redirect' => '/order-success?order=' . $order->order_number
        ];
    }

    protected function calculateCartTotal($rentalDays = null)
    {
        $cart = Session::get('rental_cart', []);
        $total = 0;
        
        foreach ($cart as $item) {
            $days = $rentalDays ?? $item['rental_days'];
            $total += $item['price'] * $item['quantity'] * $days;
        }
        
        return $total;
    }
}