<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlsforms', function (Blueprint $table) {
            $table->timestamp('draft_snapshot_at')->nullable()->after('draft_needs_update')->comment('The moment the current draft was built from. The draft includes every change saved before this time.');
            $table->timestamp('draft_claimed_at')->nullable()->after('processing')->comment('When the form was claimed for building and deploying a draft. A claim older than the TTL is treated as abandoned.');
        });
    }
};
