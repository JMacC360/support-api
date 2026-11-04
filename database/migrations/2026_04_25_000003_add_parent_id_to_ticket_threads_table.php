<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_threads', function (Blueprint $table) {
            $table
                ->foreignId('parent_id')
                ->nullable()
                ->constrained('ticket_threads')
                ->cascadeOnDelete()
                ->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
