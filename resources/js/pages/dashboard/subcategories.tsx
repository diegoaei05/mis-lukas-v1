import { Head, Link } from '@inertiajs/react';
import { show, subcategories } from '@/actions/App/Http/Controllers/DashboardController';
import CategoryRing, { type RingSlice } from '@/components/category-ring';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type CategoryOption = {
    id: number;
    name: string;
};

type CurrencyCharts = {
    fixed: RingSlice[];
    variable: RingSlice[];
};

function CurrencyCharts({ label, charts }: { label: string; charts: CurrencyCharts }) {
    return (
        <section className="flex flex-col gap-4">
            <h3 className="text-lg font-semibold">{label}</h3>
            <div className="grid gap-6 lg:grid-cols-2">
                <CategoryRing title="Fijos" slices={charts.fixed} emptyLabel="Sin subcategoría" emptyMessage="No expenses for this category in this month." />
                <CategoryRing title="Variables" slices={charts.variable} emptyLabel="Sin subcategoría" emptyMessage="No expenses for this category in this month." />
            </div>
        </section>
    );
}

export default function DashboardSubcategories({ year, month, monthLabel, categoryId, category, categories, soles, dollars }: { year: number; month: string; monthLabel: string; categoryId: number; category: string; categories: CategoryOption[]; soles: CurrencyCharts; dollars: CurrencyCharts }) {
    return (
        <>
            <Head title={`${category} · ${monthLabel} ${year}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <TextLink href={show.url({ year, month })} className="w-fit text-sm">
                    Back to {monthLabel}
                </TextLink>

                <div className="flex flex-wrap gap-2" role="group" aria-label="Category">
                    {categories.map((option) => (
                        <Button key={option.id} variant={option.id === categoryId ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={subcategories.url({ year, month, category: option.id })} preserveScroll aria-current={option.id === categoryId ? 'page' : undefined}>
                                {option.name}
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-xl font-semibold tracking-tight">{category}</h2>
                    <p className="text-sm text-muted-foreground">
                        {monthLabel} {year}. Fijos and Variables, in Soles and Dólares, split by subcategory.
                    </p>
                </div>

                <CurrencyCharts label="Soles" charts={soles} />
                <CurrencyCharts label="Dólares" charts={dollars} />
            </div>
        </>
    );
}

DashboardSubcategories.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
