<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zen_act_user_tags', function (Blueprint $table): void {
            $table->string('viewer_login', 64);
            $table->uuid('act_id');
            $table->string('tag', 30);
            $table->primary(['viewer_login', 'act_id', 'tag']);
            $table->index(['viewer_login', 'tag', 'act_id'], 'zen_act_user_tags_viewer_tag_act');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zen_act_user_tags');
    }
};
