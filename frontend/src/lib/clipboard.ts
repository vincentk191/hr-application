/**
 * Copy text to the clipboard. The modern API only exists on a secure page (https or localhost), and this app is also
 * opened over plain http on the office network, so fall back to selecting a hidden field and copying from it.
 * Resolves to whether the text was copied.
 */
export async function copyText(text: string): Promise<boolean> {
    try {
        if (window.isSecureContext && navigator.clipboard) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Fall through to the field-based copy.
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(field);
    }
}
