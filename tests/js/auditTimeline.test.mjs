import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildAuditQuery,
    grantOpenedSentence,
    groupLogsByDay,
    nextBeforeId,
    safeMetaSummary,
} from '../../resources/js/lib/auditTimeline.js';

test('grantOpenedSentence renders the FR-AUD-03 sentence', () => {
    const sentence = grantOpenedSentence({
        action: 'grant.opened',
        actor_name: 'TrustGate Agent',
        actor_id: 'agent-1',
        created_at: '2026-10-22T03:15:00.000Z',
        meta: {
            doc_count: 3,
            expires_at: '2026-10-22T03:30:00.000Z',
        },
    });

    assert.match(sentence, /^TrustGate Agent đã mở 3 tài liệu lúc /);
    assert.match(sentence, /, hết hạn lúc /);
});

test('grantOpenedSentence falls back to actor id and omits missing expiry', () => {
    const sentence = grantOpenedSentence({
        action: 'grant.opened',
        actor_id: 'agent-42',
        created_at: '2026-10-22T03:15:00.000Z',
        meta: { doc_count: 1 },
    });

    assert.match(sentence, /^Agent #agent-42 đã mở 1 tài liệu lúc /);
    assert.doesNotMatch(sentence, /hết hạn/);
});

test('safeMetaSummary only renders whitelisted, non-sensitive metadata', () => {
    const summary = safeMetaSummary({
        doc_count: 3,
        expires_at: '2026-10-22T03:30:00.000Z',
        content_b64: 'c2VjcmV0',
        grant_key_b64: 'c2VjcmV0',
        title_ct: 'c2VjcmV0',
        instruction: 'Nộp hồ sơ',
        arbitrary: 'không hiển thị',
    });

    assert.match(summary, /Số tài liệu: 3/);
    assert.match(summary, /Hết hạn lúc: /);
    assert.doesNotMatch(summary, /c2VjcmV0/);
    assert.doesNotMatch(summary, /Nộp hồ sơ/);
    assert.doesNotMatch(summary, /không hiển thị/);
});

test('groupLogsByDay keeps newest-first order and groups by Vietnam date', () => {
    const groups = groupLogsByDay([
        { id: 3, created_at: '2026-10-22T10:00:00+07:00' },
        { id: 2, created_at: '2026-10-22T09:00:00+07:00' },
        { id: 1, created_at: '2026-10-21T09:00:00+07:00' },
    ]);

    assert.equal(groups.length, 2);
    assert.equal(groups[0][1].length, 2);
    assert.match(groups[0][0], /22\/10\/2026/);
    assert.match(groups[1][0], /21\/10\/2026/);
});

test('nextBeforeId returns the oldest loaded id', () => {
    assert.equal(nextBeforeId([{ id: 9 }, { id: 5 }, { id: 3 }]), 3);
    assert.equal(nextBeforeId([]), null);
});

test('buildAuditQuery serialises cursor, filters and limit', () => {
    assert.equal(buildAuditQuery({ limit: 50 }), '?limit=50');

    assert.equal(
        buildAuditQuery({
            limit: 50,
            beforeId: 10,
            action: 'grant.opened',
            subjectType: 'grant',
        }),
        '?limit=50&before_id=10&action=grant.opened&subject_type=grant',
    );
});
