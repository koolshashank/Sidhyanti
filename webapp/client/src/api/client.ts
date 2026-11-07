import axios from 'axios';

/**
 * Shared axios instance for the new Node API (webapp/server), separate from
 * the PHP /api/v1 the mobile app uses. In dev, Vite's proxy (vite.config.ts)
 * forwards /api/* to the Node server on :4000; in production the built
 * bundle and the API are expected to be reachable under the same origin/path.
 */
export const apiClient = axios.create({ baseURL: '/api' });

const TOKEN_STORAGE_KEY = 'sidhyanti_webapp_token';

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_STORAGE_KEY);
}

export function setStoredToken(token: string | null) {
  if (token) {
    localStorage.setItem(TOKEN_STORAGE_KEY, token);
  } else {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
  }
}

apiClient.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

/** A failed request's user-facing message, whichever shape the backend sent it in. */
export function apiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    return error.response?.data?.message ?? 'Something went wrong. Please try again.';
  }
  return 'Something went wrong. Please try again.';
}
