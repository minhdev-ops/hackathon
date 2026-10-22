import test from 'node:test';
import assert from 'node:assert/strict';
import { base64ToBytes } from '../../resources/js/crypto/base64.js';
import { createGrantBundle } from '../../resources/js/crypto/bundle.js';
import {
    decryptDocument,
    decryptTitle,
    encryptDocument,
    encryptTitle,
    lockVault,
    unlockVault,
} from '../../resources/js/crypto/vault.js';

const DEMO_SALT = 'dHJ1c3RnYXRlLWRlbW8tdmF1bHQtc2FsdC0wMDAx';

test('grant bundle is AES-GCM with the payload shape the backend decodes', async () => {
    const documents = [
        {
            doc_id: 'doc-1',
            doc_type: 'id_card',
            name: 'CMND Lan',
            content_b64: 'JVBERi0xLjQ=',
        },
    ];

    const { bundle, iv, keyB64 } = await createGrantBundle(documents);

    assert.ok(bundle instanceof Uint8Array);
    assert.equal(typeof iv, 'string');
    assert.equal(base64ToBytes(keyB64).length, 32);
    assert.equal(base64ToBytes(iv).length, 12);

    const key = await crypto.subtle.importKey(
        'raw',
        base64ToBytes(keyB64),
        { name: 'AES-GCM', length: 256 },
        false,
        ['decrypt'],
    );
    const plaintext = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: base64ToBytes(iv), tagLength: 128 },
        key,
        bundle,
    );

    assert.deepEqual(JSON.parse(new TextDecoder().decode(plaintext)), { documents });
});

test('vault round-trips document and title with the demo passphrase', async () => {
    lockVault();
    await unlockVault('password', DEMO_SALT);

    const doc = new TextEncoder().encode('%PDF-1.4 demo');
    const encrypted = await encryptDocument(doc);
    const decrypted = await decryptDocument(encrypted.ciphertext, encrypted.iv);

    assert.deepEqual(new Uint8Array(decrypted), doc);
    assert.equal(encrypted.sha256.length, 64);

    const titleCt = await encryptTitle('CMND Lan');
    assert.equal(await decryptTitle(titleCt), 'CMND Lan');
    assert.ok(base64ToBytes(titleCt).length > 12);
});
