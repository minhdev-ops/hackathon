import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import { ToastProvider } from './ui/Toast';
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
                            <Route path="/vault" element={<Vault />} />
                            <Route path="/playbooks" element={<Playbooks />} />
                            <Route path="/tasks/new" element={<TaskCreate />} />
                            <Route path="/tasks/:id" element={<TaskDetail />} />
                            <Route path="/agent-console" element={<AgentConsole />} />
                            <Route path="/grants/:grantId" element={<ConsentReview />} />
                            <Route path="/admin/evil" element={<AdminEvil />} />
                            <Route path="/timeline" element={<Timeline />} />
                        </Routes>
                    </main>
                </div>
            </ToastProvider>
        </AuthProvider>
    );
}
