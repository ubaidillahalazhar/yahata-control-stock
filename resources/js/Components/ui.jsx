import { LoaderCircle } from 'lucide-react';
import { forwardRef } from 'react';

export function cn(...classes) {
    return classes.filter(Boolean).join(' ');
}

const BUTTON_VARIANTS = {
    primary: 'bg-blue-600 text-white shadow-sm hover:bg-blue-700 focus-visible:outline-blue-600',
    success: 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-emerald-600',
    danger: 'bg-red-600 text-white shadow-sm hover:bg-red-700 focus-visible:outline-red-600',
    dark: 'bg-slate-800 text-white shadow-sm hover:bg-slate-900 focus-visible:outline-slate-800',
    outline:
        'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline-slate-400',
    'outline-danger': 'border border-red-300 bg-white text-red-700 hover:bg-red-50 focus-visible:outline-red-500',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
};

const BUTTON_SIZES = {
    xs: 'h-7 gap-1 px-2 text-xs',
    sm: 'h-8 gap-1.5 px-3 text-sm',
    md: 'h-10 gap-2 px-4 text-sm',
    lg: 'h-12 gap-2 px-5 text-base',
    icon: 'size-8',
};

export function Button({
    as: Component = 'button',
    variant = 'primary',
    size = 'md',
    loading = false,
    disabled = false,
    type,
    className,
    children,
    ...props
}) {
    const isButton = Component === 'button';

    return (
        <Component
            type={isButton ? (type ?? 'button') : undefined}
            disabled={isButton ? disabled || loading : undefined}
            className={cn(
                'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-all duration-150 active:scale-[0.98] disabled:opacity-50',
                'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                BUTTON_VARIANTS[variant],
                BUTTON_SIZES[size],
                className,
            )}
            {...props}
        >
            {loading && <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />}
            {children}
        </Component>
    );
}

export function Card({ className, children, ...props }) {
    return (
        <div className={cn('rounded-xl border border-slate-200/80 bg-white shadow-sm transition-shadow duration-200 hover:shadow-md', className)} {...props}>
            {children}
        </div>
    );
}

export function CardHeader({ icon: Icon, title, subtitle, actions, className }) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                {Icon && (
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-700">
                        <Icon className="size-5" aria-hidden="true" />
                    </div>
                )}
                <div className="min-w-0">
                    <h2 className="text-base font-bold text-slate-900">{title}</h2>
                    {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
                </div>
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

const controlClass = (invalid) =>
    cn(
        'block w-full rounded-lg border bg-white text-slate-800 shadow-2xs transition-colors placeholder:text-slate-400',
        'focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none disabled:bg-slate-100',
        invalid ? 'border-red-500' : 'border-slate-300',
    );

export const Input = forwardRef(function Input({ invalid = false, className, ...props }, ref) {
    return <input ref={ref} className={cn(controlClass(invalid), 'h-10 px-3 text-sm', className)} {...props} />;
});

export const Select = forwardRef(function Select({ invalid = false, className, children, ...props }, ref) {
    return (
        <select ref={ref} className={cn(controlClass(invalid), 'h-10 px-3 pr-8 text-sm', className)} {...props}>
            {children}
        </select>
    );
});

export const Textarea = forwardRef(function Textarea({ invalid = false, className, ...props }, ref) {
    return <textarea ref={ref} className={cn(controlClass(invalid), 'px-3 py-2 text-sm', className)} {...props} />;
});

export function Field({ label, htmlFor, required = false, hint, error, className, children }) {
    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <label htmlFor={htmlFor} className="block text-sm font-semibold text-slate-700">
                    {label}
                    {required && <span className="text-red-600"> *</span>}
                </label>
            )}
            {children}
            {hint && !error && <p className="text-xs text-slate-500">{hint}</p>}
            {error && <p className="text-xs font-semibold text-red-600">{error}</p>}
        </div>
    );
}

export function EmptyState({ icon: Icon, title, description, action }) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            {Icon && (
                <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                    <Icon className="size-6" aria-hidden="true" />
                </div>
            )}
            <p className="text-sm font-bold text-slate-800">{title}</p>
            {description && <p className="mt-1 max-w-md text-sm text-slate-500">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function Stat({ label, value, className, valueClassName }) {
    return (
        <div className={cn('rounded-md border border-slate-200 bg-slate-50 px-3 py-2', className)}>
            <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">{label}</p>
            <p className={cn('mt-0.5 text-lg font-bold text-slate-900 tabular-nums', valueClassName)}>{value}</p>
        </div>
    );
}
