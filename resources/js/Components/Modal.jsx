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
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8" onMouseDown={onClose}>
            <div className={`card w-full ${wide ? 'max-w-3xl' : 'max-w-lg'} my-auto`} onMouseDown={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 className="font-semibold">{title}</h3>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-700" aria-label="Close"><Icon name="x" /></button>
                </div>
                <div className="p-5">{children}</div>
            </div>
        </div>
    );
}
