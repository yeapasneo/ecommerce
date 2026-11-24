import { useEffect } from 'react';
import Icon from './Icon';

export default function Modal({ open, onClose, title, children, wide = false }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 sm:p-8" onMouseDown={onClose}>
            <div className={`w-full overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 ${wide ? 'max-w-3xl' : 'max-w-lg'} my-auto`} onMouseDown={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                <div className="flex items-center justify-between px-6 pb-2 pt-5">
                    <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
                    <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close"><Icon name="x" /></button>
                </div>
                <div className="px-6 pb-6 pt-3">{children}</div>
            </div>
        </div>
    );
}
