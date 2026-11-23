export const money = (v) => '৳' + Number(v ?? 0).toLocaleString('en-US', { maximumFractionDigits: 2 });

export const date = (v) =>
    v ? new Date(v).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '';

export const dateTime = (v) =>
    v ? new Date(v).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

export const statusColor = (s) =>
    ({
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-blue-100 text-blue-800',
        shipped: 'bg-indigo-100 text-indigo-800',
        delivered: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
        paid: 'bg-green-100 text-green-800',
        unpaid: 'bg-slate-200 text-slate-700',
        refunded: 'bg-purple-100 text-purple-800',
        in_stock: 'bg-green-100 text-green-800',
        out_of_stock: 'bg-red-100 text-red-800',
        pre_order: 'bg-blue-100 text-blue-800',
        up_coming: 'bg-purple-100 text-purple-800',
    })[s] ?? 'bg-slate-100 text-slate-700';

export const titleCase = (s) => String(s ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

/** Strip empty values so query strings stay tidy. */
export const cleanParams = (obj) =>
    Object.fromEntries(
        Object.entries(obj).filter(([, v]) => v !== '' && v !== null && v !== undefined && !(Array.isArray(v) && v.length === 0)),
    );
