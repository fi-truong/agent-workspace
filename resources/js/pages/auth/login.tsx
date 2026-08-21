import { Head } from '@inertiajs/react';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import { Button } from '@/components/ui/button';
import type { TeamInvitationContext } from '@/types';

type Props = {
    status?: string;
    teamInvitation?: TeamInvitationContext | null;
};

export default function Login({ status, teamInvitation }: Props) {
    return (
        <>
            <Head title="Log in" />

            {teamInvitation && (
                <TeamInvitationAlert
                    invitation={teamInvitation}
                    action="Log in"
                />
            )}

            <div className="flex flex-col gap-6">
                <p className="text-center text-sm text-muted-foreground">
                    Đăng nhập bằng tài khoản trường LSTS (Microsoft / Entra ID)
                </p>

                <Button asChild className="w-full" data-test="microsoft-login-button">
                    <a href="/auth/microsoft/redirect">
                        Đăng nhập bằng tài khoản LSTS
                    </a>
                </Button>
            </div>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Đăng nhập vào AI+ LSTS',
    description: 'Sử dụng tài khoản trường (Microsoft / Entra ID) để tiếp tục',
};
