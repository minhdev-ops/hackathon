import { bytesToBase64 } from './base64.js';

const encoder = new TextEncoder();

export async function createGrantBundle(documents) {
    const keyBytes = crypto.getRandomValues(new Uint8Array(32));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const key = await crypto.subtle.importKey(
        'raw',
        keyBytes,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt'],
    );

    const payload = encoder.encode(JSON.stringify({ documents }));
    const bundle = new Uint8Array(await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv, tagLength: 128 },
        key,
        payload,
    ));

    return {
        bundle,
        iv: bytesToBase64(iv),
        keyB64: bytesToBase64(keyBytes),
    };
}
