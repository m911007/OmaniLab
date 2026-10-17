import { useState, type FormEvent } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { useAuth } from '@/core/auth';

export default function LoginPage() {
  const { user, login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (user) {
    return <Navigate to="/requests" replace />;
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await login(email, password);
      navigate('/requests');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'تعذّر تسجيل الدخول');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <PageHead title="تسجيل الدخول" lead="للمستخدمين المعتمدين فقط: طلب تحديث الرسوم، وإضافة الملاحظات، وتعديل الشروحات. الحسابات يُنشئها المشرف." />
      <form className="card login-card login-form" onSubmit={submit}>
        <label>
          البريد الإلكتروني
          <input type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </label>
        <label>
          كلمة المرور
          <input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </label>
        <button className="btn" disabled={busy}>
          دخول
        </button>
        {error && (
          <span className="form-error" role="alert">
            {error}
          </span>
        )}
      </form>
    </>
  );
}
