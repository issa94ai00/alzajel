<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحركات النقدية اليدوية على درج الوردية: إيداع وسحب.
 *
 * ليست عمليات صرافة — لا سعر لها ولا عملة مقابلة ولا ربح. هي دخول مالٍ إلى
 * الدرج أو خروجه منه لسبب خارجي (تعزيز من الخزنة، تحويل إلى المالك...).
 * قبلها كان رصيد الدرج يتحرّك بعمليات الصرافة وحدها، فأي مالٍ يدخل أو يخرج
 * خارجها يظهر عند الجرد فرقاً بلا تفسير.
 *
 * جدول واحد بحقل نوع بدل جدولَين: الاتجاه هو الفارق الوحيد بين الحركتين،
 * وكل ما عداه — التدقيق، الحذف الناعم، الربط بالوردية — مشترك.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            // مَن سجّل الحركة — قد لا يكون صاحب الوردية (مشرف يعزّز درج صرّاف).
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('type', 10);
            $table->decimal('amount', 18, 4);
            $table->text('notes')->nullable();

            // تدقيق الحذف — نفس أعمدة العمليات والورديات.
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deletion_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['shift_id', 'currency_id']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_movements');
    }
};
