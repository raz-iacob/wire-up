import.meta.glob(["../images/**/*"]);

import SparkMD5 from "spark-md5";

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

    // Reset form state after a successful submit (no validation errors)
    component.$wire.intercept(action, ({ onSuccess }) => {
        onSuccess(({ payload }) => {
            const noErrors = payload.snapshot.memo.errors.length === 0;
            if (noErrors) initialState = getState();
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
