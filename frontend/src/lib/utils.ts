import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge conditional class names and let later Tailwind utilities win.
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

/**
 * Format a fixed two-decimal money string for display.
 *
 * The API sends `decimal(15,2)` amounts as strings; the string is kept rather
 * than parsed so the cents shown are the cents the API wrote. Formatting by
 * splitting on the `.` (rather than `Number()` round-tripping) is what keeps a
 * summed total from drifting by a cent.
 */
export function rupiah(value: string): string {
    const [whole, fraction] = value.split('.');
    const grouped = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Number(whole));

    return fraction === undefined ? `Rp ${grouped}` : `Rp ${grouped},${fraction}`;
}
