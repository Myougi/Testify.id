export function sanitizeAriaSnapshot(snapshot: string): string {
    return snapshot
        .split('\n')
        .map((line) =>
            /\b(textbox|searchbox|combobox|spinbutton)\b/i.test(line)
                ? line.replace(/:\s.*$/, '')
                : line,
        )
        .join('\n');
}
