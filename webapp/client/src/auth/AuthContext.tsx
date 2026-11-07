import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { fetchMe, login as loginRequest, type StaffProfile } from '../api/auth';
import { getStoredToken, setStoredToken } from '../api/client';

interface AuthContextValue {
  staff: StaffProfile | null;
  status: 'checking' | 'authenticated' | 'unauthenticated';
  login: (username: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

/**
 * Restores the session from a stored token on app load (so a page refresh
 * doesn't bounce a logged-in user back to the login page), and exposes
 * login/logout to the rest of the app.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [staff, setStaff] = useState<StaffProfile | null>(null);
  const [status, setStatus] = useState<AuthContextValue['status']>('checking');

  useEffect(() => {
    const token = getStoredToken();
    if (!token) {
      setStatus('unauthenticated');
      return;
    }
    fetchMe()
      .then((profile) => {
        setStaff(profile);
        setStatus('authenticated');
      })
      .catch(() => {
        setStoredToken(null);
        setStatus('unauthenticated');
      });
  }, []);

  const login = useCallback(async (username: string, password: string) => {
    const { token, staff: profile } = await loginRequest(username, password);
    setStoredToken(token);
    setStaff(profile);
    setStatus('authenticated');
  }, []);

  const logout = useCallback(() => {
    setStoredToken(null);
    setStaff(null);
    setStatus('unauthenticated');
  }, []);

  return <AuthContext.Provider value={{ staff, status, login, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
