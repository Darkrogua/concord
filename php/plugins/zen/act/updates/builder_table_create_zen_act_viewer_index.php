<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zen_act_viewer_index', function (Blueprint $table): void {
            $table->uuid('act_id');
            $table->string('viewer_key', 64);
            $table->string('access', 16);
            $table->string('owner_login', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->primary(['act_id', 'viewer_key']);
            $table->index(['viewer_key', 'created_at', 'act_id'], 'zen_act_viewer_index_viewer_created');
            $table->index('owner_login', 'zen_act_viewer_index_owner_login');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zen_act_viewer_index');
    }
};
