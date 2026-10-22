#!/usr/bin/env node
/**
 * Generate test data for TrustGate API demo
 * Uses Node.js WebCrypto API (Node 18+) for client-side encryption
 * Outputs: test files + Postman environment values
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const OUTPUT_DIR = '/tmp/trustgate-demo';
const TEST_PLAINTEXT = 'Test document for TrustGate demo - 身份证复印件 - 收入证明 - 租赁记录';
const DOC_TITLE = '身份证复印件';

async function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

function generateIV(bytes = 16) {
  return crypto.randomBytes(bytes);
}

function base64Encode(buf) {
  return Buffer.from(buf).toString('base64');
}

function hexEncode(buf) {
  return Buffer.from(buf).toString('hex');
}

async function sha256(data) {
  const hash = crypto.createHash('sha256');
  hash.update(data);
  return hash.digest();
}

async function encryptAESGCM(plaintext, key, iv) {
  const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
  const ciphertext = Buffer.concat([cipher.update(plaintext), cipher.final()]);
  const authTag = cipher.getAuthTag();
  return { ciphertext, authTag };
}

async function deriveKeyPBKDF2(password, salt, iterations = 100000, keyLength = 32) {
  return new Promise((resolve, reject) => {
    crypto.pbkdf2(password, salt, iterations, keyLength, 'sha256', (err, key) => {
      if (err) reject(err);
      else resolve(key);
    });
  });
}

async function main() {
  await ensureDir(OUTPUT_DIR);

  console.log('🔐 Generating TrustGate demo test data...\n');

  // 1. Create plaintext test file
  const plaintextPath = path.join(OUTPUT_DIR, 'test-document-plaintext.txt');
  fs.writeFileSync(plaintextPath, TEST_PLAINTEXT);
  console.log(`✅ Plaintext: ${plaintextPath}`);

  // 2. Create a simple PDF (using minimal PDF structure)
  const pdfContent = `%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>
endobj
4 0 obj
<< /Length 44 >>
stream
BT /F1 12 Tf 100 700 Td (${TEST_PLAINTEXT}) Tj ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000206 00000 n
0000000301 00000 n
trailer
<< /Size 6 /Root 1 0 R >>
startxref
380
%%EOF`;
  const pdfPath = path.join(OUTPUT_DIR, 'test-document.pdf');
  fs.writeFileSync(pdfPath, pdfContent);
  console.log(`✅ Test PDF: ${pdfPath} (${fs.statSync(pdfPath).size} bytes)`);

  // 3. Generate encryption parameters
  const fileIV = generateIV(16);
  const fileKey = crypto.randomBytes(32);
  const fileSHA256 = await sha256(Buffer.from(TEST_PLAINTEXT, 'utf8'));
  
  // Encrypt file
  const { ciphertext: fileCiphertext, authTag: fileTag } = await encryptAESGCM(
    Buffer.from(TEST_PLAINTEXT, 'utf8'), fileKey, fileIV
  );
  const encryptedFile = Buffer.concat([fileCiphertext, fileTag]);
  const encryptedFilePath = path.join(OUTPUT_DIR, 'test-document.enc');
  fs.writeFileSync(encryptedFilePath, encryptedFile);
  console.log(`✅ Encrypted file: ${encryptedFilePath} (${encryptedFile.length} bytes)`);

  // Encrypt title
  const titleIV = generateIV(12);
  const { ciphertext: titleCiphertext, authTag: titleTag } = await encryptAESGCM(
    Buffer.from(DOC_TITLE, 'utf8'), fileKey, titleIV
  );
  const encryptedTitle = Buffer.concat([titleCiphertext, titleTag]);
  const titleCT = base64Encode(encryptedTitle);

  // 4. Generate grant bundle (for grant approve)
  const grantKey = crypto.randomBytes(32);
  const bundleIV = generateIV(12);
  
  // Create bundle payload (JSON with document keys)
  const bundlePayload = {
    documents: [
      {
        id: '550e8400-e29b-41d4-a716-446655440000',
        key: base64Encode(crypto.randomBytes(32)),
        iv: base64Encode(generateIV(12))
      }
    ],
    grant_key: base64Encode(grantKey)
  };
  const bundlePlaintext = Buffer.from(JSON.stringify(bundlePayload), 'utf8');
  const { ciphertext: bundleCiphertext, authTag: bundleTag } = await encryptAESGCM(
    bundlePlaintext, grantKey, bundleIV
  );
  const encryptedBundle = Buffer.concat([bundleCiphertext, bundleTag]);
  const bundlePath = path.join(OUTPUT_DIR, 'grant-bundle.bin');
  fs.writeFileSync(bundlePath, encryptedBundle);
  console.log(`✅ Grant bundle: ${bundlePath} (${encryptedBundle.length} bytes)`);

  // 5. Generate agent token request data
  const agentKey = crypto.randomBytes(32);
  const agentIV = generateIV(12);

  // 6. Output Postman environment values
  const envValues = {
    BASE_URL: 'http://localhost:8000',
    TEST_FILE_PATH: encryptedFilePath,
    TEST_BUNDLE_PATH: bundlePath,
    SAMPLE_IV: hexEncode(fileIV),
    SAMPLE_SHA256: hexEncode(fileSHA256),
    SAMPLE_TITLE_CT: titleCT,
    SAMPLE_GRANT_KEY_B64: base64Encode(grantKey),
    SAMPLE_BUNDLE_IV: base64Encode(bundleIV),
    DOC_TYPE_ID_CARD: 'id_card',
    PLAYBOOK_SLUG: 'kyc_verification',
    AGENT_CLIENT_ID: 'demo-agent-001',
    OWNER_EMAIL: 'owner@example.com',
    OWNER_PASSWORD: 'password',
    ADMIN_EMAIL: 'admin@example.com',
    ADMIN_PASSWORD: 'password',
    // These will be populated after API calls
    AUTH_TOKEN: '',
    ADMIN_TOKEN: '',
    AGENT_TOKEN: '',
    DEMO_TOKEN: '',
    DOCUMENT_ID: '',
    DOCUMENT_ID_2: '',
    GRANT_ID: '',
    TASK_ID: '',
    CR_ID: ''
  };

  const envPath = path.join(OUTPUT_DIR, 'postman-environment-demo.json');
  const postmanEnv = {
    id: 'trustgate-demo-environment',
    name: 'TrustGate Demo Environment (Auto-generated)',
    values: Object.entries(envValues).map(([key, value]) => ({
      key,
      value,
      enabled: true,
      type: 'default'
    })),
    _postman_variable_scope: 'environment',
    _postman_exported_at: new Date().toISOString(),
    _postman_exported_using: 'generate-test-data.js'
  };
  fs.writeFileSync(envPath, JSON.stringify(postmanEnv, null, 2));
  console.log(`\n✅ Postman environment: ${envPath}`);

  // 7. Print summary for manual copy-paste
  console.log('\n📋 Copy these into Postman environment variables:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`TEST_FILE_PATH: ${encryptedFilePath}`);
  console.log(`TEST_BUNDLE_PATH: ${bundlePath}`);
  console.log(`SAMPLE_IV: ${hexEncode(fileIV)}`);
  console.log(`SAMPLE_SHA256: ${hexEncode(fileSHA256)}`);
  console.log(`SAMPLE_TITLE_CT: ${titleCT}`);
  console.log(`SAMPLE_GRANT_KEY_B64: ${base64Encode(grantKey)}`);
  console.log(`SAMPLE_BUNDLE_IV: ${base64Encode(bundleIV)}`);
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

  // 8. Generate curl commands for quick testing
  const curlPath = path.join(OUTPUT_DIR, 'curl-commands.sh');
  const curlCommands = `#!/bin/bash
# TrustGate API Demo - Quick curl commands
# Run after: php artisan serve (or your server)

BASE_URL="http://localhost:8000"
AUTH_TOKEN=""  # Fill after login

# 1. Login (adjust endpoint as needed)
# curl -X POST "$BASE_URL/login" \\
#   -H "Content-Type: application/json" \\
#   -d '{"email":"owner@example.com","password":"password"}'
# AUTH_TOKEN=\$(curl -s -X POST "$BASE_URL/login" -H "Content-Type: application/json" -d '{"email":"owner@example.com","password":"password"}' | jq -r '.access_token')

# 2. Upload document
curl -X POST "$BASE_URL/api/v1/documents" \\
  -H "Authorization: Bearer \$AUTH_TOKEN" \\
  -F "file=@${encryptedFilePath}" \\
  -F "iv=${hexEncode(fileIV)}" \\
  -F "sha256=${hexEncode(fileSHA256)}" \\
  -F "doc_type=id_card" \\
  -F "title_ct=${titleCT}" \\
  -F "enc_mode=fallback"

# 3. List documents
# curl -X GET "$BASE_URL/api/v1/documents" -H "Authorization: Bearer \$AUTH_TOKEN"

# 4. Create task
# curl -X POST "$BASE_URL/api/v1/tasks" \\
#   -H "Authorization: Bearer \$AUTH_TOKEN" -H "Content-Type: application/json" \\
#   -d '{"playbook_slug":"kyc_verification","instruction":"Verify KYC"}'

echo "⚠️  Set AUTH_TOKEN after login, then run commands above"
`;
  fs.writeFileSync(curlPath, curlCommands);
  fs.chmodSync(curlPath, 0o755);
  console.log(`✅ Curl commands: ${curlPath}`);

  console.log('\n🎉 Done! Next steps:');
  console.log('1. Import generated postman-environment-demo.json into Postman');
  console.log('2. Start your server: php artisan serve');
  console.log('3. Run Setup → Login requests to get tokens');
  console.log('4. Run Vault → Upload document (uses generated encrypted file)');
}

main().catch(console.error);