# Expenses

How a signed-in user records a purchase and sees it in the spreadsheet table. This is the path behind `US-EXP-01`.

The shared shape of a screen is in [blueprint.md](../blueprint.md). Tipo comes from [expense types](expense-types.md). The category and subcategory come from [categories](categories.md).

```text
GET /expenses
  auth, verified
  ExpenseController::index
    the selected year, defaulting to the current year when the request is outside 2000 through 2100
    year buttons for the current year, the selected year and its neighbors, and every year that already has an expense
    the user's expenses for that year, newest first
    each row carries the month name and value, year, type acronym and id, concept, category name or null, category id or null, subcategory name or null, subcategory id or null, currency, amount, and the amount under soles or dollars
    the user's expense types and categories, for the form
    Inertia::render('expenses/index', { year, years, expenses, expenseTypes, categories, months, currencies })
      resources/js/pages/expenses/index.tsx
```

Year buttons sit under the introduction. The selected year filters the table, and a new expense is saved in that year. The table columns are Mes, Tipo, Concepto, Categoría, Subcategoría, Soles, Dólares, and an Edit action. Mes shows the Spanish month. Tipo shows the acronym. A missing category or subcategory is a red `-` in that column. The amount sits under Soles or under Dólares, and the other column is empty. Edit turns the row into fields for the month, type, concept, category, subcategory, and the amount. The type list shows the acronym. The amount is typed in the Soles field or the Dólares field. Both fields stay open. A green check writes the row. A red X closes it. The columns keep the same width. The form above the table stays for new expenses.

The form puts concept and amount first so Tab moves from concept to amount and Enter saves the row. Month, type, and currency stay filled for the next row. The year comes from the selected year button. Category and subcategory start empty and can be left blank. The form is shown once the user has at least one expense type.

```text
POST /expenses
  StoreExpenseRequest
    trim the concept
    treat a comma decimal such as 47,00 as 47.00
    month is Enero through Diciembre
    year is from 2000 through 2100
    concept is required and at most 255 characters
    currency is pen or usd, one value
    amount is greater than zero, with at most two decimal places
    category and subcategory may both be omitted
    a category may be saved with no subcategory
    a subcategory requires the category it belongs to
    the expense type must belong to this user, or the response is 404
    a submitted category or subcategory must belong to this user, or the response is 404
    the subcategory must belong to the selected category
    INSERT INTO expenses
```

The form posts through Wayfinder and uses the `createExpense` error bag. A successful create redirects to `expenses.index` and flashes a toast.

```text
PUT /expenses/{expense}
  UpdateExpenseRequest
    the expense must belong to this user, or the response is 404
    month, year, concept, currency, and amount follow the same rules as a new expense
    the expense type must belong to this user, or the response is 404
    category and subcategory may both be cleared
    a category may be saved with no subcategory
    a subcategory requires the category it belongs to
    a submitted category or subcategory must belong to this user, or the response is 404
    UPDATE expenses
```

The row posts through Wayfinder and uses the `updateExpense-{id}` error bag. A successful update redirects to `expenses.index` and flashes a toast. Edit is a pencil icon. Delete is a trash icon and asks for confirmation before it removes the row.

```text
DELETE /expenses/{expense}
  the expense must belong to this user, or the response is 404
  DELETE FROM expenses
```

A successful delete redirects to `expenses.index` for that year and flashes a toast.

`month`, `year`, `concept`, `currency`, and `amount` are the only fillable attributes. `user_id`, `expense_type_id`, `category_id`, and `subcategory_id` are set from the signed-in user and the chosen type, category, and subcategory, so a submitted `user_id` is ignored. When a subcategory is chosen, its category is stored as well. Two rows may share a concept.

Deleting the user deletes their expenses first, and then their expense types and categories. An expense type or a subcategory that still has expenses cannot be deleted.

| Table      | Columns written by this module                                                                                  |
| ---------- | --------------------------------------------------------------------------------------------------------------- |
| `expenses` | `user_id`, `expense_type_id`, `category_id`, `subcategory_id`, `month`, `year`, `concept`, `currency`, `amount` |
