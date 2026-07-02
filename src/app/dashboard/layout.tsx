import Link from "next/link";
import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/auth";
import { getPlan } from "@/lib/plans";
import LogoutButton from "@/components/logout-button";

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const user = await getCurrentUser();
  if (!user) redirect("/login");
  const plan = getPlan(user.org.plan);

  return (
    <div className="flex h-screen bg-zinc-950 text-zinc-100">
      <aside className="flex w-60 shrink-0 flex-col border-r border-zinc-800 bg-zinc-900/50">
        <div className="border-b border-zinc-800 px-5 py-4">
          <Link href="/dashboard" className="text-lg font-semibold text-white">
            Fluent<span className="text-indigo-400">AI</span>
          </Link>
          <p className="mt-1 truncate text-xs text-zinc-500">
            {user.org.name} · {plan.name} plan
          </p>
        </div>
        <nav className="flex-1 space-y-1 p-3 text-sm">
          <Link
            href="/dashboard/chat"
            className="block rounded-lg px-3 py-2 text-zinc-300 hover:bg-zinc-800 hover:text-white"
          >
            💬 Chat
          </Link>
          <Link
            href="/dashboard/usage"
            className="block rounded-lg px-3 py-2 text-zinc-300 hover:bg-zinc-800 hover:text-white"
          >
            📊 Usage
          </Link>
          <Link
            href="/dashboard/settings"
            className="block rounded-lg px-3 py-2 text-zinc-300 hover:bg-zinc-800 hover:text-white"
          >
            ⚙️ Settings
          </Link>
        </nav>
        <div className="border-t border-zinc-800 p-4">
          <p className="truncate text-sm text-zinc-300">{user.name}</p>
          <p className="truncate text-xs text-zinc-500">{user.email}</p>
          <LogoutButton />
        </div>
      </aside>
      <main className="flex-1 overflow-hidden">{children}</main>
    </div>
  );
}
