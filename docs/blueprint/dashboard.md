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

The page opens on the current year. Changing the year and leaving the field reloads that year. Chart.js draws a stacked bar chart for Soles and another for Dólares. Each month is one bar: fixed bills on the bottom, variable expenses on top. Hovering a month shows both amounts.

This screen does not write to the database.
