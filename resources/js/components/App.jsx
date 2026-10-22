import { Routes, Route } from 'react-router-dom';
import { ToastProvider } from './ui/Toast';
import { AuthProvider } from '../auth/AuthProvider';
import ProtectedRoute from '../auth/ProtectedRoute';
import AppHeader from './layout/AppHeader';
import AdminEvil from './AdminEvil';
import Timeline from '../pages/Timeline';
import Home from '../pages/Home';
import Vault from '../pages/Vault';
import Playbooks from '../pages/Playbooks';
import TaskCreate from '../pages/TaskCreate';
import TaskDetail from '../pages/TaskDetail';
import AgentConsole from '../pages/AgentConsole';
import ConsentReview from '../pages/ConsentReview';

export default function App() {
    return (
        <AuthProvider>
            <ToastProvider>
                <div className="min-h-screen bg-bg text-text">
                    <AppHeader />
                    <main className="pt-0 lg:pt-0">
                        <Routes>
                            <Route path="/" element={<Home />} />
                            <Route
                                path="/vault"
                                element={<ProtectedRoute roles={['owner']}><Vault /></ProtectedRoute>}
                            />
                            <Route
                                path="/playbooks"
                                element={<ProtectedRoute roles={['owner', 'admin']}><Playbooks /></ProtectedRoute>}
                            />
                            <Route
                                path="/tasks/new"
                                element={<ProtectedRoute roles={['owner']}><TaskCreate /></ProtectedRoute>}
                            />
                            <Route
                                path="/tasks/:id"
                                element={<ProtectedRoute roles={['owner', 'admin']}><TaskDetail /></ProtectedRoute>}
                            />
                            <Route
                                path="/agent-console"
                                element={<ProtectedRoute roles={['owner', 'admin']}><AgentConsole /></ProtectedRoute>}
                            />
                            <Route
                                path="/grants/:grantId"
                                element={<ProtectedRoute roles={['owner']}><ConsentReview /></ProtectedRoute>}
                            />
                            <Route
                                path="/admin/evil"
                                element={<ProtectedRoute roles={['admin']}><AdminEvil /></ProtectedRoute>}
                            />
                            <Route path="/timeline" element={<Timeline />} />
                        </Routes>
                    </main>
                </div>
            </ToastProvider>
        </AuthProvider>
    );
}
