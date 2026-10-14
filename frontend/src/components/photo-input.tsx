import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { compressImage } from '@/lib/compress-image';

function size(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * The profile picture field. Whatever is chosen is shrunk and turned into WebP on the spot, and the
 * smaller file replaces the chosen one in the input, so that is what the form sends. A phone photo
 * of several MB goes up as a few dozen KB; nothing else about the form changes.
 */
export function PhotoInput({ id, name }: { id: string; name: string }) {
    const [note, setNote] = useState<string | null>(null);

    const onChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const input = event.currentTarget;
        const chosen = input.files?.[0];

        if (!chosen) {
            setNote(null);

            return;
        }

        const { file, converted } = await compressImage(chosen);

        if (!converted) {
            setNote(null);

            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        setNote(
            `Dikecilkan dan diubah ke WebP: ${size(chosen.size)} → ${size(file.size)}.`,
        );
    };

    return (
        <>
            <Input
                id={id}
                name={name}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={onChange}
            />
            <p className="text-xs text-muted-foreground">
                {note ??
                    'JPG, PNG, atau WebP. Foto dikecilkan dan diubah ke WebP otomatis; maksimal 2 MB.'}
            </p>
        </>
    );
}
