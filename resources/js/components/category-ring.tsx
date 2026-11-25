import { ArcElement, Chart as ChartJS, type Chart, Legend, type Plugin, Tooltip } from 'chart.js';
import { useMemo } from 'react';
import { Doughnut } from 'react-chartjs-2';
import { useAppearance } from '@/hooks/use-appearance';

ChartJS.register(ArcElement, Tooltip, Legend);

export type RingSlice = {
    id: number | null;
    category: string | null;
    color: string | null;
    amount: string;
};

const categoryHex: Record<string, string> = {
    green: '#a7df60',
    purple: '#be8de5',
    blue: '#7dc6fd',
    gold: '#efcb60',
    red: '#ff4343',
};

const fallbackHex = ['#8f7628', '#737373', '#4C78A8', '#d4d4d4', '#a1a1a1', '#6f5b1e'];

function formatAmount(amount: number): string {
    return amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

type ArcPoint = {
    x: number;
    y: number;
    startAngle: number;
    endAngle: number;
    outerRadius: number;
};

type SliceLabel = {
    startX: number;
    startY: number;
    elbowX: number;
    elbowY: number;
    endX: number;
    textX: number;
    y: number;
    text: string;
    align: CanvasTextAlign;
};

function formatShare(value: number, total: number): string {
    return `${((value / total) * 100).toFixed(2)}%`;
}

function drawSliceShares(chart: Chart<'doughnut'>, color: string, line: string): void {
    const dataset = chart.data.datasets[0];
    const meta = chart.getDatasetMeta(0);
    let total = 0;

    meta.data.forEach((_, index) => {
        if (!chart.getDataVisibility(index)) {
            return;
        }

        const value = Number(dataset.data[index]);

        if (Number.isFinite(value) && value > 0) {
            total += value;
        }
    });

    if (total <= 0) {
        return;
    }

    const labels: SliceLabel[] = [];

    meta.data.forEach((element, index) => {
        if (!chart.getDataVisibility(index)) {
            return;
        }

        const value = Number(dataset.data[index]);

        if (!Number.isFinite(value) || value <= 0) {
            return;
        }

        const { x, y, startAngle, endAngle, outerRadius } = element.getProps(['x', 'y', 'startAngle', 'endAngle', 'outerRadius'], true) as ArcPoint;
        const angle = (startAngle + endAngle) / 2;
        const side = Math.cos(angle) >= 0 ? 1 : -1;
        const elbowX = x + Math.cos(angle) * (outerRadius + 12);
        const elbowY = y + Math.sin(angle) * (outerRadius + 12);

        labels.push({
            startX: x + Math.cos(angle) * outerRadius,
            startY: y + Math.sin(angle) * outerRadius,
            elbowX,
            elbowY,
            endX: elbowX + side * 14,
            textX: elbowX + side * 18,
            y: elbowY,
            text: formatShare(value, total),
            align: side > 0 ? 'left' : 'right',
        });
    });

    for (const align of ['left', 'right'] as const) {
        const group = labels.filter((label) => label.align === align).sort((left, right) => left.y - right.y);

        for (let index = 1; index < group.length; index += 1) {
            const gap = group[index].y - group[index - 1].y;

            if (gap < 16) {
                group[index].y = group[index - 1].y + 16;
            }
        }
    }

    const context = chart.ctx;
    context.save();
    context.font = '600 13px "Instrument Sans", sans-serif';
    context.lineWidth = 1;
    context.strokeStyle = line;
    context.fillStyle = color;
    context.textBaseline = 'middle';

    for (const label of labels) {
        context.beginPath();
        context.moveTo(label.startX, label.startY);
        context.lineTo(label.elbowX, label.elbowY);
        context.lineTo(label.endX, label.y);
        context.stroke();
        context.textAlign = label.align;
        context.fillText(label.text, label.textX, label.y);
    }

    context.restore();
}

function sliceColors(slices: RingSlice[]): string[] {
    let fallback = 0;

    return slices.map((slice) => {
        if (slice.color !== null && categoryHex[slice.color] !== undefined) {
            return categoryHex[slice.color];
        }

        const color = fallbackHex[fallback % fallbackHex.length];
        fallback += 1;

        return color;
    });
}

export default function CategoryRing({ title, slices, emptyLabel = 'Sin categoría', emptyMessage, onSlice }: { title: string; slices: RingSlice[]; emptyLabel?: string; emptyMessage: string; onSlice?: (slice: RingSlice) => void }) {
    const { resolvedAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const legendText = isDark ? '#e4e4e7' : '#3f3f46';
    const shareText = isDark ? '#e4e4e7' : '#1f1f1f';
    const line = isDark ? '#a1a1a1' : '#737373';
    const sliceShares = useMemo<Plugin<'doughnut'>>(
        () => ({
            id: 'sliceShares',
            afterDraw(chart) {
                drawSliceShares(chart, shareText, line);
            },
        }),
        [line, shareText],
    );

    return (
        <section className="rounded-xl border border-border bg-card p-4">
            <h3 className="mb-4 text-base font-semibold">{title}</h3>

            {slices.length === 0 ? (
                <p className="text-sm text-muted-foreground">{emptyMessage}</p>
            ) : (
                <div className="h-[30rem]">
                    <Doughnut
                        data={{
                            labels: slices.map((slice) => slice.category ?? emptyLabel),
                            datasets: [
                                {
                                    data: slices.map((slice) => Number(slice.amount)),
                                    backgroundColor: sliceColors(slices),
                                    borderColor: isDark ? '#0a0a0a' : '#ffffff',
                                    borderWidth: 2,
                                },
                            ],
                        }}
                        plugins={[sliceShares]}
                        options={{
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '58%',
                            layout: {
                                padding: { top: 28, right: 88, bottom: 8, left: 88 },
                            },
                            onClick: (_event, elements) => {
                                const slice = slices[elements[0]?.index ?? -1];

                                if (slice === undefined || slice.id === null || onSlice === undefined) {
                                    return;
                                }

                                onSlice(slice);
                            },
                            onHover: (event, elements) => {
                                const target = event.native?.target;
                                const slice = slices[elements[0]?.index ?? -1];

                                if (target instanceof HTMLElement) {
                                    target.style.cursor = slice?.id != null && onSlice !== undefined ? 'pointer' : 'default';
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { color: legendText },
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (item) => `${item.label}: ${formatAmount(Number(item.raw))}`,
                                    },
                                },
                            },
                        }}
                        aria-label={`${title} by category`}
                    />
                </div>
            )}
        </section>
    );
}
