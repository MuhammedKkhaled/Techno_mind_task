<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        User::whereNull('tenant_id')->each(function (User $user) {
            $tenant = Tenant::create(['name' => "{$user->name}'s Organization"]);

            $user->forceFill(['tenant_id' => $tenant->id])->save();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
