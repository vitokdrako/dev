<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateRentalSystemTables extends Migration
{
    public function up()
    {
        // Orders table
        Schema::create('farforrent_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // 6055
            $table->integer('opencart_order_id')->nullable();
            $table->string('status')->default('new'); // new, reserved, picked, issued, on_rent, returned, closed, cancelled
            
            // Customer info
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            
            // Dates
            $table->date('issue_date'); // дата видачі
            $table->date('return_date'); // дата повернення
            $table->integer('rental_days'); // кількість днів
            
            // Financial
            $table->decimal('subtotal', 10, 2)->default(0); // сума товарів
            $table->decimal('deposit_total', 10, 2)->default(0); // загальна застава
            $table->decimal('prepaid_amount', 10, 2)->default(0); // передплата
            $table->decimal('discount_percent', 5, 2)->default(0); // знижка %
            $table->decimal('discount_amount', 10, 2)->default(0); // сума знижки
            $table->decimal('damage_amount', 10, 2)->default(0); // сума пошкоджень
            $table->decimal('cleaning_amount', 10, 2)->default(0); // сума чистки
            $table->decimal('total_amount', 10, 2)->default(0); // сума до сплати
            
            // Comments
            $table->text('customer_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->text('warehouse_comment')->nullable();
            
            // Delivery
            $table->string('delivery_type')->default('pickup'); // pickup, delivery
            $table->text('delivery_address')->nullable();
            
            // Tracking
            $table->string('manager_name')->nullable();
            $table->string('warehouse_staff')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            
            $table->timestamps();
        });
        
        // Order Items table
        Schema::create('farforrent_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('farforrent_orders')->onDelete('cascade');
            $table->integer('product_id'); // link to OpenCart products
            $table->string('product_name');
            $table->string('product_article')->nullable();
            $table->string('product_image')->nullable();
            $table->integer('quantity');
            $table->integer('warehouse_stock')->default(0); // наявність на складі
            $table->decimal('rent_price_per_day', 8, 2); // оренда/доба
            $table->decimal('deposit_per_unit', 8, 2); // застава за одиницю
            $table->decimal('item_total', 10, 2); // сума за позицію
            $table->decimal('deposit_total', 10, 2); // загальна застава за позицію
            $table->string('status')->default('reserved'); // reserved, picked, issued, returned
            $table->timestamps();
        });
        
        // Payments table
        Schema::create('farforrent_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('farforrent_orders')->onDelete('cascade');
            $table->string('type'); // prepaid, deposit_hold, deposit_release, damage_charge, cleaning_fee
            $table->decimal('amount', 10, 2);
            $table->string('method')->default('cash'); // cash, card, online
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->string('transaction_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        // Damages table  
        Schema::create('farforrent_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('farforrent_orders')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('farforrent_order_items')->onDelete('cascade');
            $table->string('damage_type'); // broken, dirty, lost, chipped
            $table->string('severity')->default('minor'); // minor, major, total
            $table->integer('quantity_damaged')->default(1);
            $table->decimal('repair_cost', 8, 2)->default(0);
            $table->decimal('cleaning_cost', 8, 2)->default(0);
            $table->json('photos')->nullable(); // array of photo paths
            $table->text('description')->nullable();
            $table->string('resolution')->default('pending'); // pending, repair, replace, charge
            $table->string('reported_by')->nullable();
            $table->timestamps();
        });
        
        // Products cache table (for faster lookups)
        Schema::create('farforrent_products_cache', function (Blueprint $table) {
            $table->id();
            $table->integer('opencart_product_id')->unique();
            $table->string('name');
            $table->string('article')->nullable();
            $table->string('image')->nullable();
            $table->decimal('base_price', 8, 2)->default(0);
            $table->decimal('rent_price_per_day', 8, 2)->default(0); // 10% от base_price
            $table->decimal('deposit_amount', 8, 2)->default(0); // 50% от base_price
            $table->integer('stock_quantity')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('farforrent_damages');
        Schema::dropIfExists('farforrent_payments');
        Schema::dropIfExists('farforrent_order_items');
        Schema::dropIfExists('farforrent_orders');
        Schema::dropIfExists('farforrent_products_cache');
    }
}