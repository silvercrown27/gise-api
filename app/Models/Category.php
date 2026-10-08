<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\FlushesPublicCache;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes, FlushesPublicCache;

    protected $primaryKey = 'id';
    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'classification',
        'description',
        'parent_category_id',
    ];

    protected $casts = [];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = Str::uuid('id');
            $model->created_at = $model->getDateTime();
            $model->updated_at = $model->getDateTime();
        });

        static::updating(function ($model) {
            $model->updated_at = $model->getDateTime();
        });
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function subCategories()
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'category_id');
    }
}
