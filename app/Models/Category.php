<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'description', 'parent_id', 'status'];

    // Relationship for parent category
    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Relationship for child categories
    public function childCategories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
