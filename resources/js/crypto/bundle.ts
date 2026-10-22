export interface GrantDocument {
  doc_id: string;
  doc_type: string;
  name: string;
  content_b64: string;
}

export interface GrantBundle {
  bundle: string;   // base64 ciphertext
  iv: string;       // base64 IV (12 bytes)
  keyB64: string;   // base64 AES-256 key (32 bytes)
}

/**
 * FR-3.3 / FR-GRANT-02: Tạo gói bundle mã hoá cho các tài liệu đã duyệt.
 * Mỗi bundle dùng một khoá AES-256-GCM mới, IV 12 byte ngẫu nhiên.
 */
export async function createGrantBundle(documents: GrantDocument[]): Promise<GrantBundle> {
  const payload = JSON.stringify(documents);
  const enc = new TextEncoder();
  const data = enc.encode(payload);

  const iv = crypto.getRandomValues(new Uint8Array(12));
  const key = await crypto.subtle.generateKey(
    { name: 'AES-GCM', length: 256 },
    true,
    ['encrypt'],
  );

  const ciphertext = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv },
    key,
    data,
  );

  const keyBuffer = await crypto.subtle.exportKey('raw', key);
  const keyB64 = btoa(String.fromCharCode(...new Uint8Array(keyBuffer)));
  const ivB64 = btoa(String.fromCharCode(...iv));
  const bundleB64 = btoa(String.fromCharCode(...new Uint8Array(ciphertext)));

  return { bundle: bundleB64, iv: ivB64, keyB64 };
}

/**
 * Giải mã bundle lấy lại danh sách tài liệu.
 */
export async function openGrantBundle(
  bundleB64: string,
  ivB64: string,
  keyB64: string,
): Promise<GrantDocument[]> {
  const dec = new TextDecoder();
  const ciphertext = Uint8Array.from(atob(bundleB64), (c) => c.charCodeAt(0));
  const iv = Uint8Array.from(atob(ivB64), (c) => c.charCodeAt(0));

  const key = await crypto.subtle.generateKey(
    { name: 'AES-GCM', length: 256 },
    true,
    ['decrypt'],
  );

  const keyBuffer = await crypto.subtle.exportKey('raw', key);
  // Re-import the provided key bytes instead of generating a new one.
  const providedKey = await crypto.subtle.importKey(
    'raw',
    Uint8Array.from(atob(keyB64), (c) => c.charCodeAt(0)),
    { name: 'AES-GCM', length: 256 },
    false,
    ['decrypt'],
  );

  const plaintext = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv },
    providedKey,
    ciphertext,
  );

  return JSON.parse(dec.decode(plaintext));
}
