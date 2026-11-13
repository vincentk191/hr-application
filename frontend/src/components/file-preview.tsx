import { ChevronLeft, ChevronRight, Download, Eye } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

/** A file that may have a picture to show: only images carry a `previewUrl`. */
export type PreviewableFile = {
    name: string;
    downloadUrl: string;
    previewUrl: string | null;
};

/**
 * A pop-up for looking at pictures without downloading them: the sick note on a request, a scanned KTP in a profile.
 * It steps through the other pictures of the same list with the arrows (buttons or keys), and a tap on the picture
 * toggles between fitting the screen and full size. A file that is not a picture is never shown here; it keeps its
 * download link.
 */
function FilePreviewDialog({
    images,
    index,
    onIndex,
    onClose,
}: {
    images: PreviewableFile[];
    index: number | null;
    onIndex: (next: number) => void;
    onClose: () => void;
}) {
    const [zoomed, setZoomed] = useState(false);
    const [failed, setFailed] = useState(false);

    const file = index === null ? null : (images[index] ?? null);

    if (file === null || index === null) {
        return null;
    }

    const go = (next: number) => {
        setZoomed(false);
        setFailed(false);
        onIndex((next + images.length) % images.length);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                className="grid-cols-[minmax(0,1fr)] gap-0 overflow-hidden p-0 sm:max-w-4xl"
                data-test="file-preview"
                onKeyDown={(event) => {
                    if (images.length > 1 && event.key === 'ArrowLeft') {
                        go(index - 1);
                    }

                    if (images.length > 1 && event.key === 'ArrowRight') {
                        go(index + 1);
                    }
                }}
            >
                {/* The buttons come first: a long name is cut with an ellipsis instead of pushing Unduh under the close button. */}
                <div className="flex min-w-0 items-center justify-between gap-3 border-b bg-muted/40 py-3 pr-12 pl-4">
                    <div className="min-w-0 flex-1">
                        <DialogTitle
                            className="truncate text-base font-bold"
                            title={file.name}
                        >
                            {file.name}
                        </DialogTitle>
                        <DialogDescription className="label-mono">
                            {images.length > 1
                                ? `Gambar ${index + 1} dari ${images.length}`
                                : 'Pratinjau gambar'}
                        </DialogDescription>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="shrink-0"
                    >
                        <a href={file.downloadUrl}>
                            <Download /> Unduh
                        </a>
                    </Button>
                </div>

                <div
                    className={`relative flex min-h-48 items-center justify-center bg-muted/20 ${
                        zoomed ? 'max-h-[75vh] overflow-auto' : ''
                    }`}
                >
                    {failed ? (
                        <p className="p-10 text-sm text-muted-foreground">
                            Gambar tidak dapat ditampilkan. Coba unduh
                            berkasnya.
                        </p>
                    ) : (
                        <img
                            key={file.previewUrl}
                            src={file.previewUrl ?? undefined}
                            alt={file.name}
                            onError={() => setFailed(true)}
                            onClick={() => setZoomed((value) => !value)}
                            className={
                                zoomed
                                    ? 'max-w-none cursor-zoom-out'
                                    : 'max-h-[75vh] max-w-full cursor-zoom-in object-contain'
                            }
                        />
                    )}

                    {images.length > 1 && (
                        <>
                            <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                className="absolute top-1/2 left-2 -translate-y-1/2 rounded-full"
                                aria-label="Gambar sebelumnya"
                                onClick={() => go(index - 1)}
                            >
                                <ChevronLeft />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                className="absolute top-1/2 right-2 -translate-y-1/2 rounded-full"
                                aria-label="Gambar berikutnya"
                                onClick={() => go(index + 1)}
                            >
                                <ChevronRight />
                            </Button>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Shared by every list of files: `open(file)` shows a picture in the pop-up (from the other pictures of `files`),
 * and `dialog` is the pop-up itself, to be rendered once beside the list.
 */
export function useFilePreview(files: PreviewableFile[]) {
    const images = files.filter((file) => file.previewUrl !== null);
    const [index, setIndex] = useState<number | null>(null);

    return {
        open: (file: PreviewableFile) => {
            const position = images.findIndex(
                (image) => image.previewUrl === file.previewUrl,
            );

            setIndex(position === -1 ? null : position);
        },
        dialog: (
            <FilePreviewDialog
                images={images}
                index={index}
                onIndex={setIndex}
                onClose={() => setIndex(null)}
            />
        ),
    };
}

/**
 * A file's name as a link: a picture opens in the pop-up, anything else downloads. A picture also gets an "Unduh" link,
 * since the name no longer downloads it.
 */
export function FileLink({
    file,
    open,
    className = 'underline',
}: {
    file: PreviewableFile;
    open: (file: PreviewableFile) => void;
    className?: string;
}) {
    if (file.previewUrl === null) {
        return (
            <a href={file.downloadUrl} className={className}>
                {file.name}
            </a>
        );
    }

    return (
        <span className="inline-flex flex-wrap items-center gap-x-2">
            <button
                type="button"
                onClick={() => open(file)}
                className={`inline-flex items-center gap-1 text-left ${className}`}
                data-test="preview-file"
            >
                <Eye className="size-3.5 shrink-0" />
                {file.name}
            </button>
            <a
                href={file.downloadUrl}
                className="text-xs text-muted-foreground underline-offset-4 hover:underline"
            >
                Unduh
            </a>
        </span>
    );
}
