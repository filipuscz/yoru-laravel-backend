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
        Schema::create('tb_examples', function (Blueprint $table) {
            // Identifiers
            $table->id();
            $table->uuid('uuid')->unique();
            $table->ulid('ulid')->unique();

            // Strings and Text
            $table->string('string_column'); // Default VARCHAR(255)
            $table->string('string_with_length', 100);
            $table->char('char_column', 4);
            $table->text('text_column');
            $table->mediumText('medium_text_column');
            $table->longText('long_text_column');

            // Numbers
            $table->integer('integer_column');
            $table->bigInteger('big_integer_column');
            $table->mediumInteger('medium_integer_column');
            $table->smallInteger('small_integer_column');
            $table->tinyInteger('tiny_integer_column');
            $table->unsignedInteger('unsigned_integer_column');

            $table->decimal('decimal_column', 8, 2);
            $table->double('double_column');
            $table->float('float_column');

            // Booleans
            $table->boolean('boolean_column')->default(false);

            // Date and Time
            $table->date('date_column');
            $table->dateTime('datetime_column');
            $table->dateTimeTz('datetime_tz_column');
            $table->time('time_column');
            $table->timeTz('time_tz_column');
            $table->timestamp('timestamp_column')->nullable();
            $table->timestampTz('timestamp_tz_column')->nullable();
            $table->year('year_column');

            // Complex Types
            $table->json('json_column')->nullable();
            $table->jsonb('jsonb_column')->nullable();
            $table->binary('binary_column')->nullable();
            $table->enum('enum_column', ['active', 'inactive', 'pending']);
            // PostgreSQL has no SET type. Members stay a comma-separated string.
            $table->string('set_column');

            // Network
            $table->ipAddress('ip_address_column')->nullable();
            $table->macAddress('mac_address_column')->nullable();

            // WKT text. Native geometry needs the PostGIS extension, which is not part of stock PostgreSQL.
            $table->text('geometry_column')->nullable();
            $table->text('point_column')->nullable();
            $table->text('linestring_column')->nullable();
            $table->text('polygon_column')->nullable();

            // Foreign Keys
            $table->foreignId('user_id')->nullable()->constrained('me_users')->onDelete('cascade');

            // Special
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_examples');
    }
};
