import { CloudUploadIcon, ChevronLeftIcon, ChevronRightIcon, XIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useTranslations } from '@/hooks/use-translations';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { RecipeTestPhoto } from '@/types/recipe-test';
import { MAX_TEST_PHOTOS } from '@/types/recipe-test';

const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

// ---- Upload mode types ----
interface UploadModeProps {
    mode: 'upload';
    stagedFiles: File[];
    existingPhotos: RecipeTestPhoto[];
    onAddFiles: (files: File[]) => void;
    onRemoveStaged: (index: number) => void;
    onRemoveExisting: (id: number) => void;
}

// ---- Display mode types ----
interface DisplayModeProps {
    mode: 'display';
    photos: RecipeTestPhoto[];
}

type TestPhotoGridProps = UploadModeProps | DisplayModeProps;

/**
 * Dual-mode photo grid component.
 * - upload: dashed drop zone + thumbnail grid with staged/existing photos
 * - display: inline thumbnail grid with click-to-lightbox
 */
export function TestPhotoGrid(props: TestPhotoGridProps) {
    if (props.mode === 'upload') {
        return <UploadGrid {...props} />;
    }

    return <DisplayGrid {...props} />;
}

// ---- Upload mode implementation ----
function UploadGrid({ stagedFiles, existingPhotos, onAddFiles, onRemoveStaged, onRemoveExisting }: UploadModeProps) {
    const { t } = useTranslations();
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Object URLs for staged file previews
    const [objectUrls, setObjectUrls] = useState<string[]>([]);

    useEffect(() => {
        const urls = stagedFiles.map((f) => URL.createObjectURL(f));
        setObjectUrls(urls);

        return () => {
            urls.forEach((url) => URL.revokeObjectURL(url));
        };
    }, [stagedFiles]);

    const totalCount = existingPhotos.length + stagedFiles.length;
    const limitReached = totalCount >= MAX_TEST_PHOTOS;

    function processFiles(files: FileList | File[]) {
        const accepted: File[] = [];
        const fileArray = Array.from(files);

        for (const file of fileArray) {
            if (!ACCEPTED_MIME.includes(file.type)) {
                toast.error(t('app.tests.toast_upload_error'));
                continue;
            }
            if (file.size > MAX_FILE_SIZE_BYTES) {
                toast.error(t('app.tests.toast_upload_error'));
                continue;
            }
            accepted.push(file);
        }

        if (accepted.length > 0) {
            // Respect the max limit
            const remaining = MAX_TEST_PHOTOS - totalCount;
            onAddFiles(accepted.slice(0, remaining));
        }
    }

    function handleClick() {
        if (limitReached) {
            return;
        }
        fileInputRef.current?.click();
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handleClick();
        }
    }

    function handleDragOver(e: React.DragEvent) {
        e.preventDefault();
    }

    function handleDrop(e: React.DragEvent) {
        e.preventDefault();
        if (limitReached) {
            return;
        }
        processFiles(e.dataTransfer.files);
    }

    const dropZone = (
        <div
            role="button"
            tabIndex={limitReached ? -1 : 0}
            onClick={handleClick}
            onKeyDown={handleKeyDown}
            onDragOver={handleDragOver}
            onDrop={handleDrop}
            aria-label={t('app.tests.photo_upload_zone')}
            className={cn(
                'flex h-[80px] w-full cursor-pointer items-center justify-center gap-2 rounded-md border-2 border-dashed border-border text-[14px] text-muted-foreground transition-colors hover:border-ring hover:bg-muted/30',
                limitReached && 'cursor-not-allowed opacity-50',
            )}
        >
            <CloudUploadIcon className="size-5 shrink-0" />
            <span>{t('app.tests.photo_upload_zone')}</span>
        </div>
    );

    return (
        <div className="flex flex-col gap-2">
            {/* Drop zone — wrapped in tooltip when limit reached */}
            {limitReached ? (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            {dropZone}
                        </TooltipTrigger>
                        <TooltipContent>
                            <span>{t('app.tests.photo_limit')}</span>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            ) : (
                dropZone
            )}

            {/* Hidden file input */}
            <input
                ref={fileInputRef}
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(e) => {
                    if (e.target.files) {
                        processFiles(e.target.files);
                    }
                    // Reset so the same file can be re-selected
                    e.target.value = '';
                }}
            />

            {/* Thumbnail grid */}
            {(existingPhotos.length > 0 || stagedFiles.length > 0) && (
                <div className="flex flex-wrap gap-2">
                    {/* Existing photos */}
                    {existingPhotos.map((photo, idx) => (
                        <div key={photo.id} className="group relative">
                            <img
                                src={photo.url}
                                alt={`Photo ${idx + 1}`}
                                className="h-[80px] w-[80px] rounded-md object-cover"
                            />
                            <button
                                type="button"
                                onClick={() => onRemoveExisting(photo.id)}
                                aria-label={`Remove photo ${idx + 1}`}
                                className="absolute inset-0 flex items-center justify-center rounded-md bg-black/50 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <XIcon className="size-5 text-white" />
                            </button>
                        </div>
                    ))}

                    {/* Staged files */}
                    {stagedFiles.map((_, stagedIdx) => (
                        <div key={stagedIdx} className="group relative">
                            {objectUrls[stagedIdx] && (
                                <img
                                    src={objectUrls[stagedIdx]}
                                    alt={`Staged photo ${existingPhotos.length + stagedIdx + 1}`}
                                    className="h-[80px] w-[80px] rounded-md object-cover"
                                />
                            )}
                            {/* Pending badge */}
                            <span className="absolute top-1 left-1 rounded bg-black/50 px-1 text-[10px] text-white">
                                Pending
                            </span>
                            <button
                                type="button"
                                onClick={() => onRemoveStaged(stagedIdx)}
                                aria-label={`Remove photo ${existingPhotos.length + stagedIdx + 1}`}
                                className="absolute inset-0 flex items-center justify-center rounded-md bg-black/50 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <XIcon className="size-5 text-white" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Photo count indicator */}
            {totalCount > 0 && (
                <p className="text-[14px] text-muted-foreground">
                    {totalCount}/{MAX_TEST_PHOTOS} photos
                </p>
            )}
        </div>
    );
}

// ---- Display mode implementation (lightbox) ----
function DisplayGrid({ photos }: DisplayModeProps) {
    const { t } = useTranslations();
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const [currentIndex, setCurrentIndex] = useState(0);

    if (photos.length === 0) {
        return null;
    }

    const visiblePhotos = photos.slice(0, 4);
    const extraCount = photos.length - 4;

    function openLightbox(index: number) {
        setCurrentIndex(index);
        setLightboxOpen(true);
    }

    function goPrev() {
        setCurrentIndex((i) => (i - 1 + photos.length) % photos.length);
    }

    function goNext() {
        setCurrentIndex((i) => (i + 1) % photos.length);
    }

    return (
        <>
            {/* Thumbnail strip */}
            <div className="flex flex-wrap gap-2">
                {visiblePhotos.map((photo, idx) => (
                    <button
                        key={photo.id}
                        type="button"
                        onClick={() => openLightbox(idx)}
                        className="relative overflow-hidden rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <img
                            src={photo.url}
                            alt={`Photo ${idx + 1}`}
                            className="h-[80px] w-[80px] object-cover rounded-md"
                        />
                        {/* +N more overlay on the 4th thumbnail */}
                        {idx === 3 && extraCount > 0 && (
                            <span className="absolute inset-0 flex items-center justify-center rounded-md bg-black/50 text-sm font-semibold text-white">
                                +{extraCount} more
                            </span>
                        )}
                    </button>
                ))}
            </div>

            {/* Lightbox dialog */}
            <Dialog open={lightboxOpen} onOpenChange={setLightboxOpen}>
                <DialogContent className="max-w-3xl flex flex-col items-center gap-4 p-6">
                    {/* Current photo */}
                    <div className="flex w-full items-center justify-between gap-4">
                        {/* Prev button */}
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={goPrev}
                                        aria-label={t('app.tests.lightbox_prev')}
                                        disabled={photos.length <= 1}
                                        className="shrink-0"
                                    >
                                        <ChevronLeftIcon className="size-5" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <span>{t('app.tests.lightbox_prev')}</span>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        {/* Full-size image */}
                        <img
                            src={photos[currentIndex].url}
                            alt={`Photo ${currentIndex + 1} of ${photos.length}`}
                            className="max-h-[80vh] max-w-full object-contain"
                        />

                        {/* Next button */}
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={goNext}
                                        aria-label={t('app.tests.lightbox_next')}
                                        disabled={photos.length <= 1}
                                        className="shrink-0"
                                    >
                                        <ChevronRightIcon className="size-5" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <span>{t('app.tests.lightbox_next')}</span>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>

                    {/* Counter */}
                    <p className="text-[14px] text-muted-foreground">
                        {currentIndex + 1}/{photos.length}
                    </p>
                </DialogContent>
            </Dialog>
        </>
    );
}
