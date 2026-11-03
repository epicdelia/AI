<?php

namespace App\Enums;

/**
 * Who someone is in the studio, and therefore what they may do. This is the one place roles
 * and their abilities are defined: the API guards routes with `ability:<name>` (EnsureAbility),
 * and the dashboard shows only the pages whose `ability` the signed-in user has (auth/status
 * sends the list).
 *
 * - Admin: her. The whole studio, plus managing clients. Two-factor sign-in is required.
 * - Client: someone who booked, bought or got a proposal on her website. Only their own
 *   requests, calls, invoices and messages, and only once their email is verified.
 */
enum Role: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Client = 'client';
    case Member = 'member';

    /**
     * The whole studio: every page and API she has (scripts, social, deals, settings…).
     */
    public const STUDIO = 'studio';

    /**
     * Her private side of the studio: money (deals, revenue, subscribers), her website, keys,
     * settings, connections, backups and the love pages. Admin only: staff get the content
     * studio (scripts, ideas, social, media) without it, like YouTube's "Editor (no revenue)".
     */
    public const STUDIO_PRIVATE = 'studio.private';

    /**
     * API paths (after /api/) inside the studio that need STUDIO_PRIVATE, as fnmatch patterns.
     * `GET:` limits a pattern to writes being blocked (reads stay open), e.g. the brand kit
     * staff need for thumbnails. Enforced by App\Http\Middleware\EnsureStudioPrivate.
     */
    public const PRIVATE_PATHS = [
        'deals', 'deals/*', 'scripts/*/sponsor', 'engage/items/*/to-deal',
        'website', 'landing/*', 'testimonials', 'testimonials/*', 'subscribers', 'subscribers/*',
        'hero', 'marquee', 'partners', 'pricing', 'about', 'about/*', 'talks', 'talks/*', 'bio',
        'love', 'love/*',
        'social/accounts', 'social/accounts/*',
        'agents/*/sponsor-*', 'agents/*/audit-deal-fulfillment', 'agents/*/generate-deal-wrap-report',
        'agents/*/synthesize-brand-pitch', 'agents/*/architect-sponsor-placement', 'agents/*/escalate-lead-deal',
        'backups', 'backups/*', 'settings', 'settings/*', 'automations', 'user/keys', 'user/keys/*',
        'usage', 'schedule', 'schedule/*', 'studio/infra/*', 'mcp', 'mcp/*',
        'community/admin', 'community/admin/*', 'crm', 'crm/*', 'portal-brand', 'portal-brand/*', 'needs-you',
    ];

    /**
     * Paths where only writes are private (staff may read).
     */
    public const PRIVATE_WRITES = ['brand', 'brand/*', 'me/avatar'];

    /**
     * The Clients page: client accounts, linking deals, invites, messaging clients.
     */
    public const CLIENTS_MANAGE = 'clients.manage';

    /**
     * Staff team & production: editorial workflow, task dispatcher, QA checklist.
     */
    public const STAFF_TEAM = 'staff.team';

    /**
     * A client's own requests (deals), proposals, deliverables and messages.
     */
    public const PORTAL_REQUESTS = 'portal.requests';

    /**
     * A client's own payments and invoices.
     */
    public const PORTAL_BILLING = 'portal.billing';

    /**
     * A client's colleagues sharing their requests (only the team's owner adds or removes).
     */
    public const PORTAL_TEAM = 'portal.team';

    /**
     * Follower & Community Hub: starter kits, architecture blueprints, AMA questions, dispatches.
     */
    public const PORTAL_COMMUNITY = 'portal.community';

    /**
     * Their own profile, password, two-factor sign-in (everyone signed in).
     */
    public const ACCOUNT = 'account';

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => [self::STUDIO, self::STUDIO_PRIVATE, self::CLIENTS_MANAGE, self::STAFF_TEAM, self::PORTAL_COMMUNITY, self::ACCOUNT],
            self::Staff => [self::STUDIO, self::STAFF_TEAM, self::PORTAL_COMMUNITY, self::ACCOUNT],
            self::Client => [self::PORTAL_REQUESTS, self::PORTAL_BILLING, self::PORTAL_TEAM, self::PORTAL_COMMUNITY, self::ACCOUNT],
            self::Member => [self::PORTAL_COMMUNITY, self::ACCOUNT],
        };
    }

    /**
     * True when `api/<path>` with this method needs STUDIO_PRIVATE.
     */
    public static function isPrivatePath(string $method, string $path): bool
    {
        $path = trim(preg_replace('~^api/~', '', trim($path, '/')), '/');
        foreach (self::PRIVATE_PATHS as $pattern) {
            if (fnmatch($pattern, $path, FNM_NOESCAPE)) {
                return true;
            }
        }
        if (!in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            foreach (self::PRIVATE_WRITES as $pattern) {
                if (fnmatch($pattern, $path, FNM_NOESCAPE)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function allows(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Staff => 'Staff / Team',
            self::Client => 'Client',
            self::Member => 'Community Member',
        };
    }

    /**
     * Two-factor authentication is optional across roles (configurable via REQUIRE_TWO_FACTOR).
     */
    public function requiresTwoFactor(): bool
    {
        return (bool)env('REQUIRE_TWO_FACTOR', false);
    }

    /**
     * Clients see their data matched by email, so their email must be proven first.
     */
    public function requiresVerifiedEmail(): bool
    {
        return $this === self::Client;
    }

    /**
     * Sign-up role selection: clients or community members/followers.
     */
    public static function forSignUp(?string $role = 'client'): self
    {
        return match ($role) {
            'member' => self::Member,
            default => self::Client,
        };
    }
}
