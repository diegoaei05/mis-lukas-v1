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

export default function DashboardSubcategories({ year, month, monthLabel, currency, currencyLabel, group, groupLabel, categoryId, category, categories, slices }: { year: number; month: number; monthLabel: string; currency: string; currencyLabel: string; group: 'fixed' | 'variable'; groupLabel: string; categoryId: number; category: string; categories: CategoryOption[]; slices: RingSlice[] }) {
    return (
        <>
            <Head title={`${category} · ${monthLabel} ${year}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <TextLink href={show.url({ year, month, currency })} className="w-fit text-sm">
                    Back to {monthLabel}
                </TextLink>

                <div className="flex flex-wrap gap-2" role="group" aria-label="Category">
                    {categories.map((option) => (
                        <Button key={option.id} variant={option.id === categoryId ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={subcategories.url({ year, month, currency, group, category: option.id })} preserveScroll aria-current={option.id === categoryId ? 'page' : undefined}>
                                {option.name}
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="flex flex-col gap-2">
                    <h2 className="text-xl font-semibold tracking-tight">{category}</h2>
                    <p className="text-sm text-muted-foreground">
                        {monthLabel} {year}. {currencyLabel}. {groupLabel}, split by subcategory.
                    </p>
                </div>

                <div className="mx-auto w-full max-w-xl">
                    <CategoryRing title={category} slices={slices} emptyLabel="Sin subcategoría" emptyMessage="No subcategories for this month." />
                </div>
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
