export function bytesToBase64(bytes) {
    const view = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let binary = '';
    const chunkSize = 0x8000;

    for (let offset = 0; offset < view.length; offset += chunkSize) {
        binary += String.fromCharCode(...view.subarray(offset, offset + chunkSize));
    }

    return btoa(binary);
}

export function base64ToBytes(value) {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}
