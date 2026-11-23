# Categories

How a signed-in user creates categories and subcategories, and deletes a subcategory after confirming. This is the path behind `US-CAT-01`.

The shared shape of a screen is in [blueprint.md](../blueprint.md).

A signed-in, verified user opens Categories, chooses a category, and works with that category's subcategories in a table.

```text
GET /categories?category={id}
  auth, verified
  CategoryController::index
    the user's categories, with their subcategories, ordered by name
    selectedCategoryId when the query belongs to this user
    Inertia::render('categories/index', { categories, selectedCategoryId })
      resources/js/pages/categories/index.tsx
```

The page puts a category selector above the table. The table lists the chosen category's subcategories. Each row has an X that asks for confirmation, and the subcategory is deleted only after that confirmation. A field under the selector adds a category. A field under the table adds a subcategory to the category currently selected.

```text
POST /categories
  StoreCategoryRequest
    trim the name
    reject a blank name, a name longer than 255 characters, or a name this user already has
    users.categories()->create
      INSERT INTO categories

POST /categories/{category}/subcategories
  StoreSubcategoryRequest
    SubcategoryPolicy: another user's category is a 404
    trim the name
    reject a blank name, a name longer than 255 characters, or a name this category already has
    category.subcategories()->create
      INSERT INTO subcategories

DELETE /categories/{category}/subcategories/{subcategory}
  the subcategory must belong to the category in the URL, or the response is 404
  SubcategoryPolicy: another user's subcategory is a 404
  a subcategory that still has expenses is kept, and the page flashes an error
  DELETE FROM subcategories
```

The forms post through Wayfinder. The category form uses the `createCategory` error bag. The subcategory form uses `subcategory-{id}`. A successful create or delete redirects to `categories.index` with `?category=` so the same category stays selected, and flashes a toast.

`categories.name` is unique per user. `subcategories.name` is unique inside its category, including differences of letter case. `name` is the only fillable attribute, so a submitted `user_id` is ignored and the row is owned by the signed-in user. Deleting a user deletes their categories, and deleting a category deletes its subcategories.

| Table | Columns written by this module |
| --- | --- |
| `categories` | `user_id`, `name` |
| `subcategories` | `category_id`, `name` |
