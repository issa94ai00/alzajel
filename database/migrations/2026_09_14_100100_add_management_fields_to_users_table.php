<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('email_verified_at');
            $table->string('phone', 40)->nullable()->after('status');
            $table->string('employee_no', 40)->nullable()->after('phone');
            $table->text('notes')->nullable()->after('employee_no');

            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');

            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->timestamp('suspended_at')->nullable()->after('last_login_ip');
            $table->string('suspended_reason')->nullable()->after('suspended_at');

            $table->foreignId('created_by')->nullable()->after('suspended_reason')->constrained('users')->nullOnDelete();

            $table->softDeletes();

            $table->unique('employee_no');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropUnique('users_employee_no_unique');
            $table->dropIndex(['status']);

            $table->dropColumn([
                'status',
                'phone',
                'employee_no',
                'notes',
                'must_change_password',
                'password_changed_at',
                'last_login_at',
                'last_login_ip',
                'suspended_at',
                'suspended_reason',
                'created_by',
                'deleted_at',
            ]);
        });
    }
};
