<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        $subjectNames = collect(['English', 'Bangla', 'Mathematics', 'Science', 'Programming', 'General'])
            ->merge(DB::table('courses')->distinct()->pluck('subject'))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $name): array => [
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        DB::table('subjects')->insertOrIgnore($subjectNames);

        Schema::create('course_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['subject_id', 'name']);
            $table->unique(['subject_id', 'id']);
        });

        Schema::create('course_batch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('batch_number');
            $table->string('batch_name', 100);
            $table->string('short_code', 30);
            $table->unsignedInteger('capacity');
            $table->boolean('is_latest')->default(false);
            $table->date('start_date');
            $table->timestamps();
            $table->unique(['course_id', 'batch_number']);
            $table->unique(['course_id', 'short_code']);
            $table->unique(['course_id', 'id']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('course_topic_id')->nullable();
            $table->foreign(['subject_id', 'course_topic_id'])
                ->references(['subject_id', 'id'])
                ->on('course_topics')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        DB::table('courses')
            ->select('id', 'subject')
            ->orderBy('id')
            ->chunkById(500, function ($courses): void {
                foreach ($courses as $course) {
                    $subjectId = DB::table('subjects')->where('name', $course->subject)->value('id');

                    DB::table('courses')->where('id', $course->id)->update(['subject_id' => $subjectId]);
                }
            });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('subject');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('course_batch_id')->nullable();
            $table->foreign(['course_id', 'course_batch_id'])
                ->references(['course_id', 'id'])
                ->on('course_batch')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('subject', 100)->default('General');
        });

        DB::table('courses')
            ->leftJoin('subjects', 'subjects.id', '=', 'courses.subject_id')
            ->select('courses.id', 'subjects.name as subject_name')
            ->orderBy('courses.id')
            ->chunkById(500, function ($courses): void {
                foreach ($courses as $course) {
                    DB::table('courses')->where('id', $course->id)->update([
                        'subject' => $course->subject_name ?? 'General',
                    ]);
                }
            }, 'courses.id', 'id');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['course_id', 'course_batch_id']);
            $table->dropColumn('course_batch_id');
        });

        Schema::dropIfExists('course_batch');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['subject_id', 'course_topic_id']);
            $table->dropColumn('course_topic_id');
            $table->dropConstrainedForeignId('subject_id');
        });

        Schema::dropIfExists('course_topics');
        Schema::dropIfExists('subjects');
    }
};
