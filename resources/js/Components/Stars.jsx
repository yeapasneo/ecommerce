import Icon from './Icon';

export default function Stars({ value = 0, size = 'h-4 w-4', onChange }) {
    return (
        <span className="inline-flex gap-0.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    type="button"
                    key={n}
                    disabled={!onChange}
                    onClick={() => onChange?.(n)}
                    className={`${n <= Math.round(value) ? 'text-amber-400' : 'text-slate-300'} ${onChange ? 'cursor-pointer' : 'cursor-default'}`}
                    aria-label={`${n} star`}
                >
                    <Icon name="star" className={size} fill="currentColor" strokeWidth={0} />
                </button>
            ))}
        </span>
    );
}
