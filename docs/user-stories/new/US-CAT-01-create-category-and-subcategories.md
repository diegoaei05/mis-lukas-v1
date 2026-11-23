# US-CAT-01 — Create a category and its subcategories

**Status:** Needs confirmation

**Your words:**

> For the first user-story I would like to create a category so we need a module called categories. In that module I should be able to create the subcategories for that respective category.

**Restatement:** As a signed-in user, I want a Categories area where I can create a category and then create subcategories that belong to that category, so that later expenses can be filed the way the yogurt example is filed under Food and Super/Market.

Creating a subcategory always happens on a category that already exists. A subcategory does not stand on its own.

**Assumptions:**

- Only a signed-in user with a verified email can open Categories or create anything there. Guests are sent to the login page.
- Categories belong to the user who created them. Another account does not see them and cannot add subcategories to them.
- A category and a subcategory each have a name and nothing else yet.
- Category names are unique for that user. Subcategory names are unique inside their category, so two categories may both have a subcategory with the same name.
- A blank name, or a name that repeats those rules, stays on the form with an error and nothing is saved.
- This story does not include renaming, deleting, reordering, or recording an expense. Food and Super/Market are not seeded. You create them yourself if you want that example.

**Plan of action:**

1. Add a `categories` table (`user_id`, `name`) and a `subcategories` table (`category_id`, `name`), with the uniqueness rules above. `Category` belongs to the user and has many subcategories. `Subcategory` belongs to one category.
2. Behind the `auth` and `verified` middleware, add `GET /categories` to show the module, `POST /categories` to create a category, and `POST /categories/{category}/subcategories` to create a subcategory on that category. A form request validates the name. A policy rejects a subcategory create when the category belongs to someone else.
3. Add an Inertia page at `resources/js/pages/categories/index.tsx`, linked from the main navigation next to Dashboard. The page lists the user's categories with the subcategories under each one. One Wayfinder form creates a category. Each category has its own Wayfinder form to add a subcategory.
4. Add a Pest feature test that covers the acceptance criteria: a guest cannot open the page, a user can create a category and a subcategory under it, a user cannot add a subcategory to someone else's category, and invalid names are rejected.

**Acceptance:**

- A signed-in, verified user can open Categories from the main navigation.
- They can create a category by name, and it appears in their list.
- They can create a subcategory by name on one of their categories, and it appears under that category only.
- A subcategory cannot be created without a category, and it cannot be attached to another user's category.
- A guest who opens Categories is redirected to login.
- An empty name, a duplicate category name for that user, or a duplicate subcategory name inside the same category is shown on the form and is not saved.
