const bgMap = {
    proposing: 'bg-[#f3f3f2]',
    proposal_ready: 'bg-[#f3f3f2]',
    awaiting_approval: 'bg-[#f3f3f2]',
    running: 'bg-info/10',
    done: 'bg-success/10',
    failed_revoked: 'bg-danger/10',
    denied: 'bg-danger/10',
    pending: 'bg-[#f3f3f2]',
    active: 'bg-success/10',
    revoked: 'bg-danger/10',
    expired: 'bg-[#f3f3f2]',
};

const textMap = {
    proposing: 'text-[#706f6c]',
    proposal_ready: 'text-[#706f6c]',
    awaiting_approval: 'text-[#706f6c]',
    running: 'text-info',
    done: 'text-success',
    failed_revoked: 'text-danger',
    denied: 'text-danger',
    pending: 'text-[#706f6c]',
    active: 'text-success',
    revoked: 'text-danger',
    expired: 'text-[#706f6c]',
};

const borderMap = {
    proposing: 'border-[#d6d6d2]',
    proposal_ready: 'border-[#d6d6d2]',
    awaiting_approval: 'border-[#d6d6d2]',
    running: 'border-info/20',
    done: 'border-success/20',
    failed_revoked: 'border-danger/20',
    denied: 'border-danger/20',
    pending: 'border-[#d6d6d2]',
    active: 'border-success/20',
    revoked: 'border-danger/20',
    expired: 'border-[#d6d6d2]',
};

export function StatusBadge({ variant, children }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium border ${
                bgMap[variant]
            } ${textMap[variant]} ${borderMap[variant]}`}
        >
            {children}
        </span>
    );
}
