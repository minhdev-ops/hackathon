import test from 'node:test';
import assert from 'node:assert/strict';

import { authHeader, clearToken, getToken, setToken } from '../../resources/js/auth/tokenStore.js';

test('tokenStore giữ token trong bộ nhớ và không persist', () => {
    clearToken();
    assert.equal(getToken(), null);

    setToken('jwt-giả-123');
    assert.equal(getToken(), 'jwt-giả-123');

    clearToken();
    assert.equal(getToken(), null);
});

test('authHeader chỉ gắn Bearer khi có token', () => {
    clearToken();
    assert.deepEqual(authHeader(), {});

    setToken('abc.def.ghi');
    assert.deepEqual(authHeader(), { Authorization: 'Bearer abc.def.ghi' });

    clearToken();
});

test('setToken với chuỗi rỗng bị coi là xoá', () => {
    setToken('x');
    setToken('');
    assert.equal(getToken(), null);
});
