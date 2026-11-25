<?php

namespace App\Models;

use App\Enums\CategoryColor;
use Database\Factories\SubcategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property CategoryColor|null $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'color'])]
class Subcategory extends Model
{
    /** @use HasFactory<SubcategoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => CategoryColor::class,
        ];
    }

    /**
     * Get the category that owns the subcategory.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the expenses filed under this subcategory.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the fixed expenses filed under this subcategory.
     */
    public function fixedExpenses(): HasMany
    {
        return $this->hasMany(FixedExpense::class);
    }
}
