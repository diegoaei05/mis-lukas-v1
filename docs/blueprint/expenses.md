# Expenses

How a signed-in user records a purchase and sees it in the spreadsheet table. This is the path behind `US-EXP-01`.

The shared shape of a screen is in [blueprint.md](../blueprint.md). Tipo comes from [expense types](expense-types.md). The category and subcategory come from [categories](categories.md).

```text
GET /expenses
  auth, verified
  ExpenseController::index
    the user's expenses, newest first
    each row carries the month name, year, type acronym, concept, category name, subcategory name, and the amount under soles or dollars
    the user's expense types and categories, for the form
    Inertia::render('expenses/index', { expenses, expenseTypes, categories, months, currencies })
      resources/js/pages/expenses/index.tsx
```

The table columns are Mes, Tipo, Concepto, Categoría, Subcategoría, Soles, and Dólares. Mes shows the Spanish month and the year. Tipo shows the acronym. The amount sits under Soles or under Dólares, and the other column is empty.

The form above the table asks for a month, a year, an expense type, a concept, a category, a subcategory of that category, a currency, and one amount. It is shown once the user has at least one expense type and one category.

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
    the expense type and the category must belong to this user, or the response is 404
    the subcategory must belong to this user, or the response is 404
    the subcategory must belong to the selected category
    INSERT INTO expenses
```

The form posts through Wayfinder and uses the `createExpense` error bag. A successful create redirects to `expenses.index` and flashes a toast.

`month`, `year`, `concept`, `currency`, and `amount` are the only fillable attributes. `user_id`, `expense_type_id`, and `subcategory_id` are set from the signed-in user and the chosen type and subcategory, so a submitted `user_id` is ignored. The category is not stored again, because the subcategory already belongs to one category. Two rows may share a concept.

Deleting the user deletes their expenses first, and then their expense types and categories. An expense type or a subcategory that still has expenses cannot be deleted.

| Table | Columns written by this module |
| --- | --- |
| `expenses` | `user_id`, `expense_type_id`, `subcategory_id`, `month`, `year`, `concept`, `currency`, `amount` |
