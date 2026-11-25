# Dashboard

How a signed-in user sees fixed bills and variable expenses totaled for each month. Purchases are recorded in [expenses](expenses.md). Bills are described in [fixed expenses](fixed-expenses.md).

The shared shape of a screen is in [blueprint.md](../blueprint.md).

```text
GET /dashboard?year={year}
  auth, verified
  DashboardController::index
    the user's fixed bills for that year: the usual amount fills every month, and a payment for that month replaces it
    the user's variable expenses for that year, summed by month and currency
    each section has twelve months, Enero through Diciembre
    a month with nothing in that currency is 0.00
    another user's bills and expenses are left out
    a year outside 2000–2100 falls back to the current year
    Inertia::render('dashboard', { year, fixedMonths, variableMonths })
      resources/js/pages/dashboard.tsx
```

The page opens on the current year. Changing the year and leaving the field reloads that year. Chart.js draws a stacked bar chart for Soles and another for Dólares. Each month is one bar: fixed bills on the bottom, variable expenses on top. Hovering a month shows both amounts. Clicking a month opens `/dashboard/{year}/jan` through `dec`, with Soles and Dólares on the same screen.

```text
GET /dashboard/{year}/{month}
  auth, verified
  month is jan through dec; another value sends the signed-in user to the dashboard
  a year outside 2000–2100 sends the signed-in user to the dashboard
  DashboardController::show
    Soles and Dólares are both included
    fixed bills in each currency for that month, summed by category
    a payment for that month replaces the bill's usual amount
    variable expenses in each currency for that month, summed by category
    an expense with no category is its own slice, with a null category
    another user's bills and expenses are left out
    a slice of 0.00 is left out
    slices are ordered by amount, largest first
    Inertia::render('dashboard/month', { year, month, monthLabel, soles, dollars })
      resources/js/pages/dashboard/month.tsx
```

The month page draws Soles and Dólares. Each currency has two rings, Fijos and Variables. Each slice shows its share of the ring, such as 30.78%. A category with a stored color uses that color. A category with no color gets a separate neutral color. Clicking a category opens its Fijos and Variables subcategories in Soles and Dólares. A slice with no category does not open. A link back to the year sits at the top, then a button for each month.

```text
GET /dashboard/{year}/{month}/{category}
  auth, verified
  month is jan through dec; another value sends the signed-in user to the dashboard
  a year outside 2000–2100 sends the signed-in user to the dashboard
  another user's category sends the signed-in user to the dashboard
  DashboardController::subcategories
    Soles and Dólares are both included
    Fijos and Variables are both included
    fixed: that category's bills in each currency, summed by subcategory
    a payment for that month replaces the bill's usual amount
    variable: that category's expenses in each currency for that month, summed by subcategory
    an expense in the category with no subcategory is its own slice
    another category, month, or user is left out
    a slice of 0.00 is left out
    slices are ordered by amount, largest first
    the user's categories, ordered by name
    Inertia::render('dashboard/subcategories', { year, month, monthLabel, categoryId, category, categories, soles, dollars })
      resources/js/pages/dashboard/subcategories.tsx
```

The subcategory page draws Soles and Dólares. Each currency has two rings, Fijos and Variables. Each slice shows its share. A link back to the month sits at the top, then a button for each of the user's categories.

These screens do not write to the database.
