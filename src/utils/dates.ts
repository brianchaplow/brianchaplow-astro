/**
 * Format a date as a human-readable string
 * e.g., "December 22, 2025"
 */
export function readableDate(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  return d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format a date as ISO 8601 string
 * e.g., "2025-12-22T00:00:00.000Z"
 */
export function isoDate(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  return d.toISOString();
}

/**
 * Get just the date portion of ISO string
 * e.g., "2025-12-22"
 */
export function isoDateShort(date: Date | string): string {
  return isoDate(date).split('T')[0];
}

/**
 * Get current year
 */
export function currentYear(): number {
  return new Date().getFullYear();
}
