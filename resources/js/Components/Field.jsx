import { useId } from 'react';

export function Field({ label, error, children, hint, className = '', id }) {
    return (
        <div className={className}>
            {label && <label className="label" htmlFor={id}>{label}</label>}
            {children}
            {hint && !error && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600" role="alert">{error}</p>}
        </div>
    );
}

export function TextInput({ label, error, hint, className = '', ...props }) {
    const id = useId();
    return (
        <Field label={label} error={error} hint={hint} className={className} id={id}>
            <input id={id} className="input" aria-invalid={!!error} {...props} />
        </Field>
    );
}

export function TextArea({ label, error, hint, className = '', ...props }) {
    const id = useId();
    return (
        <Field label={label} error={error} hint={hint} className={className} id={id}>
            <textarea id={id} className="input" rows={4} aria-invalid={!!error} {...props} />
        </Field>
    );
}

export function Select({ label, error, hint, className = '', options = [], placeholder, ...props }) {
    const id = useId();
    return (
        <Field label={label} error={error} hint={hint} className={className} id={id}>
            <select id={id} className="input" aria-invalid={!!error} {...props}>
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </select>
        </Field>
    );
}

export function Checkbox({ label, checked, onChange, className = '' }) {
    return (
        <label className={`inline-flex cursor-pointer items-center gap-2 text-sm ${className}`}>
            <input type="checkbox" className="h-4 w-4 rounded border-slate-300 accent-brand-700" checked={!!checked} onChange={(e) => onChange(e.target.checked)} />
            {label}
        </label>
    );
}
