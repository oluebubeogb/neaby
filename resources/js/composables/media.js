/**
 * Client-side media shrinking for chat, so we upload (and the other person
 * downloads) as little as possible.
 */

/** Downscale to `max` px on the long edge and re-encode as WebP (JPEG fallback). */
export async function compressImage(file, { max = 1280, quality = 0.75 } = {}) {
    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch (_) {
        bitmap = await new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = URL.createObjectURL(file);
        });
    }
    const w0 = bitmap.width;
    const h0 = bitmap.height;
    const k = Math.min(1, max / Math.max(w0, h0));
    const w = Math.max(1, Math.round(w0 * k));
    const h = Math.max(1, Math.round(h0 * k));
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(bitmap, 0, 0, w, h);
    bitmap.close?.();
    const toBlob = (type) => new Promise((r) => canvas.toBlob(r, type, quality));
    let blob = await toBlob('image/webp');
    if (!blob || blob.type !== 'image/webp') blob = await toBlob('image/jpeg');
    // Never upload something bigger than the original.
    if (file.size && blob.size > file.size && /image\/(jpe?g|png|webp)/.test(file.type) && k === 1) blob = file;
    const ext = blob.type === 'image/webp' ? 'webp' : blob.type === 'image/png' ? 'png' : 'jpg';
    return { file: new File([blob], `photo.${ext}`, { type: blob.type }), w, h };
}

/** Best low-bitrate voice container the browser can record. */
export function voiceMime() {
    if (typeof MediaRecorder === 'undefined') return null;
    return ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/webm'].find((t) => MediaRecorder.isTypeSupported?.(t)) ?? '';
}

export function formatClock(s) {
    s = Math.max(0, Math.round(s || 0));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
