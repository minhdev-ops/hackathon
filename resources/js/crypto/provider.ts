export type EncMode = 'tide' | 'fallback';

export interface CryptoProvider {
  mode: EncMode;
  encryptDocument(file: ArrayBuffer): Promise<{
    ciphertext: ArrayBuffer;
    iv: Uint8Array;
    sha256: string;
  }>;
  decryptDocument(ciphertext: ArrayBuffer, iv: Uint8Array): Promise<ArrayBuffer>;
  encryptText(text: string): Promise<string>;
  decryptText(b64: string): Promise<string>;
}

export function createCryptoProvider(mode: EncMode = 'fallback'): CryptoProvider {
  if (mode === 'tide') {
    return new TideCryptoProvider();
  }
  return new FallbackCryptoProvider();
}

class FallbackCryptoProvider implements CryptoProvider {
  readonly mode: EncMode = 'fallback';

  async encryptDocument(file: ArrayBuffer) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const key = await crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt'],
    );
    const ciphertext = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      key,
      file,
    );
    const sha256 = await crypto.subtle.digest('SHA-256', file);
    const hashArray = Array.from(new Uint8Array(sha256));
    const sha256Hex = hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');

    return { ciphertext, iv, sha256: sha256Hex };
  }

  async decryptDocument(ciphertext: ArrayBuffer, iv: Uint8Array) {
    const key = await crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['decrypt'],
    );
    return crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, ciphertext);
  }

  async encryptText(text: string) {
    const enc = new TextEncoder();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const key = await crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt'],
    );
    const ciphertext = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      key,
      enc.encode(text),
    );
    return btoa(String.fromCharCode(...new Uint8Array(ciphertext)));
  }

  async decryptText(b64: string) {
    const enc = new TextEncoder();
    const dec = new TextDecoder();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const key = await crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['decrypt'],
    );
    const ciphertext = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
    const plaintext = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv },
      key,
      ciphertext,
    );
    return dec.decode(plaintext);
  }
}

class TideCryptoProvider implements CryptoProvider {
  readonly mode: EncMode = 'tide';

  async encryptDocument() {
    throw new Error('TideCloak SDK chưa được tích hợp (V1 chưa đạt). Dùng fallback.');
  }

  async decryptDocument() {
    throw new Error('TideCloak SDK chưa được tích hợp (V1 chưa đạt). Dùng fallback.');
  }

  async encryptText() {
    throw new Error('TideCloak SDK chưa được tích hợp (V1 chưa đạt). Dùng fallback.');
  }

  async decryptText() {
    throw new Error('TideCloak SDK chưa được tích hợp (V1 chưa đạt). Dùng fallback.');
  }
}
