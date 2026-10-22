import { base64ToBytes, bytesToBase64 } from './base64.js';

const encoder = new TextEncoder();
const decoder = new TextDecoder();

let vaultKey = null;

export function isVaultUnlocked() {
    return vaultKey !== null;
}

export function lockVault() {
    vaultKey = null;
}

export async function unlockVault(passphrase, saltB64) {
    if (!passphrase || !saltB64) {
        throw new Error('Thiếu mật khẩu két hoặc salt.');
    }

    const salt = base64ToBytes(saltB64);
    const baseKey = await crypto.subtle.importKey(
        'raw',
        encoder.encode(passphrase),
        'PBKDF2',
        false,
        ['deriveKey'],
    );

    vaultKey = await crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt,
            iterations: 210_000,
            hash: 'SHA-256',
        },
        baseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

function requireKey() {
    if (vaultKey === null) {
        throw new Error('Két chưa được mở.');
    }

    return vaultKey;
}

export async function decryptTitle(titleCt) {
    const raw = base64ToBytes(titleCt);
    const iv = raw.subarray(0, 12);
    const ciphertext = raw.subarray(12);
    const plaintext = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv, tagLength: 128 },
        requireKey(),
        ciphertext,
    );

    return decoder.decode(plaintext);
}

export async function decryptDocument(ciphertext, ivB64) {
    const iv = base64ToBytes(ivB64);
    const plaintext = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv, tagLength: 128 },
        requireKey(),
        ciphertext,
    );

    return plaintext;
}

export async function sha256Hex(buffer) {
    const digest = await crypto.subtle.digest('SHA-256', buffer);

    return [...new Uint8Array(digest)]
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

export async function encryptDocument(plaintext) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv, tagLength: 128 },
        requireKey(),
        plaintext,
    );

    return {
        ciphertext: new Uint8Array(ciphertext),
        iv: bytesToBase64(iv),
        sha256: await sha256Hex(plaintext),
    };
}

export async function encryptTitle(title) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ciphertext = new Uint8Array(await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv, tagLength: 128 },
        requireKey(),
        encoder.encode(title),
    ));

    const combined = new Uint8Array(iv.length + ciphertext.length);
    combined.set(iv);
    combined.set(ciphertext, iv.length);

    return bytesToBase64(combined);
}
