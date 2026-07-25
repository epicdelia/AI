import { getCurrentUser } from "@/lib/auth";
import { db } from "@/lib/db";
import { getPlan } from "@/lib/plans";
import WorkspaceSection from "./workspace-section";
import TeamSection from "./team-section";
import ApiKeysSection from "./api-keys-section";
import PlanSection from "./plan-section";

export default async function SettingsPage() {
  const user = await getCurrentUser();
  if (!user) return null;
  const plan = getPlan(user.org.plan);
  const isAdmin = user.role !== "MEMBER";

  const members = await db.user.findMany({
    where: { orgId: user.orgId },
    orderBy: { createdAt: "asc" },
    select: { id: true, name: true, email: true, role: true },
  });
  const keys = await db.apiKey.findMany({
    where: { orgId: user.orgId, revokedAt: null },
    orderBy: { createdAt: "desc" },
    select: { id: true, name: true, prefix: true, lastUsedAt: true, createdAt: true },
  });

  return (
    <div className="h-full overflow-y-auto">
      <div className="mx-auto max-w-3xl space-y-10 px-6 py-10">
        <header>
          <h1 className="text-xl font-semibold text-white">Workspace settings</h1>
          <p className="mt-1 text-sm text-zinc-500">
            {user.org.name} · {plan.name} plan · you are {user.role.toLowerCase()}
          </p>
        </header>

        <WorkspaceSection orgName={user.org.name} isAdmin={isAdmin} />

        <TeamSection
          members={members}
          currentUserId={user.id}
          isAdmin={isAdmin}
          seatLimit={plan.seatLimit}
        />

        <ApiKeysSection
          initialKeys={keys.map((k) => ({
            ...k,
            createdAt: k.createdAt.toISOString(),
            lastUsedAt: k.lastUsedAt?.toISOString() ?? null,
          }))}
          isAdmin={isAdmin}
          apiAccess={plan.apiAccess}
        />

        <PlanSection currentPlan={plan.id} isOwner={user.role === "OWNER"} />
      </div>
    </div>
  );
}
