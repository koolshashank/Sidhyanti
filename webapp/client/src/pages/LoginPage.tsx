import { LockOutlined, UserOutlined } from '@ant-design/icons';
import { Alert, Button, Checkbox, Form, Input } from 'antd';
import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';

interface LoginFormValues {
  username: string;
  password: string;
  remember: boolean;
}

export default function LoginPage() {
  const { login, status } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (status === 'authenticated') {
    return <Navigate to="/" replace />;
  }

  const onFinish = async (values: LoginFormValues) => {
    setSubmitting(true);
    setError(null);
    try {
      await login(values.username.trim(), values.password);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen w-full">
      {/* Brand panel -- hidden on small screens, same copy/palette as the PHP CRM's login page */}
      <div
        className="relative hidden flex-1 flex-col justify-center overflow-hidden px-16 py-12 text-white lg:flex"
        style={{ background: 'linear-gradient(135deg, #0b2545 0%, #13315c 60%, #0d3b66 100%)' }}
      >
        <div
          className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full opacity-20"
          style={{ background: 'radial-gradient(circle, #e8b95a 0%, transparent 70%)' }}
        />
        <img src="/logo.png" alt="Sidhyanti CRM" className="mb-8 h-16 w-16 rounded-2xl object-cover shadow-lg" />
        <div className="mb-3 text-sm font-semibold uppercase tracking-widest text-[#e8b95a]">Microfinance, Simplified</div>
        <h1 className="mb-6 max-w-lg text-5xl font-bold leading-tight">
          Every Loan. Every Branch.
          <br />
          <span style={{ color: '#e8b95a' }}>One Command Center.</span>
        </h1>
        <p className="max-w-md text-base leading-relaxed text-white/70">
          Track leads, disburse loans, and collect repayments — with accurate pipeline stages, real payment
          transactions, and a complete audit trail behind every action.
        </p>
        {/* Only the bottom "scene" half of the artwork is shown -- the top half already
            has its own baked-in headline text, which would duplicate the real <h1>/<p>
            above. object-position: bottom inside a shorter, overflow-hidden box crops
            that top half away instead of needing a separately-exported asset. */}
        <div className="relative mt-8 w-full max-w-lg overflow-hidden rounded-2xl" style={{ height: '300px' }}>
          <img
            src="/login-illustration.png"
            alt=""
            className="absolute bottom-0 left-0 w-full"
            style={{ height: 'auto' }}
          />
        </div>
      </div>

      {/* Form panel */}
      <div className="flex flex-1 items-center justify-center bg-slate-50 px-6 py-12">
        <div className="w-full max-w-sm">
          <div className="mb-8 text-center lg:hidden">
            <img src="/logo.png" alt="Sidhyanti CRM" className="mx-auto mb-4 h-14 w-14 rounded-2xl object-cover shadow" />
          </div>
          <h2 className="mb-1 text-2xl font-bold text-slate-900">Welcome back</h2>
          <p className="mb-8 text-sm text-slate-500">Sign in to your CRM workspace</p>

          {error && <Alert type="error" message={error} showIcon className="mb-5" />}

          <Form<LoginFormValues> layout="vertical" onFinish={onFinish} requiredMark={false} disabled={submitting}>
            <Form.Item name="username" label="Username" rules={[{ required: true, message: 'Please enter your username' }]}>
              <Input prefix={<UserOutlined className="text-slate-400" />} placeholder="e.g. admin" size="large" autoFocus />
            </Form.Item>
            <Form.Item name="password" label="Password" rules={[{ required: true, message: 'Please enter your password' }]}>
              <Input.Password prefix={<LockOutlined className="text-slate-400" />} placeholder="••••••••" size="large" />
            </Form.Item>
            <Form.Item name="remember" valuePropName="checked" initialValue={true}>
              <Checkbox>Remember me for 30 days</Checkbox>
            </Form.Item>
            <Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                size="large"
                block
                loading={submitting}
                style={{ background: '#0b2545' }}
              >
                Sign In
              </Button>
            </Form.Item>
          </Form>
        </div>
      </div>
    </div>
  );
}
