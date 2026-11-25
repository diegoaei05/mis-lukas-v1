import { Head, router } from '@inertiajs/react';
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import { useEffect, useState } from 'react';
import { Bar } from 'react-chartjs-2';
import { show } from '@/actions/App/Http/Controllers/DashboardController';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard } from '@/routes';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

type MonthTotal = {
    month: number;
    label: string;
    soles: string;
    dollars: string;
};

function formatAmount(amount: number): string {
    return amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function StackedCurrencyChart({ title, year, currency, fixedMonths, variableMonths }: { title: string; year: number; currency: 'soles' | 'dollars'; fixedMonths: MonthTotal[]; variableMonths: MonthTotal[] }) {
    const { resolvedAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const text = isDark ? '#e4e4e7' : '#3f3f46';
    const grid = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.08)';
    const fixed = fixedMonths.map((month) => Number(month[currency]));
    const variables = variableMonths.map((month) => Number(month[currency]));
    const hasExpenses = [...fixed, ...variables].some((amount) => amount > 0);

    return (
        <section className="rounded-xl border border-border bg-card p-4">
            <h3 className="mb-4 text-base font-semibold">{title}</h3>

            {!hasExpenses && <p className="mb-4 text-sm text-muted-foreground">No {title.toLowerCase()} for this year.</p>}

            <div className="h-80">
                <Bar
                    data={{
                        labels: fixedMonths.map((month) => month.label.slice(0, 3)),
                        datasets: [
                            {
                                label: 'Fijos',
                                data: fixed,
                                backgroundColor: '#4C78A8',
                            },
                            {
                                label: 'Variables',
                                data: variables,
                                backgroundColor: '#59A14F',
                            },
                        ],
                    }}
                    options={{
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                labels: { color: text },
                            },
                            tooltip: {
                                callbacks: {
                                    title: (items) => fixedMonths[items[0]?.dataIndex ?? 0]?.label ?? '',
                                    label: (item) => `${item.dataset.label}: ${formatAmount(Number(item.raw))}`,
                                },
                            },
                        },
                        onClick: (_event, elements) => {
                            const month = fixedMonths[elements[0]?.index ?? -1]?.month;

                            if (month === undefined) {
                                return;
                            }

                            const slug = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'][month - 1];

                            if (slug === undefined) {
                                return;
                            }

                            router.get(show.url({ year, month: slug }));
                        },
                        onHover: (event, elements) => {
                            const target = event.native?.target;

                            if (target instanceof HTMLElement) {
                                target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                ticks: { color: text },
                                grid: { color: grid },
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: { color: text },
                                grid: { color: grid },
                            },
                        },
                    }}
                    aria-label={`${title} for ${year}, stacked by fixed and variable expenses`}
                />
            </div>
        </section>
    );
}

function showYear(nextYear: string): void {
    router.get(
        dashboard.url({
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

export default function Dashboard({ year, fixedMonths, variableMonths }: { year: number; fixedMonths: MonthTotal[]; variableMonths: MonthTotal[] }) {
    const [periodYear, setPeriodYear] = useState(String(year));

    useEffect(() => {
        setPeriodYear(String(year));
    }, [year]);

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">Dashboard</h2>
                    <p className="text-sm text-muted-foreground">Each month is one bar. Fixed bills sit on the bottom and variable expenses sit on top. Click a month to see its categories.</p>
                </div>

                <div className="grid max-w-xs gap-2">
                    <Label htmlFor="dashboard-year">Year</Label>
                    <Input
                        id="dashboard-year"
                        type="number"
                        required
                        min={2000}
                        max={2100}
                        value={periodYear}
                        onChange={(event) => setPeriodYear(event.target.value)}
                        onBlur={() => {
                            const nextYear = Number(periodYear);

                            if (Number.isInteger(nextYear) && nextYear >= 2000 && nextYear <= 2100 && nextYear !== year) {
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

                <StackedCurrencyChart title="Soles" year={year} currency="soles" fixedMonths={fixedMonths} variableMonths={variableMonths} />
                <StackedCurrencyChart title="Dólares" year={year} currency="dollars" fixedMonths={fixedMonths} variableMonths={variableMonths} />
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
