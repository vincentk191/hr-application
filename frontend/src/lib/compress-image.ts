const MAX_SIDE = 640;
const QUALITY = 0.8;

export type CompressedImage = {
    file: File;
    /** False when the original was kept: the browser could not do it, or it would not have been smaller. */
    converted: boolean;
};

/**
 * Shrinks a picture and re-encodes it as WebP in the browser, before it is uploaded.
 *
 * The longest side is brought down to {@link MAX_SIDE} (never enlarged) and the picture is saved at
 * a quality that is indistinguishable on a profile picture at a fraction of the size. Phone photos
 * of several MB come out well under 100 KB. Orientation from the camera is applied, so the result
 * is the right way up without the EXIF data that WebP would drop.
 *
 * It never gets in the way: if the browser cannot decode or encode the picture, or the result is
 * not smaller, the original is returned untouched and the server's own rules apply as before.
 */
export async function compressImage(file: File): Promise<CompressedImage> {
    const original = { file, converted: false };

    if (
        typeof createImageBitmap !== 'function' ||
        !file.type.startsWith('image/')
    ) {
        return original;
    }

    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        const scale = Math.min(
            1,
            MAX_SIDE / Math.max(bitmap.width, bitmap.height),
        );
        const canvas = document.createElement('canvas');

        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        canvas
            .getContext('2d')
            ?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/webp', QUALITY),
        );

        // A browser that cannot encode WebP quietly hands back a PNG: that is not what was asked for.
        if (
            blob === null ||
            blob.type !== 'image/webp' ||
            blob.size >= file.size
        ) {
            return original;
        }

        const name = file.name.replace(/\.[^.]+$/, '') || 'foto';

        return {
            file: new File([blob], `${name}.webp`, { type: 'image/webp' }),
            converted: true,
        };
    } catch {
        return original;
    }
}
