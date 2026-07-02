export type PlanId = "FREE" | "PRO" | "ENTERPRISE";

export interface Plan {
  id: PlanId;
  name: string;
  priceMonthly: number; // USD per seat
  monthlyTokenLimit: number; // prompt + completion tokens per org per month
  seatLimit: number;
  models: string[];
  apiAccess: boolean;
  description: string;
}

export const PLANS: Record<PlanId, Plan> = {
  FREE: {
    id: "FREE",
    name: "Free",
    priceMonthly: 0,
    monthlyTokenLimit: 100_000,
    seatLimit: 3,
    models: ["gpt-4o-mini"],
    apiAccess: false,
    description: "For individuals and small teams trying things out.",
  },
  PRO: {
    id: "PRO",
    name: "Pro",
    priceMonthly: 29,
    monthlyTokenLimit: 5_000_000,
    seatLimit: 25,
    models: ["gpt-4o-mini", "gpt-4o"],
    apiAccess: true,
    description: "For growing teams that need more power and API access.",
  },
  ENTERPRISE: {
    id: "ENTERPRISE",
    name: "Enterprise",
    priceMonthly: 99,
    monthlyTokenLimit: 100_000_000,
    seatLimit: 1000,
    models: ["gpt-4o-mini", "gpt-4o", "o3-mini"],
    apiAccess: true,
    description: "For organizations with advanced security and scale needs.",
  },
};

export const DEFAULT_MODEL = "gpt-4o-mini";

export function getPlan(id: string): Plan {
  return PLANS[(id as PlanId) in PLANS ? (id as PlanId) : "FREE"];
}
