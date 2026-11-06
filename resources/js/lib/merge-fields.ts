/**
 * Fill {{field}} placeholders in a message template. Unknown fields are left visible
 * so a typo in a template is obvious rather than silently blank.
 */
export function fillTemplate(text: string, values: Record<string, string | null | undefined>): string {
    return text.replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (match, field: string) => {
        const value = values[field];
        return value === undefined || value === null || value === '' ? match : value;
    });
}
