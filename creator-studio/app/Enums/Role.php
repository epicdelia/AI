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
            self::Admin => [self::STUDIO, self::CLIENTS_MANAGE, self::STAFF_TEAM, self::PORTAL_COMMUNITY, self::ACCOUNT],
            self::Staff => [self::STUDIO, self::STAFF_TEAM, self::PORTAL_COMMUNITY, self::ACCOUNT],
            self::Client => [self::PORTAL_REQUESTS, self::PORTAL_BILLING, self::PORTAL_TEAM, self::PORTAL_COMMUNITY, self::ACCOUNT],
            self::Member => [self::PORTAL_COMMUNITY, self::ACCOUNT],
        };
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
