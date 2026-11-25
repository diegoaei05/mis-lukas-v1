# Categories

How a signed-in user creates, updates, and deletes categories and subcategories. This is the path behind `US-CAT-01`.

The shared shape of a screen is in [blueprint.md](../blueprint.md).

A signed-in, verified user opens Categories, chooses a category, and works with that category's subcategories in a table.

```text
GET /categories?category={id}
  auth, verified
  CategoryController::index
    the user's categories, with their subcategories, ordered by name
    each category carries the color chosen for its pill, or null when none was chosen
    the five pill colors the form can offer
    selectedCategoryId when the query belongs to this user
    Inertia::render('categories/index', { categories, colors, selectedCategoryId })
      resources/js/pages/categories/index.tsx
```

A row above the card adds a category. The name and a color dropdown sit on that same row, with Add category beside them. The card starts with a dropdown that chooses the category, a green pencil, and a red trash icon. The pencil opens the name and color fields for that category. Choosing another category or saving closes those fields. The trash icon asks for confirmation, then deletes the category and its subcategories. A category that still has variable expenses or fixed expenses is kept, and the page flashes an error. A category with no color stays plain text until a color is saved. The table lists the chosen category's subcategories. Each row has a green pencil and a trash icon. The pencil opens a name field for that subcategory. Saving its current name is allowed. A name this category already uses on another subcategory is shown on the row and is not saved. The trash icon asks for confirmation, and the subcategory is deleted only after that confirmation. Creating or updating a category requires one of the five pill colors: green, purple, blue, gold, or red. Saving the current name is allowed. A field under the table adds a subcategory to the category currently selected.

```text
POST /categories
  StoreCategoryRequest
    trim the name
    reject a blank name, a name longer than 255 characters, or a name this user already has
    require a color from the five pill colors
    users.categories()->create
      INSERT INTO categories

PUT /categories/{category}
  UpdateCategoryRequest
    CategoryPolicy: another user's category is a 404
    trim the name
    reject a blank name, a name longer than 255 characters, or a name this user already has on a different category
    require a color from the five pill colors
    category->update
      UPDATE categories

DELETE /categories/{category}
  CategoryPolicy: another user's category is a 404
  a category that still has variable expenses or fixed expenses is kept, and the page flashes an error
  DELETE FROM categories
    subcategories of that category are deleted with it

POST /categories/{category}/subcategories
  StoreSubcategoryRequest
    SubcategoryPolicy: another user's category is a 404
    trim the name
    reject a blank name, a name longer than 255 characters, or a name this category already has
    category.subcategories()->create
      INSERT INTO subcategories

PUT /categories/{category}/subcategories/{subcategory}
  the subcategory must belong to the category in the URL, or the response is 404
  UpdateSubcategoryRequest
    SubcategoryPolicy: another user's subcategory is a 404
    trim the name
    reject a blank name, a name longer than 255 characters, or a name this category already has on a different subcategory
    subcategory->update
      UPDATE subcategories

DELETE /categories/{category}/subcategories/{subcategory}
  the subcategory must belong to the category in the URL, or the response is 404
  SubcategoryPolicy: another user's subcategory is a 404
  a subcategory that still has variable expenses or fixed expenses is kept, and the page flashes an error
  DELETE FROM subcategories
```

The forms post through Wayfinder. The new-category form uses the `createCategory` error bag. The selected category's form uses `updateCategory-{id}`. The new-subcategory form uses `subcategory-{id}`. The subcategory name form uses `updateSubcategory-{id}`. A successful create, update, or subcategory delete redirects to `categories.index` with `?category=` so the same category stays selected, and flashes a toast. Deleting a category redirects to `categories.index` without that category.

`categories.name` is unique per user. `subcategories.name` is unique inside its category, including differences of letter case. `name` and `color` are the fillable attributes, so a submitted `user_id` is ignored and the row is owned by the signed-in user. The same color can be used by more than one category. Deleting a user deletes their categories, and deleting a category deletes its subcategories.

| Table           | Columns written by this module |
| --------------- | ------------------------------ |
| `categories`    | `user_id`, `name`, `color`     |
| `subcategories` | `category_id`, `name`          |
