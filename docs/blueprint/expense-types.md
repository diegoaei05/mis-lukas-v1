# Expense Types

How a signed-in user creates the acronym and description behind Tipo. This is the path behind `US-TYP-01`.

The shared shape of a screen is in [blueprint.md](../blueprint.md).

`IO` / `IO credit card` and `EF` / `Efectivo` are examples of types a user would add. The app does not create them.

```text
GET /expense-types
  auth, verified
  ExpenseTypeController::index
    the user's expense types, ordered by acronym
    Inertia::render('expense-types/index', { expenseTypes })
      resources/js/pages/expense-types/index.tsx
```

The page lists acronym and description in a table. A form above the table adds a type.

```text
POST /expense-types
  StoreExpenseTypeRequest
    trim the acronym and the description
    reject a blank acronym, a blank description, an acronym longer than 20 characters, or a description longer than 255 characters
    reject an acronym this user already has, including a difference of letter case
    users.expenseTypes()->create
      INSERT INTO expense_types
```

The form posts through Wayfinder and uses the `createExpenseType` error bag. A successful create redirects to `expense-types.index` and flashes a toast.

`acronym` and `description` are the only fillable attributes, so a submitted `user_id` is ignored and the row is owned by the signed-in user. The same acronym is allowed on another user's account. Deleting a user deletes their expense types.

| Table           | Columns written by this module      |
| --------------- | ----------------------------------- |
| `expense_types` | `user_id`, `acronym`, `description` |
