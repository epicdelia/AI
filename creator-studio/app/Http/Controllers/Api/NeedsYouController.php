<?php

namespace App\Http\Controllers\Api;

use App\Domains\Clients\Models\DealMessage;
use App\Domains\Clients\Models\DeliverableAnnotation;
use App\Domains\Community\Models\CommunityItem;
use App\Domains\Crm\Models\CrmActivity;
use App\Domains\Crm\Models\CrmContact;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Team\Models\TeamTask;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;

/**
 * One list of everything waiting on her, across every role: clients (messages, revision
 * notes, new briefs), the team (payouts to approve, overdue work), the community (projects to
 * approve, questions to answer), her CRM (follow-ups due) and agent drafts to approve.
 * Each item links straight to where it's handled.
 */
class NeedsYouController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $groups = [];

        $unread = DealMessage::with('deal:id,brand_name,user_id')->where('from_admin', false)->whereNull('read_at')
            ->latest()->take(20)->get()->groupBy('deal_id');
        $groups[] = $this->group('clients', 'Client messages', 'chat', $unread->map(fn ($msgs) => [
            'title' => ($msgs->first()->deal?->brand_name ?? 'A client') . ': ' . mb_substr($msgs->first()->body, 0, 90),
            'meta' => $msgs->count() . ' unread',
            'url' => $msgs->first()->deal?->user_id ? '/clients/' . $msgs->first()->deal->user_id : '/clients',
            'at' => $msgs->first()->created_at?->toIso8601String(),
        ])->values()->all());

        $notes = DeliverableAnnotation::with(['deal:id,brand_name,user_id', 'user:id,role'])->where('status', 'open')->latest()->take(40)->get()
            ->filter(fn ($a) => !$a->user?->isAdmin())->take(10);
        $groups[] = $this->group('revisions', 'Revision notes', 'pen', $notes->map(fn ($a) => [
            'title' => ($a->deal?->brand_name ?? 'Deal') . ': ' . mb_substr($a->body, 0, 90),
            'meta' => $a->timecode_seconds !== null ? 'at ' . gmdate($a->timecode_seconds >= 3600 ? 'H:i:s' : 'i:s', (int)$a->timecode_seconds) : null,
            'url' => $a->deal?->user_id ? '/clients/' . $a->deal->user_id : '/deals',
            'at' => $a->created_at?->toIso8601String(),
        ])->values()->all());

        $briefs = Deal::whereNotNull('intake_at')->where('intake_at', '>=', now()->subDays(7))->whereNotIn('stage', ['paid', 'lost'])
            ->latest('intake_at')->take(10)->get(['id', 'brand_name', 'user_id', 'intake_at']);
        $groups[] = $this->group('briefs', 'New briefs this week', 'file-text', $briefs->map(fn ($d) => [
            'title' => $d->brand_name,
            'meta' => 'answered the intake questions',
            'url' => $d->user_id ? '/clients/' . $d->user_id : '/deals',
            'at' => $d->intake_at?->toIso8601String(),
        ])->all());

        $overdueInvoices = Deal::whereNotNull('invoice_due_on')->whereDate('invoice_due_on', '<', now()->toDateString())
            ->where('payment_status', '!=', 'paid')->where('stage', '!=', 'lost')->orderBy('invoice_due_on')->take(10)->get();
        $lateReviews = Deal::where('stage', 'in_review')->whereNotNull('review_due_on')->whereDate('review_due_on', '<', now()->toDateString())
            ->orderBy('review_due_on')->take(10)->get();
        $rights = \App\Domains\Deals\DealRight::with('deal:id,brand_name,user_id')->where('status', 'active')->whereNotNull('ends_on')
            ->whereDate('ends_on', '<=', now()->addDays(\App\Domains\Deals\Rights::REMIND_DAYS_BEFORE)->toDateString())->orderBy('ends_on')->take(10)->get();
        $groups[] = $this->group('money', 'Payments & deadlines', 'credit-card', array_merge(
            $overdueInvoices->map(fn ($d) => [
                'title' => "Overdue invoice: {$d->brand_name}",
                'meta' => \App\Domains\Deals\Billing::daysOverdue($d) . ' days · ' . number_format(\App\Domains\Deals\Billing::nextPayment($d)['amount'], 2) . ' ' . ($d->currency ?: 'USD'),
                'url' => '/deals',
                'at' => $d->invoice_due_on->toIso8601String(),
            ])->all(),
            $lateReviews->map(fn ($d) => [
                'title' => "Client review late: {$d->brand_name}",
                'meta' => 'due ' . $d->review_due_on->toFormattedDateString(),
                'url' => $d->user_id ? '/clients/' . $d->user_id : '/deals',
                'at' => $d->review_due_on->toIso8601String(),
            ])->all(),
            $rights->map(fn ($r) => [
                'title' => 'Rights ending: ' . ($r->deal?->brand_name ?? 'deal') . ' · ' . (\App\Domains\Deals\Rights::KINDS[$r->kind] ?? $r->kind),
                'meta' => 'ends ' . $r->ends_on->toFormattedDateString(),
                'url' => '/deals',
                'at' => $r->ends_on->toIso8601String(),
            ])->all(),
        ));

        $inquiries = Deal::where('stage', 'inquiry')->where(fn ($q) => $q->where('fit_verdict', 'review')->orWhere('auto_reply', 'draft'))
            ->latest()->take(10)->get(['id', 'brand_name', 'fit_score', 'fit_verdict', 'auto_reply', 'created_at']);
        $copyChanges = Deal::where('copy_status', 'changes')->take(10)->get(['id', 'brand_name', 'user_id', 'copy_note']);
        $groups[] = $this->group('inquiries', 'Brand inquiries', 'briefcase', array_merge(
            $inquiries->map(fn ($d) => [
                'title' => ($d->auto_reply === 'draft' ? 'Reply drafted: ' : 'Look at: ') . $d->brand_name,
                'meta' => $d->fit_score !== null ? "fit {$d->fit_score}/100 · {$d->fit_verdict}" : null,
                'url' => '/deals',
                'at' => $d->created_at?->toIso8601String(),
            ])->all(),
            $copyChanges->map(fn ($d) => [
                'title' => "Copy changes asked: {$d->brand_name}",
                'meta' => mb_substr((string)$d->copy_note, 0, 80),
                'url' => '/deals',
                'at' => null,
            ])->all(),
            \App\Domains\Deals\InquiryAutopilot::rules()['enabled'] && \App\Domains\Deals\InquiryAutopilot::rateCardStale() ? [[
                'title' => 'Your rates haven’t been reviewed in 90+ days',
                'meta' => 'the autopilot quotes your starting price',
                'url' => '/deals',
                'at' => null,
            ]] : [],
        ));

        $payouts = TeamTask::with('assignee:id,name')->where('payout_status', 'pending')->latest('completed_at')->take(10)->get();
        $overdue = TeamTask::with('assignee:id,name')->whereNull('completed_at')->whereNotNull('due_on')
            ->whereDate('due_on', '<', now()->toDateString())->orderBy('due_on')->take(10)->get();
        $groups[] = $this->group('team', 'Team', 'users', array_merge(
            $payouts->map(fn ($t) => [
                'title' => 'Approve payout: ' . $t->title,
                'meta' => ($t->assignee?->name ?? 'Unassigned') . ' · ' . number_format((float)$t->payout_amount, 2) . ' ' . $t->payout_currency,
                'url' => '/team',
                'at' => $t->completed_at?->toIso8601String(),
            ])->all(),
            $overdue->map(fn ($t) => [
                'title' => 'Overdue: ' . $t->title,
                'meta' => ($t->assignee?->name ?? 'Unassigned') . ' · due ' . $t->due_on->toFormattedDateString(),
                'url' => '/team',
                'at' => null,
            ])->all(),
        ));

        $community = CommunityItem::with('user:id,name')->where(fn ($q) => $q->where('status', 'pending')
            ->orWhere(fn ($q) => $q->where('type', 'question')->where('status', 'published')->whereNull('answered_at')))
            ->orderByDesc('upvotes')->latest('id')->take(10)->get();
        $groups[] = $this->group('community', 'Community', 'sparkle', $community->map(fn ($i) => [
            'title' => ($i->status === 'pending' ? 'Approve project: ' : 'Answer: ') . mb_substr($i->title, 0, 90),
            'meta' => trim(($i->user?->name ?? '') . ($i->upvotes ? " · ▲ {$i->upvotes}" : '')),
            'url' => '/community/manage',
            'at' => $i->created_at?->toIso8601String(),
        ])->all());

        $followUps = CrmContact::whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay())
            ->whereNotIn('stage', ['lost'])->orderBy('next_follow_up_at')->take(10)->get();
        $tasks = CrmActivity::where('kind', 'task')->whereNull('done_at')->whereNotNull('due_at')->where('due_at', '<=', now()->endOfDay())
            ->orderBy('due_at')->take(10)->get();
        $names = CrmContact::whereIn('id', $tasks->pluck('contact_id'))->pluck('name', 'id');
        $groups[] = $this->group('crm', 'Follow-ups', 'mail', array_merge(
            $followUps->map(fn ($c) => [
                'title' => "Follow up with {$c->name}" . ($c->company ? " ({$c->company})" : ''),
                'meta' => $c->next_follow_up_at->isPast() && !$c->next_follow_up_at->isToday() ? 'overdue' : 'today',
                'url' => '/crm?contact=' . $c->id,
                'at' => $c->next_follow_up_at->toIso8601String(),
            ])->all(),
            $tasks->map(fn ($t) => [
                'title' => mb_substr($t->body, 0, 90),
                'meta' => $names[$t->contact_id] ?? '',
                'url' => '/crm?contact=' . $t->contact_id,
                'at' => $t->due_at?->toIso8601String(),
            ])->all(),
        ));

        $drafts = SocialPost::whereNotNull('social_agent_id')->where('approval', 'pending')->latest()->take(10)->get(['id', 'platform', 'title', 'body', 'created_at']);
        $groups[] = $this->group('approvals', 'Agent drafts to approve', 'bot', $drafts->map(fn ($p) => [
            'title' => $p->title ?: mb_substr(strip_tags((string)$p->body), 0, 90),
            'meta' => $p->platform,
            'url' => '/posts/' . $p->id,
            'at' => $p->created_at?->toIso8601String(),
        ])->all());

        $groups = array_values(array_filter($groups, fn ($g) => $g['count'] > 0));

        return response()->json([
            'total' => array_sum(array_column($groups, 'count')),
            'groups' => $groups,
        ]);
    }

    private function group(string $id, string $title, string $icon, array $items): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'icon' => $icon,
            'count' => count($items),
            'items' => $items,
        ];
    }
}
