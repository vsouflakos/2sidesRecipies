import { Head } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Toggle } from '@/components/ui/toggle';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';

// ─── Color swatches ───────────────────────────────────────────────────────────

type SwatchItem = {
    variable: string;
    label: string;
    bgClass: string;
    lightValue: string;
    darkValue: string;
    isAccent?: boolean;
};

const swatches: SwatchItem[] = [
    {
        variable: '--background',
        label: 'background',
        bgClass: 'bg-background',
        lightValue: 'oklch(0.981 0.007 60)',
        darkValue: 'oklch(0.148 0.008 50)',
    },
    {
        variable: '--foreground',
        label: 'foreground',
        bgClass: 'bg-foreground',
        lightValue: 'oklch(0.168 0.010 50)',
        darkValue: 'oklch(0.940 0.008 60)',
    },
    {
        variable: '--card',
        label: 'card',
        bgClass: 'bg-card',
        lightValue: 'oklch(0.981 0.007 60)',
        darkValue: 'oklch(0.148 0.008 50)',
    },
    {
        variable: '--primary',
        label: 'primary',
        bgClass: 'bg-primary',
        lightValue: 'oklch(0.210 0.010 50)',
        darkValue: 'oklch(0.940 0.008 60)',
    },
    {
        variable: '--secondary',
        label: 'secondary',
        bgClass: 'bg-secondary',
        lightValue: 'oklch(0.948 0.010 60)',
        darkValue: 'oklch(0.225 0.010 52)',
    },
    {
        variable: '--muted',
        label: 'muted',
        bgClass: 'bg-muted',
        lightValue: 'oklch(0.948 0.010 60)',
        darkValue: 'oklch(0.225 0.010 52)',
    },
    {
        variable: '--muted-foreground',
        label: 'muted-foreground',
        bgClass: 'bg-muted-foreground',
        lightValue: 'oklch(0.530 0.018 55)',
        darkValue: 'oklch(0.650 0.015 56)',
    },
    {
        variable: '--accent',
        label: 'accent',
        bgClass: 'bg-accent',
        lightValue: 'oklch(0.550 0.035 60)',
        darkValue: 'oklch(0.550 0.035 60)',
        isAccent: true,
    },
    {
        variable: '--destructive',
        label: 'destructive',
        bgClass: 'bg-destructive',
        lightValue: 'oklch(0.577 0.245 27.325)',
        darkValue: 'oklch(0.396 0.141 25.723)',
    },
    {
        variable: '--border',
        label: 'border',
        bgClass: 'bg-border',
        lightValue: 'oklch(0.900 0.012 58)',
        darkValue: 'oklch(0.235 0.010 52)',
    },
    {
        variable: '--input',
        label: 'input',
        bgClass: 'bg-input',
        lightValue: 'oklch(0.900 0.012 58)',
        darkValue: 'oklch(0.235 0.010 52)',
    },
    {
        variable: '--ring',
        label: 'ring',
        bgClass: 'bg-ring',
        lightValue: 'oklch(0.820 0.018 58)',
        darkValue: 'oklch(0.395 0.015 54)',
    },
    {
        variable: '--sidebar',
        label: 'sidebar',
        bgClass: 'bg-sidebar',
        lightValue: 'oklch(0.970 0.009 60)',
        darkValue: 'oklch(0.188 0.008 50)',
    },
    {
        variable: '--sidebar-primary',
        label: 'sidebar-primary',
        bgClass: 'bg-sidebar-primary',
        lightValue: 'oklch(0.210 0.010 50)',
        darkValue: 'oklch(0.940 0.008 60)',
    },
    {
        variable: '--sidebar-accent',
        label: 'sidebar-accent',
        bgClass: 'bg-sidebar-accent',
        lightValue: 'oklch(0.920 0.015 58)',
        darkValue: 'oklch(0.255 0.010 52)',
    },
];

// ─── Typography rows ──────────────────────────────────────────────────────────

const typographyRows = [
    {
        role: 'Display',
        className: 'text-[28px] font-semibold leading-[1.2]',
        annotation: '28px / 600',
    },
    {
        role: 'Heading',
        className: 'text-[20px] font-semibold leading-[1.2]',
        annotation: '20px / 600',
    },
    {
        role: 'Body',
        className: 'text-[16px] font-normal leading-[1.5]',
        annotation: '16px / 400',
    },
    {
        role: 'Label',
        className: 'text-[14px] font-normal leading-[1.4]',
        annotation: '14px / 400',
    },
];

// ─── Spacing tokens ───────────────────────────────────────────────────────────

const spacingTokens = [
    { label: 'xs', size: 4 },
    { label: 'sm', size: 8 },
    { label: 'md', size: 16 },
    { label: 'lg', size: 24 },
    { label: 'xl', size: 32 },
    { label: '2xl', size: 48 },
    { label: '3xl', size: 64 },
];

// ─── Section wrapper ─────────────────────────────────────────────────────────

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-4">
            <h2 className="text-[20px] leading-[1.2] font-semibold text-foreground">
                {title}
            </h2>
            {children}
        </section>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Styleguide() {
    const { appearance, updateAppearance } = useAppearance();
    const isDark = appearance === 'dark';

    const toggleTheme = () => {
        updateAppearance(isDark ? 'light' : 'dark');
    };

    // Check if table is available (installed in Plan 04)
    const hasTable = false; // table.tsx not yet installed

    return (
        <>
            <Head title="Styleguide" />

            <div className="relative min-h-screen bg-background text-foreground">
                {/* Theme toggle — top-right */}
                <div className="absolute top-6 right-6 z-10">
                    <Toggle
                        aria-label="Toggle theme"
                        pressed={isDark}
                        onPressedChange={toggleTheme}
                        variant="outline"
                        className="gap-2"
                    >
                        {isDark ? (
                            <Moon className="size-4" />
                        ) : (
                            <Sun className="size-4" />
                        )}
                        {isDark ? 'Dark' : 'Light'}
                    </Toggle>
                </div>

                <div className="mx-auto max-w-5xl space-y-12 px-6 py-16">
                    <div>
                        <h1 className="text-[28px] leading-[1.2] font-semibold">
                            Design System — Styleguide
                        </h1>
                        <p className="mt-2 text-[14px] text-muted-foreground">
                            Warm-minimal token set and shadcn/ui component
                            gallery. Toggle Light / Dark in the top right.
                        </p>
                    </div>

                    <Separator />

                    {/* ── 1. Color Palette ─────────────────────────────────── */}
                    <Section title="Color Palette">
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                            {swatches.map((swatch) => (
                                <div
                                    key={swatch.variable}
                                    className={`overflow-hidden rounded-md border ${swatch.isAccent ? 'border-accent' : 'border-border'}`}
                                >
                                    <div
                                        className={`h-16 w-full ${swatch.bgClass}`}
                                    />
                                    <div className="space-y-1 p-3">
                                        <p
                                            className={`text-[13px] font-medium ${swatch.isAccent ? 'text-accent' : 'text-foreground'}`}
                                        >
                                            {swatch.label}
                                        </p>
                                        <p className="text-[11px] break-all text-muted-foreground">
                                            {isDark
                                                ? swatch.darkValue
                                                : swatch.lightValue}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>

                    <Separator />

                    {/* ── 2. Typography Scale ──────────────────────────────── */}
                    <Section title="Typography Scale">
                        <div className="space-y-6">
                            {typographyRows.map((row) => (
                                <div
                                    key={row.role}
                                    className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-6"
                                >
                                    <span className="w-20 shrink-0 text-[13px] font-medium text-muted-foreground">
                                        {row.role}
                                    </span>
                                    <span className={row.className}>
                                        The quick brown fox jumps over the lazy
                                        dog
                                    </span>
                                    <span className="shrink-0 text-[12px] text-muted-foreground">
                                        {row.annotation}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Section>

                    <Separator />

                    {/* ── 3. Buttons ───────────────────────────────────────── */}
                    <Section title="Buttons">
                        <div className="space-y-6">
                            {/* Variants */}
                            <div>
                                <p className="mb-3 text-[13px] text-muted-foreground">
                                    Variants (default size)
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    <Button>Default</Button>
                                    <Button variant="secondary">
                                        Secondary
                                    </Button>
                                    <Button variant="outline">Outline</Button>
                                    <Button variant="ghost">Ghost</Button>
                                    <Button variant="destructive">
                                        Destructive
                                    </Button>
                                    <Button variant="link">Link</Button>
                                </div>
                            </div>
                            {/* Sizes */}
                            <div>
                                <p className="mb-3 text-[13px] text-muted-foreground">
                                    Sizes
                                </p>
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button size="sm">Small</Button>
                                    <Button size="default">Default</Button>
                                    <Button size="lg">Large</Button>
                                    <Button
                                        size="icon"
                                        aria-label="Icon button"
                                    >
                                        <Sun />
                                    </Button>
                                </div>
                            </div>
                            {/* States */}
                            <div>
                                <p className="mb-3 text-[13px] text-muted-foreground">
                                    States
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    <Button>Normal</Button>
                                    <Button disabled>Disabled</Button>
                                    <Button disabled>
                                        <Spinner />
                                        Loading
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </Section>

                    <Separator />

                    {/* ── 4. Form Elements ─────────────────────────────────── */}
                    <Section title="Form Elements">
                        <div className="space-y-6">
                            {/* Input */}
                            <div>
                                <p className="mb-3 text-[13px] text-muted-foreground">
                                    Input
                                </p>
                                <div className="flex max-w-sm flex-col gap-3">
                                    <Input placeholder="Normal input" />
                                    <Input
                                        placeholder="Disabled input"
                                        disabled
                                    />
                                    <Input
                                        placeholder="Error input"
                                        aria-invalid
                                        className="border-destructive"
                                    />
                                </div>
                            </div>
                            {/* Select */}
                            <div>
                                <p className="mb-3 text-[13px] text-muted-foreground">
                                    Select
                                </p>
                                <Select>
                                    <SelectTrigger className="w-48">
                                        <SelectValue placeholder="Select an option" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">
                                            Admin
                                        </SelectItem>
                                        <SelectItem value="moderator">
                                            Moderator
                                        </SelectItem>
                                        <SelectItem value="user">
                                            User
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {/* Checkbox */}
                            <div>
                                <p className="mb-3 text-[13px] text-muted-foreground">
                                    Checkbox
                                </p>
                                <div className="flex flex-col gap-3">
                                    <div className="flex items-center gap-2">
                                        <Checkbox id="cb-unchecked" />
                                        <Label htmlFor="cb-unchecked">
                                            Unchecked
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="cb-checked"
                                            defaultChecked
                                        />
                                        <Label htmlFor="cb-checked">
                                            Checked
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="cb-indeterminate"
                                            data-state="indeterminate"
                                        />
                                        <Label htmlFor="cb-indeterminate">
                                            Indeterminate
                                        </Label>
                                    </div>
                                </div>
                            </div>
                            {/* Toggle */}
                            <div>
                                <p className="mb-3 text-[13px] text-muted-foreground">
                                    Toggle
                                </p>
                                <div className="flex gap-3">
                                    <Toggle aria-label="Toggle on" pressed>
                                        On
                                    </Toggle>
                                    <Toggle aria-label="Toggle off">Off</Toggle>
                                </div>
                            </div>
                        </div>
                    </Section>

                    <Separator />

                    {/* ── 5. Feedback ──────────────────────────────────────── */}
                    <Section title="Feedback">
                        {/* Badges */}
                        <div>
                            <p className="mb-3 text-[13px] text-muted-foreground">
                                Badge variants
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Badge>Default</Badge>
                                <Badge variant="secondary">Secondary</Badge>
                                <Badge variant="outline">Outline</Badge>
                                <Badge variant="destructive">Destructive</Badge>
                            </div>
                        </div>
                        {/* Toasts */}
                        <div>
                            <p className="mb-3 text-[13px] text-muted-foreground">
                                Sonner toasts
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        toast.info('This is an info message.')
                                    }
                                >
                                    Info toast
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        toast.success(
                                            'Operation completed successfully.',
                                        )
                                    }
                                >
                                    Success toast
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        toast.error(
                                            'Something went wrong. Please try again.',
                                        )
                                    }
                                >
                                    Error toast
                                </Button>
                            </div>
                        </div>
                        {/* Skeleton */}
                        <div>
                            <p className="mb-3 text-[13px] text-muted-foreground">
                                Skeleton
                            </p>
                            <div className="flex items-center gap-3">
                                <Skeleton className="size-10 rounded-full" />
                                <div className="space-y-2">
                                    <Skeleton className="h-4 w-32" />
                                    <Skeleton className="h-3 w-48" />
                                </div>
                            </div>
                        </div>
                    </Section>

                    <Separator />

                    {/* ── 6. Overlays ──────────────────────────────────────── */}
                    <Section title="Overlays">
                        <div className="flex flex-wrap gap-4">
                            {/* Dialog */}
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        Open dialog
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Example dialog
                                        </DialogTitle>
                                        <DialogDescription>
                                            This dialog uses the warm-minimal
                                            palette. Confirm action or cancel
                                            below.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button variant="secondary">
                                            Cancel
                                        </Button>
                                        <Button>Confirm</Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                            {/* Tooltip */}
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="outline">
                                            Hover for tooltip
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        This is a tooltip example
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </Section>

                    <Separator />

                    {/* ── 7. Data Display ──────────────────────────────────── */}
                    <Section title="Data Display">
                        {hasTable ? null : (
                            <p className="text-[14px] text-muted-foreground">
                                Table / Pagination — installed in Plan 04.
                            </p>
                        )}
                    </Section>

                    <Separator />

                    {/* ── 8. Spacing Scale ─────────────────────────────────── */}
                    <Section title="Spacing Scale">
                        <div className="space-y-3">
                            {spacingTokens.map((token) => (
                                <div
                                    key={token.label}
                                    className="flex items-center gap-4"
                                >
                                    <span className="w-8 text-[13px] text-muted-foreground">
                                        {token.label}
                                    </span>
                                    <div
                                        className="rounded-sm bg-accent"
                                        style={{
                                            width: token.size,
                                            height: token.size,
                                        }}
                                    />
                                    <span className="text-[13px] text-muted-foreground">
                                        {token.size}px
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Section>
                </div>
            </div>
        </>
    );
}
