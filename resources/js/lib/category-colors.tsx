import { cn } from '@/lib/utils';

const categoryFills: Record<string, string> = {
    green: 'bg-category-green',
    purple: 'bg-category-purple',
    blue: 'bg-category-blue',
    gold: 'bg-category-gold',
    red: 'bg-category-red',
    orange: 'bg-category-orange',
    teal: 'bg-category-teal',
    pink: 'bg-category-pink',
    indigo: 'bg-category-indigo',
    coral: 'bg-category-coral',
    cyan: 'bg-category-cyan',
    magenta: 'bg-category-magenta',
};

export function categoryFillClass(color: string | null): string {
    if (color === null || color === '') {
        return '';
    }

    return categoryFills[color] ?? '';
}

export function CategoryPill({ label, color }: { label: string | null; color: string | null }) {
    if (label === null || label === '') {
        return <span className="font-medium text-white">-</span>;
    }

    const fill = categoryFillClass(color);

    if (fill === '') {
        return <span className="break-words">{label}</span>;
    }

    return <span className={cn('inline-block max-w-full rounded-full px-2.5 py-0.5 text-left font-medium break-words', fill, 'text-category-foreground')}>{label}</span>;
}
