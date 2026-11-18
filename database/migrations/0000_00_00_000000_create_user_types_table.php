<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User types are a table (not an enum) so new ones can be added from the admin
 * without a deploy. The three core types are system data: users.user_type_id
 * defaults to 1, so they are inserted here rather than in a seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            // Higher level = more power. Access checks compare levels, so a new
            // type slots in between existing ones without code changes.
            $table->unsignedSmallInteger('level')->default(0);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();

        DB::table('user_types')->insert([
            ['id' => 1, 'slug' => 'user', 'name' => 'User', 'level' => 10, 'is_admin' => false, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'slug' => 'admin', 'name' => 'Admin', 'level' => 50, 'is_admin' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'slug' => 'super_admin', 'name' => 'Super admin', 'level' => 100, 'is_admin' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_types');
    }
};
