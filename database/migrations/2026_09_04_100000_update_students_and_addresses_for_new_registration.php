<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('sex');
            }
            if (!Schema::hasColumn('students', 'grade_level')) {
                $table->string('grade_level')->nullable()->after('educational_level');
            }
            if (!Schema::hasColumn('students', 'occupation_type')) {
                $table->string('occupation_type')->default('student')->after('grade_level');
            }
            if (!Schema::hasColumn('students', 'current_school')) {
                $table->string('current_school')->nullable()->after('occupation_type');
            }
            if (!Schema::hasColumn('students', 'current_office')) {
                $table->string('current_office')->nullable()->after('current_school');
            }
            if (!Schema::hasColumn('students', 'family_guardian_name')) {
                $table->string('family_guardian_name')->nullable()->after('current_office');
            }
            if (!Schema::hasColumn('students', 'family_guardian_phone')) {
                $table->string('family_guardian_phone')->nullable()->after('family_guardian_name');
            }
            if (!Schema::hasColumn('students', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('family_guardian_phone');
            }
            if (!Schema::hasColumn('students', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            }
            if (!Schema::hasColumn('students', 'picture')) {
                $table->string('picture')->nullable()->after('emergency_contact_phone');
            }
            if (!Schema::hasColumn('students', 'birth_certificates')) {
                $table->json('birth_certificates')->nullable()->after('picture');
            }
            if (!Schema::hasColumn('students', 'educational_certificates')) {
                $table->json('educational_certificates')->nullable()->after('birth_certificates');
            }
            if (!Schema::hasColumn('students', 'classification')) {
                $table->string('classification')->nullable()->after('educational_certificates');
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'city')) {
                $table->string('city')->default('Addis Ababa')->after('student_id');
            }
            if (!Schema::hasColumn('addresses', 'woreda')) {
                $table->string('woreda')->nullable()->after('subcity');
            }
            if (!Schema::hasColumn('addresses', 'kebele')) {
                $table->string('kebele')->nullable()->after('woreda');
            }
            if (!Schema::hasColumn('addresses', 'house_no')) {
                $table->string('house_no')->nullable()->after('kebele');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $cols = [
                'birth_date', 'grade_level', 'occupation_type', 'current_school',
                'current_office', 'family_guardian_name', 'family_guardian_phone',
                'emergency_contact_name', 'emergency_contact_phone', 'picture',
                'birth_certificates', 'educational_certificates', 'classification'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('students', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            $cols = ['city', 'woreda', 'kebele', 'house_no'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('addresses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
