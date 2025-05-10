<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1️⃣ Add the new column as nullable first
        Schema::table('books', function (Blueprint $table) {
            if (!Schema::hasColumn('books', 'author_id')) {
                $table->unsignedBigInteger('author_id')->nullable()->after('id');
            }
        });

        // 2️⃣ Only run this logic if 'author' column exists
        if (Schema::hasColumn('books', 'author')) {
            $authors = DB::table('books')->select('author')->distinct()->pluck('author');

            foreach ($authors as $authorName) {
                // Insert author if it doesn't already exist
                $authorId = DB::table('authors')->where('name', $authorName)->value('id');
                if (!$authorId) {
                    $authorId = DB::table('authors')->insertGetId(['name' => $authorName]);
                }

                // Update books with this author's ID
                DB::table('books')->where('author', $authorName)->update(['author_id' => $authorId]);
            }
        }

        // 3️⃣ Make the column NOT NULL
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedBigInteger('author_id')->nullable(false)->change();
        });

        // 4️⃣ Add the foreign key constraint
        Schema::table('books', function (Blueprint $table) {
            $table->foreign('author_id')->references('id')->on('authors')->onDelete('cascade');
        });

        // 5️⃣ Drop the old string column
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'author')) {
                $table->dropColumn('author');
            }
        });
    }

    public function down()
    {
        // Add back the old author column
        Schema::table('books', function (Blueprint $table) {
            $table->string('author');
        });

        // Restore author names based on author_id
        $books = DB::table('books')->get(['id', 'author_id']);
        foreach ($books as $book) {
            $authorName = DB::table('authors')->where('id', $book->author_id)->value('name');
            DB::table('books')->where('id', $book->id)->update(['author' => $authorName]);
        }

        // Drop the foreign key and author_id column
        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn('author_id');
        });
    }
};
