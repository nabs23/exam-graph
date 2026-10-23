import { router, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

export interface TabItem {
    value: string;
    label: ReactNode;
    icon?: ReactNode;
    content: ReactNode;
}

export type URLTabsVariant = 'default' | 'full-width' | 'underline';

interface URLTabsContainerProps {
    tabs: TabItem[];
    defaultTab?: string;
    queryParam?: string;
    variant?: URLTabsVariant;
    className?: string; // Class for the root Tabs component
    tabsListClassName?: string; // Class for the TabsList component
    fullWidth?: boolean; // Whether the tab list container should take full width
    boxedBody?: boolean; // Whether the tab panel body should have border and padding
}

export function replaceUrlQueryParams(
    updates: Record<string, string | null | undefined>,
): void {
    const url = new URL(window.location.href);

    Object.entries(updates).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            url.searchParams.delete(key);

            return;
        }

        url.searchParams.set(key, value);
    });

    router.visit(url.toString(), {
        preserveState: true,
        preserveScroll: false,
        replace: true,
    });
}

/**
 * A reusable tabbed UI component that syncs the active tab with URL query parameters.
 *
 * @example
 * ```tsx
 * const tabs = [
 *   { value: 'overview', label: 'Overview', content: <OverviewTab /> },
 *   { value: 'settings', label: 'Settings', content: <SettingsTab /> }
 * ];
 *
 * <URLTabsContainer tabs={tabs} defaultTab="overview" />
 * ```
 *
 * This will create tabs that sync with ?tab=overview or ?tab=settings in the URL.
 */
export default function URLTabsContainer({
    tabs,
    defaultTab,
    queryParam = 'tab',
    className,
    tabsListClassName,
    variant = 'default',
    fullWidth = false,
    boxedBody = true,
}: URLTabsContainerProps) {
    const { url } = usePage();
    const tabsId = useId();
    const [isMobileSheetOpen, setIsMobileSheetOpen] = useState(false);
    const tabButtonRefs = useRef<Record<string, HTMLButtonElement | null>>({});
    const pendingFocusTab = useRef<string | null>(null);

    const resolveTab = (rawUrl?: string): string => {
        const fallback = defaultTab || tabs[0]?.value || '';

        if (!rawUrl) {
            return fallback;
        }

        const search = rawUrl.includes('?')
            ? rawUrl.slice(rawUrl.indexOf('?'))
            : '';
        const urlParams = new URLSearchParams(search);
        const tabFromUrl = urlParams.get(queryParam);

        if (tabFromUrl && tabs.some((tab) => tab.value === tabFromUrl)) {
            return tabFromUrl;
        }

        return fallback;
    };

    const activeTab = resolveTab(url);
    const activeTabItem = tabs.find((tab) => tab.value === activeTab);
    const isFullWidthVariant = variant === 'full-width';

    const getTabButtonId = (value: string) => `${tabsId}-tab-${value}`;
    const getTabPanelId = (value: string) => `${tabsId}-panel-${value}`;

    // Update URL when tab changes
    const handleTabChange = (value: string) => {
        replaceUrlQueryParams({
            [queryParam]: value,
        });
    };

    const handleKeyboardTabChange = (value: string) => {
        pendingFocusTab.current = value;
        handleTabChange(value);

        if (value === activeTab) {
            tabButtonRefs.current[value]?.focus();
            pendingFocusTab.current = null;
        }
    };

    useEffect(() => {
        if (pendingFocusTab.current === activeTab) {
            tabButtonRefs.current[activeTab]?.focus();
            pendingFocusTab.current = null;
        }
    }, [activeTab]);

    return (
        <div className={cn('w-full', className)}>
            <div className="sm:hidden">
                <Sheet
                    open={isMobileSheetOpen}
                    onOpenChange={setIsMobileSheetOpen}
                >
                    <SheetTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full justify-between"
                        >
                            <span className="inline-flex min-w-0 items-center gap-2">
                                {activeTabItem?.icon ? (
                                    <span aria-hidden="true">
                                        {activeTabItem.icon}
                                    </span>
                                ) : null}
                                <span className="truncate">
                                    {activeTabItem?.label ?? 'Select tab'}
                                </span>
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Sections
                            </span>
                        </Button>
                    </SheetTrigger>

                    <SheetContent side="bottom" className="rounded-t-2xl">
                        <SheetHeader>
                            <SheetTitle>Select section</SheetTitle>
                        </SheetHeader>
                        <div className="flex flex-col gap-2 px-4 pb-6">
                            {tabs.map((tab) => (
                                <Button
                                    key={tab.value}
                                    type="button"
                                    variant={
                                        tab.value === activeTab
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={tab.value === activeTab}
                                    className="w-full justify-start"
                                    onClick={() => {
                                        handleTabChange(tab.value);
                                        setIsMobileSheetOpen(false);
                                    }}
                                >
                                    <span className="inline-flex items-center gap-2">
                                        {tab.icon ? (
                                            <span aria-hidden="true">
                                                {tab.icon}
                                            </span>
                                        ) : null}
                                        <span>{tab.label}</span>
                                    </span>
                                </Button>
                            ))}
                        </div>
                    </SheetContent>
                </Sheet>
            </div>

            <div
                className={cn(
                    'hidden overflow-x-auto overflow-y-hidden sm:block',
                    fullWidth || isFullWidthVariant ? 'w-full' : 'w-fit',
                )}
            >
                <div
                    role="tablist"
                    aria-label="Sections"
                    className={cn(
                        'flex w-full min-w-max sm:min-w-0',
                        isFullWidthVariant &&
                            'justify-between gap-1 rounded-full bg-muted p-1 [&>*]:rounded-full!',
                        variant === 'underline' &&
                            'gap-4 border-b border-border [&>*]:rounded-none',
                        tabsListClassName,
                    )}
                >
                    {tabs.map((tab, index) => (
                        <Button
                            key={tab.value}
                            ref={(element) => {
                                tabButtonRefs.current[tab.value] = element;
                            }}
                            type="button"
                            variant={
                                isFullWidthVariant
                                    ? 'ghost'
                                    : tab.value === activeTab
                                      ? 'default'
                                      : 'outline'
                            }
                            role="tab"
                            id={getTabButtonId(tab.value)}
                            aria-controls={getTabPanelId(tab.value)}
                            aria-selected={tab.value === activeTab}
                            tabIndex={tab.value === activeTab ? 0 : -1}
                            onKeyDown={(event) => {
                                if (!tabs.length) {
                                    return;
                                }

                                if (event.key === 'ArrowRight') {
                                    event.preventDefault();
                                    const nextIndex = (index + 1) % tabs.length;
                                    handleKeyboardTabChange(
                                        tabs[nextIndex].value,
                                    );
                                }

                                if (event.key === 'ArrowLeft') {
                                    event.preventDefault();
                                    const prevIndex =
                                        (index - 1 + tabs.length) % tabs.length;
                                    handleKeyboardTabChange(
                                        tabs[prevIndex].value,
                                    );
                                }

                                if (event.key === 'Home') {
                                    event.preventDefault();
                                    handleKeyboardTabChange(tabs[0].value);
                                }

                                if (event.key === 'End') {
                                    event.preventDefault();
                                    handleKeyboardTabChange(
                                        tabs[tabs.length - 1].value,
                                    );
                                }
                            }}
                            onClick={() => handleTabChange(tab.value)}
                            className={cn(
                                'h-10 cursor-pointer px-4 text-sm font-medium transition-all duration-200 focus-visible:z-10',
                                isFullWidthVariant &&
                                    'flex-1 justify-center text-muted-foreground hover:bg-background/70 hover:text-foreground',
                                variant === 'underline' &&
                                    'relative rounded-none border-0 bg-transparent px-1 shadow-none after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:origin-center after:scale-x-0 after:bg-primary after:transition-transform hover:bg-transparent',
                                variant === 'underline' &&
                                    tab.value === activeTab &&
                                    'text-foreground after:scale-x-100',
                                variant === 'underline' &&
                                    tab.value !== activeTab &&
                                    'text-muted-foreground hover:text-foreground',
                                variant === 'default' && 'flex-1',
                                variant === 'default' &&
                                    (tab.value === activeTab
                                        ? 'shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'),
                                isFullWidthVariant &&
                                    tab.value === activeTab &&
                                    'bg-background text-foreground shadow-sm',
                            )}
                        >
                            <span className="inline-flex items-center gap-2">
                                {tab.icon ? (
                                    <span aria-hidden="true">{tab.icon}</span>
                                ) : null}
                                <span>{tab.label}</span>
                            </span>
                        </Button>
                    ))}
                </div>
            </div>

            <div
                role="tabpanel"
                tabIndex={0}
                id={
                    activeTabItem
                        ? getTabPanelId(activeTabItem.value)
                        : undefined
                }
                aria-labelledby={
                    activeTabItem
                        ? getTabButtonId(activeTabItem.value)
                        : undefined
                }
                className={cn(
                    'mt-4',
                    boxedBody &&
                        'rounded-xl border border-border bg-background p-4 sm:p-6',
                )}
            >
                {activeTabItem?.content}
            </div>
        </div>
    );
}
