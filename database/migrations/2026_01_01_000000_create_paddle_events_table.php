<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paddle_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('paddle_event_id');
            $table->json('payload');
            $table->timestamps();

            $table->index('event_type');
            $table->unique('paddle_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paddle_events');
    }
};
