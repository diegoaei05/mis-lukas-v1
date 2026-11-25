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

The page opens on the current year. Changing the year and leaving the field reloads that year. Chart.js draws a stacked bar chart for Soles and another for Dólares. Each month is one bar: fixed bills on the bottom, variable expenses on top. Hovering a month shows both amounts. Clicking a month opens that month in the currency of the chart.

```text
GET /dashboard/{year}/{month}/{currency}
  auth, verified
  currency is pen or usd; another value is a 404
  a month outside 1–12, or a year outside 2000–2100, is a 404
  DashboardController::show
    fixed bills in that currency for that month, summed by category
    a payment for that month replaces the bill's usual amount
    variable expenses in that currency for that month, summed by category
    an expense with no category is its own slice, with a null category
    another user's bills and expenses are left out
    a slice of 0.00 is left out
    slices are ordered by amount, largest first
    Inertia::render('dashboard/month', { year, month, monthLabel, currency, currencyLabel, fixed, variable })
      resources/js/pages/dashboard/month.tsx
```

The month page draws two rings, Fijos and Variables. Each slice shows its share of the ring, such as 30.78%. A category with a stored color uses that color. A category with no color gets a separate neutral color. Clicking a category opens its subcategories. A slice with no category does not open. A link back to the year sits at the top, then a button for each month in that same currency.

```text
GET /dashboard/{year}/{month}/{currency}/{group}/{category}
  auth, verified
  group is fixed or variable; another value is a 404
  currency is pen or usd; another value is a 404
  a month outside 1–12, or a year outside 2000–2100, is a 404
  another user's category is a 404
  DashboardController::subcategories
    fixed: that category's bills in that currency, summed by subcategory
    a payment for that month replaces the bill's usual amount
    variable: that category's expenses in that currency for that month, summed by subcategory
    an expense in the category with no subcategory is its own slice
    another category, currency, month, or user is left out
    a slice of 0.00 is left out
    slices are ordered by amount, largest first
    the user's categories, ordered by name
    Inertia::render('dashboard/subcategories', { year, month, monthLabel, currency, currencyLabel, group, groupLabel, categoryId, category, categories, slices })
      resources/js/pages/dashboard/subcategories.tsx
```

The subcategory page draws one ring. Each slice shows its share. A link back to the month sits at the top, then a button for each of the user's categories in that same group and currency.

These screens do not write to the database.
