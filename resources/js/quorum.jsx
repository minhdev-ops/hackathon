import React from 'react';
import ReactDOM from 'react-dom/client';
import AdminQuorum from './pages/AdminQuorum';

const root = document.getElementById('quorum-root');

if (root) {
    ReactDOM.createRoot(root).render(<AdminQuorum userName={root.dataset.userName || 'Admin'} />);
}
