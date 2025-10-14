<?php namespace Farforrent\Catalog\Updates;

use Schema;
use Db;
use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;

class CreateAllCoreTables extends Migration
{
    public function up()
    {
        /* -------------------- 1) CUSTOMERS -------------------- */
        if (!Schema::hasTable('farforrent_customers')) {
            Schema::create('farforrent_customers', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id'); // INT(10/11) UNSIGNED
                $t->string('name', 191)->nullable();
                $t->string('phone', 32)->nullable()->index();
                $t->string('email', 191)->nullable()->index();
                $t->timestamps();
                $t->unique(['phone','email'], 'uniq_ff_customers_phone_email');
            });
        }

        /* -------------------- 2) ORDERS -------------------- */
        Schema::create('farforrent_orders', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');                        // = OC order_id, якщо імпортуємо як 1:1
            $t->unsignedInteger('customer_id')->nullable()->index();

            $t->string('title', 191)->nullable();
            $t->date('date_from')->nullable();
            $t->date('date_to')->nullable();

            $t->enum('status', ['draft','confirmed','picked_up','returned','closed','cancelled'])
              ->default('draft')->index();

            $t->decimal('rent_total', 12, 2)->default(0);
            $t->decimal('deposit_total', 12, 2)->default(0);
            $t->text('note')->nullable();

            // корисні поля для інтеграції/звітів — опційно
            $t->string('source', 32)->default('opencart');
            $t->date('created_local_day')->nullable()->index();
            $t->string('customer_name', 191)->nullable();
            $t->string('customer_phone', 64)->nullable();
            $t->string('customer_email', 191)->nullable();

            $t->timestamps();
        });

        /* -------------------- 3) ORDER ITEMS -------------------- */
        Schema::create('farforrent_order_items', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id');                 // -> orders.id
            $t->unsignedInteger('product_id')->nullable();   // посилання на ваш каталог (якщо є)
            $t->string('sku', 64)->nullable();
            $t->string('name_snapshot', 191)->nullable();

            $t->integer('quantity')->default(0);
            $t->integer('qty_issued')->default(0);
            $t->integer('qty_returned')->default(0);

            $t->decimal('rent_per_day', 12, 2)->default(0);
            $t->decimal('deposit_per_item', 12, 2)->default(0);

            $t->enum('return_status', ['pending','ok','dirty','repairable','lost','broken_irrecoverable','transfer'])
              ->default('pending');

            $t->decimal('unit_price', 12, 2)->nullable();
            $t->decimal('line_total', 12, 2)->nullable();

            $t->timestamps();

            $t->index('order_id', 'idx_ff_items_order');
            $t->index('product_id', 'idx_ff_items_product');
        });

        /* -------------------- 4) PAYMENTS -------------------- */
        Schema::create('farforrent_payments', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id'); // -> orders.id
            $t->string('type', 32);          // cash|card|transfer|refund...
            $t->decimal('amount', 12, 2);
            $t->string('currency', 8)->default('UAH');
            $t->string('method', 64)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->text('meta')->nullable();    // JSON-лайт
            $t->timestamps();

            $t->index('order_id', 'idx_ff_pay_order');
        });

        /* -------------------- 5) FEES (штрафи/послуги) -------------------- */
        Schema::create('farforrent_fees', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id');       // -> orders.id
            $t->unsignedInteger('order_item_id')->nullable(); // -> order_items.id
            $t->string('fee_type', 32);            // clean|repair|loss|late|discount...
            $t->decimal('amount', 12, 2)->default(0);
            $t->text('reason')->nullable();
            $t->timestamps();

            $t->index('order_id', 'idx_ff_fees_order');
            $t->index('order_item_id', 'idx_ff_fees_item');
        });

        /* -------------------- 6) DEPOSITS -------------------- */
        Schema::create('farforrent_deposits', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id'); // -> orders.id
            $t->enum('direction', ['hold','apply','refund'])->default('hold');
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('method', 64)->nullable();
            $t->timestamp('held_at')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();

            $t->index('order_id', 'idx_ff_deposits_order');
        });

        /* -------------------- 7) INVENTORY MOVES -------------------- */
        Schema::create('farforrent_inventory_moves', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('product_id')->nullable();
            $t->unsignedInteger('order_id')->nullable(); // -> orders.id (може бути null)
            $t->enum('kind', ['reserve','issue','return','writeoff','adjust'])->default('reserve');
            $t->integer('qty')->default(0);
            $t->text('note')->nullable();
            $t->timestamps();

            $t->index('product_id', 'idx_ff_inv_product');
            $t->index('order_id', 'idx_ff_inv_order');
        });

        /* -------------------- 8) CALENDAR EVENTS -------------------- */
        Schema::create('farforrent_calendar_events', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id')->nullable(); // -> orders.id
            $t->string('title', 191);
            $t->enum('kind', ['issue','return','other'])->default('other');
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->boolean('all_day')->default(true);
            $t->string('color', 16)->nullable();
            $t->timestamps();

            $t->index('order_id', 'idx_ff_cal_order');
            $t->index(['start_date','end_date'], 'idx_ff_cal_dates');
        });

        /* -------------------- 9) TASKS -------------------- */
        Schema::create('farforrent_tasks', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id')->nullable(); // -> orders.id
            $t->string('title', 191);
            $t->enum('status', ['open','in_progress','done','cancelled'])->default('open')->index();
            $t->timestamp('due_at')->nullable();
            $t->unsignedInteger('assignee_id')->nullable(); // якщо колись буде users
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index('order_id', 'idx_ff_tasks_order');
            $t->index('assignee_id', 'idx_ff_tasks_assignee');
        });

        /* -------------------- 10) DAMAGES -------------------- */
        Schema::create('farforrent_damages', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id');              // -> orders.id
            $t->unsignedInteger('order_item_id')->nullable(); // -> order_items.id
            $t->enum('kind', ['chip','crack','break','loss','other'])->default('other');
            $t->tinyInteger('severity')->default(0);
            $t->decimal('amount', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamp('reported_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();

            $t->index('order_id', 'idx_ff_dmg_order');
            $t->index('order_item_id', 'idx_ff_dmg_item');
        });

        /* -------------------- 11) RETURNS (узагальнений стан повернення) -------------------- */
        Schema::create('farforrent_returns', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id'); // -> orders.id
            $t->enum('status', ['pending','ok','dirty','repairable','lost','broken_irrecoverable','transfer'])
              ->default('pending')->index();
            $t->boolean('is_late')->default(false);
            $t->decimal('fee_clean', 12, 2)->default(0);
            $t->decimal('fee_repair', 12, 2)->default(0);
            $t->decimal('fee_loss', 12, 2)->default(0);
            $t->decimal('fee_late', 12, 2)->default(0);
            $t->text('comment')->nullable();
            $t->timestamps();

            $t->index('order_id', 'idx_ff_returns_order');
        });

        /* -------------------- 12) SYNC STATE -------------------- */
        Schema::create('farforrent_sync_state', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->string('provider', 32);
            $t->string('state_key', 64);
            $t->string('state_value', 191)->nullable(); // або JSON окремо, якщо треба
            $t->timestamps();

            $t->unique(['provider','state_key'], 'uniq_ff_sync_state');
        });

        /* -------------------- 13) ORDER STATUS HISTORY -------------------- */
        Schema::create('farforrent_order_status_history', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->increments('id');
            $t->unsignedInteger('order_id'); // -> orders.id
            $t->string('status', 32);
            $t->text('comment')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index('order_id', 'idx_ff_osh_order');
            $t->index('status', 'idx_ff_osh_status');
        });

        /* --------------- 14) FOREIGN KEYS (окремим блоком) --------------- */
        // orders -> customers
        Db::statement("
            ALTER TABLE farforrent_orders
            ADD CONSTRAINT fk_ff_orders_customer
            FOREIGN KEY (customer_id) REFERENCES farforrent_customers(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        // items -> orders
        Db::statement("
            ALTER TABLE farforrent_order_items
            ADD CONSTRAINT fk_ff_items_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");

        // payments -> orders
        Db::statement("
            ALTER TABLE farforrent_payments
            ADD CONSTRAINT fk_ff_pay_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");

        // fees -> orders, order_items
        Db::statement("
            ALTER TABLE farforrent_fees
            ADD CONSTRAINT fk_ff_fees_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
        Db::statement("
            ALTER TABLE farforrent_fees
            ADD CONSTRAINT fk_ff_fees_item
            FOREIGN KEY (order_item_id) REFERENCES farforrent_order_items(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        // deposits -> orders
        Db::statement("
            ALTER TABLE farforrent_deposits
            ADD CONSTRAINT fk_ff_deposits_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");

        // inventory_moves -> orders (nullable)
        Db::statement("
            ALTER TABLE farforrent_inventory_moves
            ADD CONSTRAINT fk_ff_inv_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        // calendar_events -> orders (nullable)
        Db::statement("
            ALTER TABLE farforrent_calendar_events
            ADD CONSTRAINT fk_ff_cal_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        // tasks -> orders (nullable)
        Db::statement("
            ALTER TABLE farforrent_tasks
            ADD CONSTRAINT fk_ff_tasks_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        // damages -> orders, order_items
        Db::statement("
            ALTER TABLE farforrent_damages
            ADD CONSTRAINT fk_ff_dmg_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
        Db::statement("
            ALTER TABLE farforrent_damages
            ADD CONSTRAINT fk_ff_dmg_item
            FOREIGN KEY (order_item_id) REFERENCES farforrent_order_items(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        // returns -> orders
        Db::statement("
            ALTER TABLE farforrent_returns
            ADD CONSTRAINT fk_ff_returns_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");

        // order_status_history -> orders
        Db::statement("
            ALTER TABLE farforrent_order_status_history
            ADD CONSTRAINT fk_ff_osh_order
            FOREIGN KEY (order_id) REFERENCES farforrent_orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
    }

    public function down()
    {
        // Знімати зовнішні ключі та видаляти таблиці у зворотному порядку
        foreach ([
            'farforrent_order_status_history',
            'farforrent_returns',
            'farforrent_damages',
            'farforrent_tasks',
            'farforrent_calendar_events',
            'farforrent_inventory_moves',
            'farforrent_deposits',
            'farforrent_fees',
            'farforrent_payments',
            'farforrent_order_items',
            'farforrent_orders',
            // customers лишаємо — якщо треба, прибери комент нижче
            // 'farforrent_customers',
        ] as $table) {
            if (Schema::hasTable($table)) {
                // знімаємо FK грубо (на випадок різних назв)
                try { Db::statement("SET FOREIGN_KEY_CHECKS=0"); } catch (\Exception $e) {}
                try { Schema::drop($table); } catch (\Exception $e) {}
                try { Db::statement("SET FOREIGN_KEY_CHECKS=1"); } catch (\Exception $e) {}
            }
        }
    }
}