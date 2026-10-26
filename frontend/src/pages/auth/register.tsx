import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

/**
 * "Daftar" (CORE-REG): an employee claims the account for the profile HR created, with the NIK and
 * employee ID on it. A mismatch of any kind shows the same message, by design.
 */
export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Daftar" />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <InputError message={errors.registration} />

                        <div className="grid gap-2">
                            <Label htmlFor="national_id">NIK</Label>
                            <Input
                                id="national_id"
                                name="national_id"
                                inputMode="numeric"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="off"
                                placeholder="16 digit NIK"
                            />
                            <InputError message={errors.national_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="employee_number">ID karyawan</Label>
                            <Input
                                id="employee_number"
                                name="employee_number"
                                required
                                tabIndex={2}
                                autoComplete="username"
                                placeholder="ID karyawan"
                            />
                            <InputError message={errors.employee_number} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Kata sandi</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={3}
                                autoComplete="new-password"
                                placeholder="Kata sandi"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Ulangi kata sandi
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                tabIndex={4}
                                autoComplete="new-password"
                                placeholder="Ulangi kata sandi"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email (opsional)</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                tabIndex={5}
                                autoComplete="email"
                                placeholder="email@example.com"
                            />
                            <p className="text-xs text-muted-foreground">
                                Tidak dipakai untuk masuk; Anda tetap masuk
                                dengan ID karyawan.
                            </p>
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            tabIndex={6}
                            disabled={processing}
                            data-test="register-button"
                        >
                            {processing && <Spinner />}
                            Daftar
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            Sudah punya akun?{' '}
                            <TextLink href={login()} tabIndex={7}>
                                Masuk
                            </TextLink>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Daftar',
    description:
        'Isi NIK dan ID karyawan yang sama dengan data yang dibuat HR, lalu pilih kata sandi. Akun baru bisa dipakai setelah HR mengaktifkannya.',
};
