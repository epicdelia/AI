"use client";

import { useRouter } from "next/navigation";

export default function LogoutButton() {
  const router = useRouter();
  return (
    <button
      onClick={async () => {
        await fetch("/api/auth/logout", { method: "POST" });
        router.push("/login");
        router.refresh();
      }}
      className="mt-2 text-xs text-zinc-500 hover:text-zinc-300"
    >
      Sign out
    </button>
  );
}
