import { Head, router } from '@inertiajs/react';
import { Fragment, useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index } from '@/routes/general-report';

type MonthAmount = {
    month: number;
    soles: string | null;
    dollars: string | null;
};

type BillMonth = MonthAmount & {
    paid: boolean;
    changed: boolean;
};

type FixedExpenseBill = {
    id: number;
    name: string;
    months: BillMonth[];
};

type FixedExpenseGroup = {
    category: string;
    bills: FixedExpenseBill[];
    totals: MonthAmount[];
};

type MonthOption = {
    value: number;
    label: string;
};

function formatAmount(amount: string | null): string {
    if (amount === null) {
        return '';
    }

    return Number(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const monthEdge = 'border-r-[1.5px] border-r-zinc-500 dark:border-r-zinc-400';

function amountClass(
    tone: 'bill' | 'total' | 'grand',
    paid = false,
    changed = false,
    monthEnd = false,
): string {
    return cn(
        'border border-border px-2 py-1.5 text-right whitespace-nowrap tabular-nums',
        tone === 'total' && 'bg-[#8f7628] font-semibold text-[#f6ecc8]',
        tone === 'grand' && 'bg-[#6f5b1e] font-semibold text-[#f6ecc8]',
        tone === 'bill' && paid && !changed && 'bg-emerald-600/15',
        tone === 'bill' && changed && 'bg-amber-400/40',
        monthEnd && monthEdge,
    );
}

function showYear(nextYear: string): void {
    router.get(
        index.url({
            query: {
                year: nextYear,
            },
        }),
        {},
        {
            preserveScroll: true,
        },
    );
}

export default function GeneralReportIndex({
    groups,
    totals,
    year,
    months,
}: {
    groups: FixedExpenseGroup[];
    totals: MonthAmount[];
    year: number;
    months: MonthOption[];
}) {
    const [periodYear, setPeriodYear] = useState(String(year));
    const columnCount = 1 + months.length * 2;

    useEffect(() => {
        setPeriodYear(String(year));
    }, [year]);

    return (
        <>
            <Head title="General Report" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        General Report
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Fixed bills for the year, grouped by category. A paid
                        month is green. A month paid for a different amount is
                        highlighted.
                    </p>
                </div>

                <div className="grid max-w-xs gap-2">
                    <Label htmlFor="general-report-year">Year</Label>
                    <Input
                        id="general-report-year"
                        type="number"
                        required
                        min={2000}
                        max={2100}
                        value={periodYear}
                        onChange={(event) => setPeriodYear(event.target.value)}
                        onBlur={() => {
                            const nextYear = Number(periodYear);

                            if (
                                Number.isInteger(nextYear) &&
                                nextYear >= 2000 &&
                                nextYear <= 2100 &&
                                nextYear !== year
                            ) {
                                showYear(String(nextYear));

                                return;
                            }

                            setPeriodYear(String(year));
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.currentTarget.blur();
                            }
                        }}
                    />
                </div>

                <div className="max-h-[70vh] overflow-auto rounded-xl border border-border bg-card">
                    <table className="w-max min-w-full border-separate border-spacing-0 text-sm">
                        <caption className="sr-only">
                            Fixed expenses for {year}
                        </caption>
                        <thead className="sticky top-0 z-20 bg-muted">
                            <tr>
                                <th
                                    rowSpan={2}
                                    className="sticky left-0 z-30 min-w-40 border border-solid border-zinc-300 bg-card px-3 py-2 text-left font-medium shadow-[0_1px_0_0] shadow-zinc-300 dark:border-zinc-600 dark:shadow-zinc-600"
                                >
                                    Fijos
                                </th>
                                {months.map((option) => (
                                    <th
                                        key={option.value}
                                        colSpan={2}
                                        className={cn(
                                            'border border-solid border-zinc-300 bg-muted px-2 py-2 text-center font-semibold dark:border-zinc-600',
                                            option.value !== 12 && monthEdge,
                                        )}
                                    >
                                        {option.label}
                                    </th>
                                ))}
                            </tr>
                            <tr>
                                {months.map((option) => (
                                    <Fragment key={option.value}>
                                        <th className="min-w-20 border border-solid border-zinc-300 bg-muted px-2 py-1 text-center text-xs font-medium shadow-[0_1px_0_0] shadow-zinc-300 dark:border-zinc-600 dark:shadow-zinc-600">
                                            Soles
                                        </th>
                                        <th
                                            className={cn(
                                                'min-w-20 border border-solid border-zinc-300 bg-muted px-2 py-1 text-center text-xs font-medium shadow-[0_1px_0_0] shadow-zinc-300 dark:border-zinc-600 dark:shadow-zinc-600',
                                                option.value !== 12 &&
                                                    monthEdge,
                                            )}
                                        >
                                            Dólares
                                        </th>
                                    </Fragment>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {groups.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={columnCount}
                                        className="border border-border px-4 py-6 text-muted-foreground"
                                    >
                                        No fixed expenses for this year.
                                    </td>
                                </tr>
                            ) : (
                                groups.map((group) => (
                                    <Fragment key={group.category}>
                                        <tr>
                                            <th className="sticky left-0 z-10 border border-border bg-sky-100 px-3 py-1.5 text-left font-semibold text-sky-950 dark:bg-sky-950 dark:text-sky-100">
                                                {group.category}
                                            </th>
                                            {months.map((option) => (
                                                <Fragment key={option.value}>
                                                    <td className="border border-border bg-sky-100 dark:bg-sky-950" />
                                                    <td
                                                        className={cn(
                                                            'border border-border bg-sky-100 dark:bg-sky-950',
                                                            option.value !==
                                                                12 &&
                                                                monthEdge,
                                                        )}
                                                    />
                                                </Fragment>
                                            ))}
                                        </tr>
                                        {group.bills.map((bill) => (
                                            <tr key={bill.id}>
                                                <th className="sticky left-0 z-10 border border-border bg-card px-3 py-1.5 text-left font-medium">
                                                    {bill.name}
                                                </th>
                                                {bill.months.map((cell) => (
                                                    <Fragment key={cell.month}>
                                                        <td
                                                            className={amountClass(
                                                                'bill',
                                                                cell.paid &&
                                                                    cell.soles !==
                                                                        null,
                                                                cell.changed &&
                                                                    cell.soles !==
                                                                        null,
                                                            )}
                                                        >
                                                            {formatAmount(
                                                                cell.soles,
                                                            )}
                                                        </td>
                                                        <td
                                                            className={amountClass(
                                                                'bill',
                                                                cell.paid &&
                                                                    cell.dollars !==
                                                                        null,
                                                                cell.changed &&
                                                                    cell.dollars !==
                                                                        null,
                                                                cell.month !==
                                                                    12,
                                                            )}
                                                        >
                                                            {formatAmount(
                                                                cell.dollars,
                                                            )}
                                                        </td>
                                                    </Fragment>
                                                ))}
                                            </tr>
                                        ))}
                                        <tr>
                                            <th className="sticky left-0 z-10 border border-border bg-[#8f7628] px-3 py-1.5 text-left font-semibold text-[#f6ecc8]">
                                                Total
                                            </th>
                                            {group.totals.map((cell) => (
                                                <Fragment key={cell.month}>
                                                    <td
                                                        className={amountClass(
                                                            'total',
                                                        )}
                                                    >
                                                        {formatAmount(
                                                            cell.soles,
                                                        )}
                                                    </td>
                                                    <td
                                                        className={amountClass(
                                                            'total',
                                                            false,
                                                            false,
                                                            cell.month !== 12,
                                                        )}
                                                    >
                                                        {formatAmount(
                                                            cell.dollars,
                                                        )}
                                                    </td>
                                                </Fragment>
                                            ))}
                                        </tr>
                                    </Fragment>
                                ))
                            )}
                            {groups.length > 0 && (
                                <tr>
                                    <th className="sticky left-0 z-10 border border-border bg-[#6f5b1e] px-3 py-2 text-left font-semibold text-[#f6ecc8]">
                                        Total fijos
                                    </th>
                                    {totals.map((cell) => (
                                        <Fragment key={cell.month}>
                                            <td
                                                className={amountClass('grand')}
                                            >
                                                {formatAmount(cell.soles)}
                                            </td>
                                            <td
                                                className={amountClass(
                                                    'grand',
                                                    false,
                                                    false,
                                                    cell.month !== 12,
                                                )}
                                            >
                                                {formatAmount(cell.dollars)}
                                            </td>
                                        </Fragment>
                                    ))}
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

GeneralReportIndex.layout = {
    breadcrumbs: [
        {
            title: 'General Report',
            href: index(),
        },
    ],
};
