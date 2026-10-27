<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdditionalCustomersToDepositRequests extends Migration
{
    public function up()
    {
        Schema::table('unit_deposit_requests', function (Blueprint $table) {
            $table->json('additional_customers')->nullable();
        });
    }

    public function down()
    {
        Schema::table('unit_deposit_requests', function (Blueprint $table) {
            $table->dropColumn('additional_customers');
        });
    }
}
