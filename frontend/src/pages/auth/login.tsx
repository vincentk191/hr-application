import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    canRegister: boolean;
};

/**
 * Signing in (CORE-ACC-007): NIK and employee ID together, which identify one person across the group. An
 * email works only on the private entrance, for the administrator accounts; the form sends either as the single
 * "login" the server reads ("NIK|ID" or an email).
 */
export default function Login({ status, canRegister }: Props) {
    const { entrance } = usePage().props;
    const [byEmail, setByEmail] = useState(false);
    const [nik, setNik] = useState('');
    const [number, setNumber] = useState('');
    const [email, setEmail] = useState('');

    const mayUseEmail = !entrance?.public;
    const login =
        byEmail && mayUseEmail ? email : `${nik.trim()}|${number.trim()}`;

    return (
        <>
            <Head title="Masuk" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <input type="hidden" name="email" value={login} />

                            {byEmail && mayUseEmail ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="login-email">Email</Label>
                                    <Input
                                        id="login-email"
                                        type="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="username"
                                        placeholder="email@example.com"
                                        value={email}
                                        onChange={(event) =>
                                            setEmail(event.target.value)
                                        }
                                    />
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="login-nik">NIK</Label>
                                        <Input
                                            id="login-nik"
                                            type="text"
                                            inputMode="numeric"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="off"
                                            placeholder="16 digit NIK"
                                            value={nik}
                                            onChange={(event) =>
                                                setNik(event.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="login-number">
                                            ID karyawan
                                        </Label>
                                        <Input
                                            id="login-number"
                                            type="text"
                                            required
                                            tabIndex={2}
                                            autoComplete="off"
                                            placeholder="ID karyawan"
                                            value={number}
                                            onChange={(event) =>
                                                setNumber(event.target.value)
                                            }
                                        />
                                    </div>
                                </>
                            )}
                            <InputError message={errors.email} />

                            <div className="grid gap-2">
                                <Label htmlFor="password">Kata sandi</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="current-password"
                                    placeholder="Kata sandi"
                                />
                                <InputError message={errors.password} />
                                <p className="text-xs text-muted-foreground">
                                    Lupa kata sandi? Hubungi HR untuk
                                    mengaturnya ulang.
                                </p>
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={4}
                                />
                                <Label htmlFor="remember">Ingat saya</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={5}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Masuk
                            </Button>
                        </div>

                        {mayUseEmail && (
                            <button
                                type="button"
                                className="text-center text-sm text-muted-foreground underline"
                                onClick={() => setByEmail(!byEmail)}
                            >
                                {byEmail
                                    ? 'Masuk dengan NIK dan ID karyawan'
                                    : 'Masuk dengan email (akun administrator)'}
                            </button>
                        )}

                        {canRegister && (
                            <div className="text-center text-sm text-muted-foreground">
                                Belum punya akun?{' '}
                                <TextLink href={register()} tabIndex={6}>
                                    Daftar
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Masuk',
    description: 'Masuk dengan NIK dan ID karyawan Anda.',
};
