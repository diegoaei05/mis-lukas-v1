import { Head, Link, router } from '@inertiajs/react';
import { show, subcategories } from '@/actions/App/Http/Controllers/DashboardController';
import CategoryRing, { type RingSlice } from '@/components/category-ring';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const months = [
    { value: 1, label: 'Ene' },
    { value: 2, label: 'Feb' },
    { value: 3, label: 'Mar' },
    { value: 4, label: 'Abr' },
    { value: 5, label: 'May' },
    { value: 6, label: 'Jun' },
    { value: 7, label: 'Jul' },
    { value: 8, label: 'Ago' },
    { value: 9, label: 'Sep' },
    { value: 10, label: 'Oct' },
    { value: 11, label: 'Nov' },
    { value: 12, label: 'Dic' },
];

export default function DashboardMonth({ year, month, monthLabel, currency, currencyLabel, fixed, variable }: { year: number; month: number; monthLabel: string; currency: string; currencyLabel: string; fixed: RingSlice[]; variable: RingSlice[] }) {
    const openCategory = (group: 'fixed' | 'variable') => (slice: RingSlice) => {
        if (slice.id === null) {
            return;
        }

        router.get(subcategories.url({ year, month, currency, group, category: slice.id }));
    };

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
                            <Link href={show.url({ year, month: option.value, currency })} preserveScroll aria-current={option.value === month ? 'page' : undefined}>
                                {option.label}
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-xl font-semibold tracking-tight">
                        {monthLabel} {year}
                    </h2>
                    <p className="text-sm text-muted-foreground">{currencyLabel}. Fixed bills and variable expenses, each split by category. Click a category to see its subcategories.</p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <CategoryRing title="Fijos" slices={fixed} emptyMessage="No Fijos for this month." onSlice={openCategory('fixed')} />
                    <CategoryRing title="Variables" slices={variable} emptyMessage="No Variables for this month." onSlice={openCategory('variable')} />
                </div>
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
