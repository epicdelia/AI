<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Overview of team members, production pipeline, active assignments, and publishing QA.
     */
    public function index(Request $request): JsonResponse
    {
        $staffUsers = User::whereIn('role', [Role::Admin->value, Role::Staff->value])
            ->select('id', 'name', 'email', 'role', 'created_at')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role instanceof Role ? $u->role->value : (string)$u->role,
                'role_label' => ($u->role instanceof Role ? $u->role : Role::tryFrom((string)$u->role) ?? Role::Staff)->label(),
            ]);

        $tasks = Setting::get('team_production_tasks', $this->defaultTasks());
        $qaItems = Setting::get('team_qa_checklist', $this->defaultQaChecklist());

        // Calculate pipeline metrics
        $stageCounts = [
            'idea' => 0,
            'script' => 0,
            'filming' => 0,
            'editing' => 0,
            'review' => 0,
            'scheduled' => 0,
            'published' => 0,
        ];

        foreach ($tasks as $t) {
            $st = $t['stage'] ?? 'idea';
            if (isset($stageCounts[$st])) {
                $stageCounts[$st]++;
            }
        }

        return response()->json([
            'members' => $staffUsers,
            'tasks' => $tasks,
            'qa' => $qaItems,
            'metrics' => [
                'total_tasks' => count($tasks),
                'in_progress' => ($stageCounts['filming'] + $stageCounts['editing'] + $stageCounts['review']),
                'ready_to_schedule' => $stageCounts['review'],
                'stage_counts' => $stageCounts,
                'velocity_monthly' => 14,
            ],
            'roles_available' => [
                'Video Editor',
                'Thumbnail Artist',
                'Copywriter',
                'Social Manager',
                'Researcher',
                'Production Lead',
            ],
        ]);
    }

    /**
     * Create a production assignment or video task.
     */
    public function createTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'stage' => 'required|string|in:idea,script,filming,editing,review,scheduled,published',
            'assignee_name' => 'nullable|string|max:100',
            'role_specialty' => 'nullable|string|max:100',
            'due_date' => 'nullable|string|max:50',
            'priority' => 'nullable|string|in:urgent,high,medium,normal',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tasks = Setting::get('team_production_tasks', $this->defaultTasks());

        $newTask = [
            'id' => 'task_' . Str::random(8),
            'title' => $data['title'],
            'stage' => $data['stage'],
            'assignee_name' => $data['assignee_name'] ?? 'Unassigned',
            'role_specialty' => $data['role_specialty'] ?? 'Video Editor',
            'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+3 days')),
            'priority' => $data['priority'] ?? 'high',
            'notes' => $data['notes'] ?? '',
            'created_at' => now()->toIso8601String(),
            'completed' => ($data['stage'] === 'published'),
        ];

        array_unshift($tasks, $newTask);
        Setting::put('team_production_tasks', $tasks);

        return response()->json([
            'ok' => true,
            'message' => 'Task assigned to production queue.',
            'task' => $newTask,
        ], 201);
    }

    /**
     * Update task stage, assignee, or completion.
     */
    public function updateTask(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'stage' => 'nullable|string|in:idea,script,filming,editing,review,scheduled,published',
            'assignee_name' => 'nullable|string|max:100',
            'priority' => 'nullable|string|in:urgent,high,medium,normal',
            'completed' => 'nullable|boolean',
        ]);

        $tasks = Setting::get('team_production_tasks', $this->defaultTasks());
        $found = false;

        foreach ($tasks as &$task) {
            if ($task['id'] === $id) {
                if (isset($data['stage'])) {
                    $task['stage'] = $data['stage'];
                    if ($data['stage'] === 'published') {
                        $task['completed'] = true;
                    }
                }
                if (isset($data['assignee_name'])) {
                    $task['assignee_name'] = $data['assignee_name'];
                }
                if (isset($data['priority'])) {
                    $task['priority'] = $data['priority'];
                }
                if (isset($data['completed'])) {
                    $task['completed'] = (bool)$data['completed'];
                    if ($task['completed']) {
                        $task['stage'] = 'published';
                    }
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json(['error' => 'Task not found.'], 404);
        }

        Setting::put('team_production_tasks', $tasks);

        return response()->json([
            'ok' => true,
            'message' => 'Task updated.',
            'tasks' => $tasks,
        ]);
    }

    /**
     * Toggle a pre-publishing QA item.
     */
    public function toggleQaItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string',
        ]);

        $qaItems = Setting::get('team_qa_checklist', $this->defaultQaChecklist());
        foreach ($qaItems as &$it) {
            if ($it['key'] === $data['key']) {
                $it['passed'] = !$it['passed'];
                break;
            }
        }

        Setting::put('team_qa_checklist', $qaItems);

        return response()->json([
            'ok' => true,
            'qa' => $qaItems,
        ]);
    }

    /**
     * Admin provisions a staff user account for Delia's employees.
     */
    public function inviteStaff(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        if ($currentUser->role() !== Role::Admin) {
            return response()->json(['error' => 'Only the studio administrator can provision staff accounts.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $staffUser = new User([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
        ]);

        $staffUser->forceFill([
            'role' => Role::Staff,
            'email_verified_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => "Staff member {$staffUser->name} provisioned successfully with studio access.",
            'user' => [
                'id' => $staffUser->id,
                'name' => $staffUser->name,
                'email' => $staffUser->email,
                'role' => 'staff',
                'role_label' => 'Staff / Team',
            ],
        ], 201);
    }

    private function defaultTasks(): array
    {
        return [
            [
                'id' => 'task_1',
                'title' => 'Cut 3 High-Energy Shorts from Cursor AI Full Breakdown',
                'stage' => 'editing',
                'assignee_name' => 'Alex (Editor)',
                'role_specialty' => 'Video Editor',
                'due_date' => date('Y-m-d', strtotime('+1 day')),
                'priority' => 'urgent',
                'notes' => 'Focus on the 01:45 prompt injection tip and 04:12 file tree speedrun.',
                'created_at' => now()->subDays(1)->toIso8601String(),
                'completed' => false,
            ],
            [
                'id' => 'task_2',
                'title' => 'A/B Test 3 High-Contrast 3D Thumbnails for BigQuery Architecture',
                'stage' => 'review',
                'assignee_name' => 'Elena (Design)',
                'role_specialty' => 'Thumbnail Artist',
                'due_date' => date('Y-m-d', strtotime('+2 days')),
                'priority' => 'high',
                'notes' => 'Option A with yellow neon border, Option B with ex-Google badge.',
                'created_at' => now()->subDays(2)->toIso8601String(),
                'completed' => false,
            ],
            [
                'id' => 'task_3',
                'title' => 'Draft Deep-Dive LinkedIn Carousel for AI Coding Copilot',
                'stage' => 'script',
                'assignee_name' => 'Marcus (Copywriter)',
                'role_specialty' => 'Copywriter',
                'due_date' => date('Y-m-d', strtotime('+3 days')),
                'priority' => 'medium',
                'notes' => '10-slide template with code snippet highlights and key takeaways.',
                'created_at' => now()->subDays(3)->toIso8601String(),
                'completed' => false,
            ],
            [
                'id' => 'task_4',
                'title' => 'Pre-Film Audio Calibration & 4K B-Roll Capture for Studio VLOG',
                'stage' => 'filming',
                'assignee_name' => 'Delia Lazarescu',
                'role_specialty' => 'Production Lead',
                'due_date' => date('Y-m-d', strtotime('+4 days')),
                'priority' => 'normal',
                'notes' => 'Dual lavalier wireless test and desk overhead camera rig check.',
                'created_at' => now()->subDays(4)->toIso8601String(),
                'completed' => false,
            ],
        ];
    }

    private function defaultQaChecklist(): array
    {
        return [
            ['key' => 'retention_hook', 'title' => 'First 30s Retention Hook Audited', 'desc' => 'Immediate visual payoff, question framed, zero dead air or slow logo intro.', 'passed' => true],
            ['key' => 'audio_norm', 'title' => 'Loudness Normalized to -14 LUFS', 'desc' => 'Compliant with YouTube / Spotify / Reels normalization curve with no clipping.', 'passed' => true],
            ['key' => 'thumbnail_contrast', 'title' => 'Mobile Thumbnail Tested at 120px', 'desc' => 'High contrast text, readable face expression, passes dark/light feed simulation.', 'passed' => true],
            ['key' => 'sponsor_disclosure', 'title' => 'Sponsorship & Affiliate Disclosures Verified', 'desc' => 'FTC compliant #ad disclosure, custom UTM tracking links verified in description.', 'passed' => false],
            ['key' => 'chapters_timestamps', 'title' => 'Video Chapters & Interactive Timestamps Formatted', 'desc' => '00:00 start, clear topic descriptions for SEO indexing and player bookmarks.', 'passed' => true],
            ['key' => 'community_repurpose', 'title' => 'Cross-Platform Repurposing Package Queued', 'desc' => 'LinkedIn carousel, X thread, and Instagram Reel cuts ready in drafts.', 'passed' => false],
        ];
    }
}

