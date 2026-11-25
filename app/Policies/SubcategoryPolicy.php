<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubcategoryPolicy
{
    /**
     * Determine whether the user can add a subcategory to the category.
     */
    public function create(User $user, Category $category): Response
    {
        return $user->id === $category->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the subcategory.
     */
    public function update(User $user, Subcategory $subcategory): Response
    {
        $ownsCategory = $subcategory->category()
            ->where('user_id', $user->id)
            ->exists();

        return $ownsCategory
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the subcategory.
     */
    public function delete(User $user, Subcategory $subcategory): Response
    {
        $ownsCategory = $subcategory->category()
            ->where('user_id', $user->id)
            ->exists();

        return $ownsCategory
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
