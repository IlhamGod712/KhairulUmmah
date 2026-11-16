import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';

import { AuthProvider } from '@/context/AuthContext';
import { router } from '@/router';
import '@/index.css';

const container = document.getElementById('root');

if (container === null) {
    throw new Error('Element #root tidak ditemukan di index.html.');
}

createRoot(container).render(
    <StrictMode>
        <AuthProvider>
            <RouterProvider router={router} />
        </AuthProvider>
    </StrictMode>,
);
