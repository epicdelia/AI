<?php

namespace App\Domains\Deals;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Security\Audit;
use App\Models\Deal;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Non-repudiation E-Signature engine for proposals & contracts.
 * Generates tamper-evident cryptographic verification fingerprints,
 * snapshots client IP, email, user-agent, and logs immutable security audits.
 */
class ContractSigner
{
    public static function sign(Deal $deal, string $name, ?Request $request = null): JsonResponse
    {
        $request ??= request();
        $user = $request?->user();

        $ip = (string) ($request?->ip() ?: '0.0.0.0');
        $userAgent = mb_substr((string) ($request?->userAgent() ?: 'Unknown'), 0, 255);
        $email = $user?->email ?: ($deal->contact_email ?: 'client@direct.deal');
        $timestamp = now();

        $proposal = $deal->proposal ?? [];
        $markdown = rescue(fn () => ProposalWriter::markdown($proposal), fn () => ($proposal['title'] ?? '')."\n".($proposal['summary'] ?? ''), false);

        // Tamper-evident signature fingerprint
        $fingerprintPayload = implode('|', [
            $deal->id,
            $deal->brand_name,
            (float) ($proposal['investment']['amount'] ?? $deal->rate),
            $proposal['investment']['currency'] ?? ($deal->currency ?: 'USD'),
            hash('sha256', (string) $markdown),
            trim($name),
            strtolower(trim($email)),
            $ip,
            $timestamp->toIso8601ZuluString(),
        ]);
        $signatureHash = hash('sha256', $fingerprintPayload);

        $before = array_search($deal->stage, Deal::STAGES, true);
        $stageUpdate = ($before !== false && $before < array_search('contracted', Deal::STAGES, true))
            ? ['stage' => 'contracted']
            : [];

        $deal->update([
            'accepted_at' => $timestamp,
            'accepted_by' => trim($name),
            'signature_hash' => $signatureHash,
            'signer_ip' => $ip,
            'signer_user_agent' => $userAgent,
            'signer_email' => strtolower(trim($email)),
            'last_contacted_at' => $timestamp,
        ] + $stageUpdate);

        // Security Audit Log & Agent event
        Audit::log('contract_signed', $user, $deal, [
            'signer_name' => trim($name),
            'signer_email' => strtolower(trim($email)),
            'signature_hash' => $signatureHash,
            'ip' => $ip,
        ]);

        AgentEvent::log('lead', "{$deal->brand_name} signed agreement ({$name}) with verified hash {$signatureHash}.");

        $creatorFirstName = explode(' ', Prompts::name())[0];

        return response()->json([
            'accepted_at' => $deal->accepted_at->toIso8601String(),
            'accepted_by' => $deal->accepted_by,
            'signature_hash' => $signatureHash,
            'verification' => [
                'signed_by' => $deal->accepted_by,
                'email' => $deal->signer_email,
                'ip' => $deal->signer_ip,
                'signed_at' => $deal->accepted_at->toIso8601String(),
                'hash' => $signatureHash,
            ],
            'message' => "Proposal signed and verified! {$creatorFirstName} will be in touch about next steps.",
        ]);
    }
}
