import Link from "next/link";
import { db } from "@/lib/db";
import AcceptInviteForm from "./accept-form";

export default async function InvitePage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = await params;
  const invite = await db.invite.findUnique({
    where: { token },
    include: { org: true },
  });
  const valid = invite && !invite.acceptedAt && invite.expiresAt > new Date();

  return (
    <main className="flex min-h-screen items-center justify-center bg-zinc-950 px-4">
      <div className="w-full max-w-sm">
        <Link href="/" className="mb-8 block text-center text-xl font-semibold text-white">
          Fluent<span className="text-indigo-400">AI</span>
        </Link>
        {valid ? (
          <AcceptInviteForm token={token} orgName={invite.org.name} email={invite.email} />
        ) : (
          <div className="rounded-2xl border border-zinc-800 bg-zinc-900 p-8 text-center">
            <h1 className="mb-2 text-lg font-semibold text-white">Invite not valid</h1>
            <p className="text-sm text-zinc-400">
              This invitation link is invalid, expired, or has already been used. Ask your
              workspace admin to send a new one.
            </p>
          </div>
        )}
      </div>
    </main>
  );
}
