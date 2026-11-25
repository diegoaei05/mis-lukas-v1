import { Head, Link, router } from '@inertiajs/react';
import { show, subcategories } from '@/actions/App/Http/Controllers/DashboardController';
import CategoryRing, { type RingSlice } from '@/components/category-ring';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const months = [
    { value: 'jan', label: 'Ene' },
    { value: 'feb', label: 'Feb' },
    { value: 'mar', label: 'Mar' },
    { value: 'apr', label: 'Abr' },
    { value: 'may', label: 'May' },
    { value: 'jun', label: 'Jun' },
    { value: 'jul', label: 'Jul' },
    { value: 'aug', label: 'Ago' },
    { value: 'sep', label: 'Sep' },
    { value: 'oct', label: 'Oct' },
    { value: 'nov', label: 'Nov' },
    { value: 'dec', label: 'Dic' },
];

type CurrencyCharts = {
    fixed: RingSlice[];
    variable: RingSlice[];
};

function CurrencyCharts({ label, charts, year, month }: { label: string; charts: CurrencyCharts; year: number; month: string }) {
    const openCategory = (slice: RingSlice) => {
        if (slice.id === null) {
            return;
        }

        router.get(subcategories.url({ year, month, category: slice.id }));
    };

    return (
        <section className="flex flex-col gap-4">
            <h3 className="text-lg font-semibold">{label}</h3>
            <div className="grid gap-6 lg:grid-cols-2">
                <CategoryRing title="Fijos" slices={charts.fixed} emptyMessage="No Fijos for this month." onSlice={openCategory} />
                <CategoryRing title="Variables" slices={charts.variable} emptyMessage="No Variables for this month." onSlice={openCategory} />
            </div>
        </section>
    );
}

export default function DashboardMonth({ year, month, monthLabel, soles, dollars }: { year: number; month: string; monthLabel: string; soles: CurrencyCharts; dollars: CurrencyCharts }) {
    return (
        <>
            <Head title={`${monthLabel} ${year}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <TextLink href={dashboard.url({ query: { year } })} className="w-fit text-sm">
                    Back to {year}
                </TextLink>

                <div className="flex flex-wrap gap-2" role="group" aria-label="Month">
                    {months.map((option) => (
                        <Button key={option.value} variant={option.value === month ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={show.url({ year, month: option.value })} preserveScroll aria-current={option.value === month ? 'page' : undefined}>
                                {option.label}
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-xl font-semibold tracking-tight">
                        {monthLabel} {year}
                    </h2>
                    <p className="text-sm text-muted-foreground">Fixed bills and variable expenses, in Soles and Dólares, each split by category. Click a category to see its subcategories.</p>
                </div>

                <CurrencyCharts label="Soles" charts={soles} year={year} month={month} />
                <CurrencyCharts label="Dólares" charts={dollars} year={year} month={month} />
            </div>
        </>
    );
}

DashboardMonth.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
