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
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            // Meta Tags العامة
            $table->string('site_title_ar')->nullable();
            $table->string('site_title_en')->nullable();
            $table->text('meta_description_ar')->nullable();
            $table->text('meta_description_en')->nullable();
            $table->text('meta_keywords_ar')->nullable();
            $table->text('meta_keywords_en')->nullable();
            
            // Open Graph & Social Media
            $table->string('og_title_ar')->nullable();
            $table->string('og_title_en')->nullable();
            $table->text('og_description_ar')->nullable();
            $table->text('og_description_en')->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('og_type')->default('website');
            
            // Twitter Card
            $table->string('twitter_card_type')->default('summary_large_image');
            $table->string('twitter_site')->nullable();
            $table->string('twitter_creator')->nullable();
            
            // Robots & Indexing
            $table->text('robots_txt')->nullable();
            $table->boolean('allow_indexing')->default(true);
            $table->text('custom_meta_tags')->nullable(); // للـ meta tags المخصصة
            
            // Google Analytics & Verification
            $table->string('google_analytics_id')->nullable();
            $table->string('google_site_verification')->nullable();
            $table->string('bing_verification')->nullable();
            
            // Schema.org / Structured Data
            $table->text('organization_schema')->nullable(); // JSON-LD للبيانات المنظمة
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
    }
};
