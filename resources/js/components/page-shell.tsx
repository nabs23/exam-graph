import { cn } from '@/lib/utils';

type PageShellProps = React.ComponentProps<'main'>;

export function PageShell({ className, children, ...props }: PageShellProps) {
    return (
        <main
            className={cn(
                'w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8',
                className,
            )}
            {...props}
        >
            {children}
        </main>
    );
}

type PageHeaderProps = {
    eyebrow?: string;
    title: string;
    description?: string | null;
    actions?: React.ReactNode;
};

export function PageHeader({
    eyebrow,
    title,
    description,
    actions,
}: PageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div className="space-y-1">
                {eyebrow && (
                    <p className="text-sm font-medium uppercase tracking-wide text-primary">
                        {eyebrow}
                    </p>
                )}
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-3xl text-sm text-muted-foreground sm:text-base">
                        {description}
                    </p>
                )}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
        </header>
    );
}

export function EmptyState({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex min-h-32 items-center justify-center rounded-xl border border-dashed bg-muted/20 p-8 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}
