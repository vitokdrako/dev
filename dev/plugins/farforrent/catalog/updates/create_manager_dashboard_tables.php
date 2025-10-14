<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateManagerDashboardTables extends Migration
{
    public function up()
    {
        // Payments table
        if (!Schema::hasTable('farforrent_catalog_payments')) {
            Schema::create('farforrent_catalog_payments', function($table) {
                $table->increments('id');
                $table->integer('order_id')->unsigned()->index();
                $table->enum('type', ['rent_payment', 'deposit_hold', 'deposit_refund', 'invoice_payment', 'prepayment'])->index();
                $table->decimal('amount', 10, 2);
                $table->timestamp('paid_at')->index();
                $table->string('payment_method', 50)->nullable();
                $table->text('notes')->nullable();
                $table->integer('manager_id')->unsigned()->nullable();
                $table->timestamps();
            });
        }

        // Fees table
        if (!Schema::hasTable('farforrent_catalog_fees')) {
            Schema::create('farforrent_catalog_fees', function($table) {
                $table->increments('id');
                $table->integer('order_id')->unsigned()->index();
                $table->integer('order_item_id')->unsigned()->nullable()->index();
                $table->enum('fee_type', ['late_fee', 'cleaning_fee', 'repair_fee', 'loss_fee'])->index();
                $table->decimal('amount', 10, 2);
                $table->text('reason')->nullable();
                $table->integer('manager_id')->unsigned()->nullable();
                $table->timestamps();
            });
        }

        // Deposits table
        if (!Schema::hasTable('farforrent_catalog_deposits')) {
            Schema::create('farforrent_catalog_deposits', function($table) {
                $table->increments('id');
                $table->integer('order_id')->unsigned()->index();
                $table->decimal('amount', 10, 2);
                $table->timestamp('held_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->enum('status', ['held', 'refunded', 'applied'])->default('held')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // Damages table
        if (!Schema::hasTable('farforrent_catalog_damages')) {
            Schema::create('farforrent_catalog_damages', function($table) {
                $table->increments('id');
                $table->integer('order_id')->unsigned()->index();
                $table->integer('order_item_id')->unsigned()->nullable()->index();
                $table->enum('type', ['dirty', 'repairable', 'lost', 'broken_irrecoverable', 'late'])->index();
                $table->integer('qty')->default(1);
                $table->decimal('amount', 10, 2);
                $table->timestamp('happened_at')->index();
                $table->text('description')->nullable();
                $table->enum('status', ['pending', 'approved', 'waived'])->default('pending')->index();
                $table->text('photos')->nullable(); // JSON
                $table->integer('manager_id')->unsigned()->nullable();
                $table->timestamps();
            });
        }

        // Tasks table
        if (!Schema::hasTable('farforrent_catalog_tasks')) {
            Schema::create('farforrent_catalog_tasks', function($table) {
                $table->increments('id');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->enum('task_type', ['issue', 'return', 'call', 'other'])->default('other')->index();
                $table->integer('order_id')->unsigned()->nullable()->index();
                $table->timestamp('scheduled_at')->index();
                $table->timestamp('completed_at')->nullable();
                $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending')->index();
                $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
                $table->integer('assigned_to')->unsigned()->nullable();
                $table->timestamps();
            });
        }

        // Calendar Events table
        if (!Schema::hasTable('farforrent_catalog_calendar_events')) {
            Schema::create('farforrent_catalog_calendar_events', function($table) {
                $table->increments('id');
                $table->integer('order_id')->unsigned()->nullable()->index();
                $table->string('title', 255);
                $table->enum('event_type', ['issue', 'return', 'booking', 'other'])->default('booking')->index();
                $table->timestamp('start_date')->index();
                $table->timestamp('end_date')->nullable();
                $table->boolean('all_day')->default(false);
                $table->string('color', 20)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Return Sheets table
        if (!Schema::hasTable('farforrent_catalog_return_sheets')) {
            Schema::create('farforrent_catalog_return_sheets', function($table) {
                $table->increments('id');
                $table->integer('order_id')->unsigned()->index();
                $table->timestamp('returned_at');
                $table->integer('checked_by')->unsigned()->nullable();
                $table->text('notes')->nullable();
                $table->text('items_condition')->nullable(); // JSON
                $table->boolean('has_damages')->default(false);
                $table->enum('status', ['pending', 'completed'])->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('farforrent_catalog_return_sheets');
        Schema::dropIfExists('farforrent_catalog_calendar_events');
        Schema::dropIfExists('farforrent_catalog_tasks');
        Schema::dropIfExists('farforrent_catalog_damages');
        Schema::dropIfExists('farforrent_catalog_deposits');
        Schema::dropIfExists('farforrent_catalog_fees');
        Schema::dropIfExists('farforrent_catalog_payments');
    }
}
