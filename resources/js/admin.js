import.meta.glob(["../images/**/*"], {
    eager: true,
    query: "?url",
    import: "default",
});

import SparkMD5 from "spark-md5";

import Cropper from "cropperjs";

import * as pdfjsLib from "pdfjs-dist";
import pdfWorker from "pdfjs-dist/build/pdf.worker.min.mjs?url";

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;

Livewire.directive("warn-dirty", ({ el, directive, component, cleanup }) => {
    const action = el.getAttribute("wire:submit");
    const message =
        directive.expression || "You have unsaved changes. Leave anyway?";

    const getState = () => JSON.stringify(component.ephemeral);
    let initialState = getState();

    const isDirty = () => getState() !== initialState;

    // Reset the dirty baseline after a successful submit (no validation errors).
    // A message interceptor gives onSuccess the response payload, and onRender
    // runs after the new state is merged so the baseline matches the saved state.
    component.$wire.interceptMessage(action, ({ onSuccess }) => {
        onSuccess(({ payload, onRender }) => {
            if (payload.snapshot.memo.errors.length !== 0) {
                return;
            }

            onRender(() => {
                initialState = getState();
            });
        });
    });

    const handleBeforeUnload = (e) => {
        if (!isDirty()) return;
        e.preventDefault();
        e.returnValue = ""; // required for browser dialog
    };

    const handleNavigate = (e) => {
        if (isDirty() && !confirm(message)) e.preventDefault();
    };

    // Register listeners
    window.addEventListener("beforeunload", handleBeforeUnload);
    document.addEventListener("livewire:navigate", handleNavigate);

    // Clean up when component is torn down
    cleanup(() => {
        window.removeEventListener("beforeunload", handleBeforeUnload);
        document.removeEventListener("livewire:navigate", handleNavigate);
    });
});

document.addEventListener("alpine:init", () => {
    Alpine.data("mediaLibrary", ($wire) => {
        return {
            lastSelectedIndex: null,
            shiftPressed: false,
            hoverIndex: null,
            init() {
                window.addEventListener("keydown", (e) => {
                    if (e.key === "Shift") this.shiftPressed = true;
                });
                window.addEventListener("keyup", (e) => {
                    if (e.key === "Shift") this.shiftPressed = false;
                });
            },
            async select(id, index, event) {
                this.shiftPressed = event.shiftKey;

                if (this.shiftPressed && this.lastSelectedIndex !== null) {
                    const start = Math.min(this.lastSelectedIndex, index);
                    const end = Math.max(this.lastSelectedIndex, index);

                    const ids = [];
                    const elements =
                        this.$root.querySelectorAll("[data-media-id]");

                    elements.forEach((el) => {
                        const i = parseInt(el.dataset.index);
                        if (!isNaN(i) && i >= start && i <= end) {
                            const mId = parseInt(el.dataset.mediaId);
                            if (!isNaN(mId)) ids.push(mId);
                        }
                    });

                    if (ids.length > 0) {
                        await $wire.selectMediaRange(ids);
                    }

                    this.lastSelectedIndex = index;
                    return;
                }

                this.lastSelectedIndex = index;
                await $wire.selectMediaById(id);
            },
            isInRange(index) {
                if (
                    !this.shiftPressed ||
                    this.lastSelectedIndex === null ||
                    this.hoverIndex === null
                )
                    return false;
                const start = Math.min(this.lastSelectedIndex, this.hoverIndex);
                const end = Math.max(this.lastSelectedIndex, this.hoverIndex);
                return index >= start && index <= end;
            },
        };
    });

    // Crops a single media item across one or more named variants (e.g. desktop,
    // mobile). Selection geometry is read back from the rendered DOM as ratios so
    // it stays accurate regardless of the resolution the editing image is shown
    // at, then mapped onto the media's natural pixel dimensions for the
    // server-side ImageService. Each variant's crop is staged in `pending` and
    // the whole set is committed together when the user clicks Update.
    Alpine.data("mediaCropper", ($wire) => {
        return {
            cropper: null,
            open: false,
            index: null,
            item: null,
            variants: [],
            activeVariant: null,
            pending: {},
            dims: { w: 0, h: 0 },

            start(index, item, crops) {
                this.index = index;
                this.item = item;
                this.variants = Object.entries(crops ?? {}).map(
                    ([key, def]) => ({
                        key,
                        label: def.label ?? key,
                        w: def.w ?? null,
                        h: def.h ?? null,
                        ratio:
                            def.ratio ??
                            (def.w && def.h ? def.w / def.h : null),
                        q: def.q ?? 80,
                        fm: def.fm ?? "jpg",
                    }),
                );
                this.pending = { ...(item?.crop ?? {}) };
                this.activeVariant = this.variants[0]?.key ?? null;
                this.open = true;

                this.$nextTick(() => this.mount());
            },

            current() {
                return (
                    this.variants.find((v) => v.key === this.activeVariant) ??
                    null
                );
            },

            switchTo(key) {
                if (key === this.activeVariant) {
                    return;
                }

                this.capture();
                this.activeVariant = key;
                this.applyVariant();
            },

            mount() {
                const image = this.$refs.cropImage;

                if (!image) {
                    return;
                }

                this.teardown();

                this.cropper = new Cropper(image);

                const canvas = this.cropper.getCropperCanvas();

                if (canvas) {
                    // Full width of the modal, with the height following the
                    // image's aspect ratio but never taller than 600px.
                    const naturalWidth = Number(this.item?.width) || 0;
                    const naturalHeight = Number(this.item?.height) || 0;
                    const containerWidth =
                        canvas.parentElement?.clientWidth || canvas.clientWidth;

                    const fitHeight =
                        naturalWidth && naturalHeight && containerWidth
                            ? (containerWidth * naturalHeight) / naturalWidth
                            : 600;

                    canvas.style.width = "100%";
                    canvas.style.height =
                        Math.min(Math.round(fitHeight), 600) + "px";
                }

                const selection = this.cropper.getCropperSelection();

                if (selection) {
                    selection.initialCoverage = 0.8;
                    selection.addEventListener("change", () =>
                        this.updateDims(),
                    );
                }

                const cropperImage = this.cropper.getCropperImage();

                if (cropperImage) {
                    cropperImage.$ready().then(() => {
                        cropperImage.$center("contain");
                        this.applyVariant();
                    });
                }
            },

            // Applies the active variant's aspect ratio to the existing cropper
            // and restores that variant's saved selection (or resets to a default
            // when nothing is saved). Reused on open and on tab switch, so each
            // variant keeps its own crop without recreating the cropper.
            applyVariant() {
                const selection = this.cropper?.getCropperSelection();
                const variant = this.current();

                if (!selection) {
                    return;
                }

                if (variant?.ratio) {
                    selection.aspectRatio = variant.ratio;
                }

                const saved = this.pending?.[this.activeVariant];

                if (
                    !this.restoreSelection(selection, saved) &&
                    !this.centerSelection(selection)
                ) {
                    selection.$reset();
                }

                this.updateDims();
            },

            // Places the largest selection of the active variant's aspect ratio
            // that fits inside the displayed image, centered on it (so it spans
            // either the full width or the full height).
            centerSelection(selection) {
                const image = this.cropper?.getCropperImage();
                const canvas = this.cropper?.getCropperCanvas();
                const variant = this.current();

                if (!image || !canvas) {
                    return false;
                }

                const imageRect = image.getBoundingClientRect();
                const canvasRect = canvas.getBoundingClientRect();

                if (!imageRect.width || !imageRect.height) {
                    return false;
                }

                const imageAspect = imageRect.width / imageRect.height;
                const ratio = variant?.ratio || imageAspect;

                let width;
                let height;

                if (ratio >= imageAspect) {
                    width = imageRect.width;
                    height = width / ratio;
                } else {
                    height = imageRect.height;
                    width = height * ratio;
                }

                selection.$change(
                    imageRect.left -
                        canvasRect.left +
                        (imageRect.width - width) / 2,
                    imageRect.top -
                        canvasRect.top +
                        (imageRect.height - height) / 2,
                    width,
                    height,
                );

                return true;
            },

            get ratioLabel() {
                const ratio = this.current()?.ratio;

                return ratio ? ratio.toFixed(2) : "—";
            },

            // Re-projects a saved crop (stored in natural image pixels) back onto
            // the current on-screen image so the previous selection is visible
            // when the cropper is reopened. Returns false when there is nothing
            // to restore.
            restoreSelection(selection, saved) {
                const image = this.cropper?.getCropperImage();
                const canvas = this.cropper?.getCropperCanvas();
                const naturalWidth = Number(this.item?.width) || 0;
                const naturalHeight = Number(this.item?.height) || 0;

                if (
                    !image ||
                    !canvas ||
                    !naturalWidth ||
                    !naturalHeight ||
                    !saved?.crop_w ||
                    !saved?.crop_h
                ) {
                    return false;
                }

                const imageRect = image.getBoundingClientRect();
                const canvasRect = canvas.getBoundingClientRect();
                const scaleX = imageRect.width / naturalWidth;
                const scaleY = imageRect.height / naturalHeight;

                selection.$change(
                    imageRect.left - canvasRect.left + saved.crop_x * scaleX,
                    imageRect.top - canvasRect.top + saved.crop_y * scaleY,
                    saved.crop_w * scaleX,
                    saved.crop_h * scaleY,
                );

                return true;
            },

            teardown() {
                if (this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }
            },

            close() {
                this.teardown();
                this.open = false;
                this.index = null;
                this.item = null;
                this.variants = [];
                this.activeVariant = null;
                this.pending = {};
                this.dims = { w: 0, h: 0 };
            },

            computeCrop() {
                const selection = this.cropper?.getCropperSelection();
                const image = this.cropper?.getCropperImage();
                const variant = this.current();

                if (!selection || !image || !variant) {
                    return null;
                }

                const imageRect = image.getBoundingClientRect();
                const selectionRect = selection.getBoundingClientRect();

                if (!imageRect.width || !imageRect.height) {
                    return null;
                }

                const naturalWidth =
                    Number(this.item?.width) || imageRect.width;
                const naturalHeight =
                    Number(this.item?.height) || imageRect.height;

                const clamp = (value, max) =>
                    Math.max(0, Math.min(Math.round(value), max));

                const cropX = clamp(
                    ((selectionRect.left - imageRect.left) / imageRect.width) *
                        naturalWidth,
                    naturalWidth,
                );
                const cropY = clamp(
                    ((selectionRect.top - imageRect.top) / imageRect.height) *
                        naturalHeight,
                    naturalHeight,
                );
                const cropW = clamp(
                    (selectionRect.width / imageRect.width) * naturalWidth,
                    naturalWidth - cropX,
                );
                const cropH = clamp(
                    (selectionRect.height / imageRect.height) * naturalHeight,
                    naturalHeight - cropY,
                );

                if (cropW <= 0 || cropH <= 0) {
                    return null;
                }

                return {
                    crop_w: cropW,
                    crop_h: cropH,
                    crop_x: cropX,
                    crop_y: cropY,
                    w: variant.w,
                    h: variant.h,
                    q: variant.q,
                    fm: variant.fm,
                };
            },

            updateDims() {
                const crop = this.computeCrop();

                if (crop) {
                    this.dims = { w: crop.crop_w, h: crop.crop_h };
                }
            },

            capture() {
                const crop = this.computeCrop();

                if (crop && this.activeVariant) {
                    this.pending[this.activeVariant] = crop;
                }
            },

            async apply() {
                this.capture();

                if (this.index !== null) {
                    await $wire.setCrops(this.index, this.pending);
                }

                this.close();
            },
        };
    });
});

const analyzeFile = async (file) => {
    const base = {
        name: file.name,
        size: file.size,
        mime: file.type,
        width: null,
        height: null,
        duration: null,
        thumbnail: null,
        error: null,
    };

    try {
        if (file.type.startsWith("image/")) {
            return { ...base, ...(await analyzeImage(file)) };
        }
        if (file.type.startsWith("audio/")) {
            return { ...base, ...(await analyzeAudio(file)) };
        }
        if (file.type.startsWith("video/")) {
            return { ...base, ...(await analyzeVideo(file)) };
        }
        if (file.type === "application/pdf") {
            return { ...base, ...(await analyzePdf(file)) };
        }
    } catch (err) {
        return { ...base, error: err.message || "unknown" };
    }

    return { ...base, type: "other" };
};

const analyzeImage = async (file) => {
    if (file.type === "image/svg+xml") return await analyzeSvg(file);

    return new Promise((resolve) => {
        const img = new Image();
        const url = URL.createObjectURL(file);

        const cleanup = () => URL.revokeObjectURL(url);

        img.onload = () => {
            cleanup();
            resolve({
                type: "image",
                width: img.width,
                height: img.height,
            });
        };

        img.onerror = () => {
            cleanup();
            resolve({
                type: "image",
                width: null,
                height: null,
                error: "failed to load image",
            });
        };

        img.src = url;
    });
};

const analyzeSvg = async (file) => {
    const text = await file.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, "image/svg+xml");
    const svg = doc.documentElement;

    let width = parseInt(svg.getAttribute("width"));
    let height = parseInt(svg.getAttribute("height"));

    if ((!width || !height) && svg.hasAttribute("viewBox")) {
        const [, , w, h] = svg.getAttribute("viewBox").split(" ").map(Number);

        width = width || w || null;
        height = height || h || null;
    }

    return {
        type: "svg",
        width,
        height,
        duration: null,
        thumbnail: null,
    };
};

const analyzeAudio = async (file) => {
    return new Promise((resolve) => {
        const audio = document.createElement("audio");
        audio.preload = "metadata";
        const url = URL.createObjectURL(file);

        let resolved = false;

        const cleanup = () => {
            URL.revokeObjectURL(url);
            audio.remove();
        };

        const fail = () => {
            if (resolved) return;
            resolved = true;
            cleanup();
            resolve({
                type: "audio",
                duration: null,
                thumbnail: null,
                error: "failed to load audio",
            });
        };

        audio.onloadedmetadata = async () => {
            if (resolved) return;

            const duration = isFinite(audio.duration)
                ? Math.round(audio.duration)
                : null;

            const thumbnail = await createAudioWaveformThumbnail(file);

            resolved = true;
            cleanup();
            resolve({
                type: "audio",
                duration,
                thumbnail,
            });
        };

        audio.onerror = audio.onabort = fail;

        setTimeout(fail, 8000);

        audio.src = url;
        audio.load();
    });
};

const createAudioWaveformThumbnail = async (
    file,
    width = 500,
    height = 100,
) => {
    const arrayBuffer = await file.arrayBuffer();

    const audioCtx = new (
        window.OfflineAudioContext || window.webkitOfflineAudioContext
    )(1, 44100, 44100);
    const audioData = await audioCtx.decodeAudioData(arrayBuffer);

    const raw = audioData.getChannelData(0);
    const samples = 1000;
    const blockSize = Math.floor(raw.length / samples);
    const peaks = [];

    for (let i = 0; i < samples; i++) {
        const start = i * blockSize;
        let sum = 0;

        for (let j = 0; j < blockSize; j++) {
            sum += Math.abs(raw[start + j] ?? 0);
        }

        peaks.push(sum / blockSize);
    }

    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d");

    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, width, height);

    ctx.strokeStyle = "#000";
    ctx.lineWidth = 1;
    ctx.beginPath();

    for (let i = 0; i < peaks.length; i++) {
        const x = (i / peaks.length) * width;
        const y = height / 2;
        const peak = peaks[i] * (height / 2);

        ctx.moveTo(x, y - peak);
        ctx.lineTo(x, y + peak);
    }

    ctx.stroke();

    return canvas.toDataURL("image/png");
};

const analyzeVideo = (file) => {
    return new Promise((resolve) => {
        const video = document.createElement("video");
        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");
        const url = URL.createObjectURL(file);

        video.preload = "metadata";
        video.muted = true;

        const fail = () => {
            cleanup();
            resolve({
                type: "video",
                width: null,
                height: null,
                duration: null,
                thumbnail: null,
                error: "failed to load video",
            });
        };

        const cleanup = () => {
            try {
                URL.revokeObjectURL(url);
            } catch {}
            try {
                video.remove();
            } catch {}
        };

        video.onerror = fail;
        video.onabort = fail;

        video.onloadedmetadata = () => {
            // identical to your working snippet
            const width = video.videoWidth;
            const height = video.videoHeight;
            const duration = Math.floor(video.duration);

            const maxSize = 300;
            const scale = Math.min(maxSize / width, maxSize / height);

            canvas.width = width * scale;
            canvas.height = height * scale;

            // identical: seek to early keyframe
            video.currentTime = Math.min(1, duration * 0.1);

            video.onseeked = () => {
                try {
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const thumbnail = createThumbnail(canvas);
                    cleanup();

                    resolve({
                        type: "video",
                        width,
                        height,
                        duration,
                        thumbnail,
                        error: null,
                    });
                } catch (err) {
                    fail();
                }
            };
        };

        video.src = url;
    });
};

const analyzePdf = async (file) => {
    try {
        const buffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: buffer }).promise;
        const page = await pdf.getPage(1);
        const viewport = page.getViewport({ scale: 1.5 });

        const canvas = document.createElement("canvas");
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        const ctx = canvas.getContext("2d");
        await page.render({ canvasContext: ctx, viewport }).promise;

        return {
            type: "pdf",
            width: Math.round(viewport.width),
            height: Math.round(viewport.height),
            thumbnail: createThumbnail(canvas),
        };
    } catch (err) {
        return {
            type: "pdf",
            width: null,
            height: null,
            thumbnail: null,
            error: err.message || "corrupted pdf",
        };
    }
};

const createThumbnail = (canvas, maxSize = 300) => {
    const ratio = Math.min(maxSize / canvas.width, maxSize / canvas.height);
    const thumbWidth = Math.round(canvas.width * ratio);
    const thumbHeight = Math.round(canvas.height * ratio);

    const thumbCanvas = document.createElement("canvas");
    thumbCanvas.width = thumbWidth;
    thumbCanvas.height = thumbHeight;

    const ctx = thumbCanvas.getContext("2d");
    ctx.drawImage(canvas, 0, 0, thumbWidth, thumbHeight);

    return thumbCanvas.toDataURL("image/jpeg", 0.8);
};

const fileMd5 = async (file) => {
    const arrayBuffer = await file.arrayBuffer();
    return SparkMD5.ArrayBuffer.hash(arrayBuffer);
};

window.analyzeFile = analyzeFile;
window.fileMd5 = fileMd5;
