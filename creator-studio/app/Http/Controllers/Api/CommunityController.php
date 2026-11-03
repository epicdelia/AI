<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    private const DEFAULT_KITS = [
        [
            'id' => 'kit-ai-agent',
            'title' => 'Production AI Agent & Tool Router',
            'category' => 'AI Architecture',
            'badge' => 'LangGraph + FastAPI',
            'desc' => 'Delia’s personal agent boilerplate with multi-model fallback, retry backoff, and JSON schema validation.',
            'stars' => 1420,
            'github' => 'https://github.com/thetechunicorn/ai-agent-starter',
            'clone' => 'git clone https://github.com/thetechunicorn/ai-agent-starter.git',
            'features' => ['FastAPI Async Endpoints', 'LangGraph State Graph', 'Tool Call Reflection', 'Brotli Stream Compression'],
        ],
        [
            'id' => 'kit-zero-cls',
            'title' => 'Zero-CLS High Performance Landing Template',
            'category' => 'Frontend Performance',
            'badge' => 'Vite + CSS Prebundle',
            'desc' => 'The exact zero-layout-shift architecture running on the Tech Unicorn site with 100/100 Lighthouse score.',
            'stars' => 980,
            'github' => 'https://github.com/thetechunicorn/zero-cls-vite-landing',
            'clone' => 'git clone https://github.com/thetechunicorn/zero-cls-vite-landing.git',
            'features' => ['Frame-0 Layout Reservations', 'Preconnected Fonts', 'In-Memory SWR Cache', 'Brotli/Gzip Build Pipeline'],
        ],
        [
            'id' => 'kit-rag-vector',
            'title' => 'Enterprise Semantic RAG & Vector Engine',
            'category' => 'Data & Retrieval',
            'badge' => 'pgvector + Hybrid Search',
            'desc' => 'High-throughput semantic search pipeline with BM25 keyword reranking and chunk caching.',
            'stars' => 840,
            'github' => 'https://github.com/thetechunicorn/rag-vector-starter',
            'clone' => 'git clone https://github.com/thetechunicorn/rag-vector-starter.git',
            'features' => ['PostgreSQL pgvector', 'Reciprocal Rank Fusion', 'Embedding Cache', 'Metadata Partitioning'],
        ],
    ];

    private const DEFAULT_DISPATCHES = [
        [
            'id' => 'disp-01',
            'title' => 'How We Designed the 0.00 CLS Architecture for Creator Studio',
            'date' => '2026-11-02',
            'read_time' => '6 min read',
            'summary' => 'Deep dive into eliminating FOUC, pre-seeding intrinsic element dimensions, and batching hydration with Promise.allSettled.',
            'tags' => ['WebPerf', 'CSS', 'Vite', 'Architecture'],
        ],
        [
            'id' => 'disp-02',
            'title' => 'Prompt Engineering Patterns from 3 Years of Building Production Agents',
            'date' => '2026-10-28',
            'read_time' => '8 min read',
            'summary' => 'System prompts that actually prevent hallucinations, structured JSON enforcement techniques, and dynamic tool definitions.',
            'tags' => ['AI', 'LLM', 'Prompting', 'Production'],
        ],
        [
            'id' => 'disp-03',
            'title' => 'The Solo Engineer’s Tech Stack for $20k/mo MRR',
            'date' => '2026-10-15',
            'read_time' => '5 min read',
            'summary' => 'Why I pick Cloud Run over Kubernetes, SQLite/PostgreSQL over DynamoDB, and single-binary deploys.',
            'tags' => ['SaaS', 'CloudRun', 'SoloFounder', 'Productivity'],
        ],
    ];

    private const DEFAULT_PERKS = [
        [
            'id' => 'perk-gcp',
            'brand' => 'Google Cloud',
            'offer' => '$350 in Cloud Run & Gemini Credits',
            'code' => 'TECHUNICORN350',
            'url' => 'https://cloud.google.com/free',
            'badge' => 'Verified Partner',
        ],
        [
            'id' => 'perk-supabase',
            'brand' => 'Supabase',
            'offer' => '3 Months Pro Plan Free',
            'code' => 'DELIA_SUPA_2026',
            'url' => 'https://supabase.com',
            'badge' => 'Database Perk',
        ],
        [
            'id' => 'perk-cursor',
            'brand' => 'Cursor AI',
            'offer' => '20% Off Annual Developer License',
            'code' => 'DELIA_CODE_20',
            'url' => 'https://cursor.com',
            'badge' => 'Editor Partner',
        ],
    ];

    public function hub(Request $request): JsonResponse
    {
        $amaList = json_decode((string)Setting::get('community_ama_questions', '[]'), true) ?: [
            [
                'id' => 1,
                'author' => 'Alex (ML Engineer)',
                'question' => 'How do you structure async error recovery when tools fail inside a long multi-agent run?',
                'upvotes' => 47,
                'answer' => 'We use idempotent checkpointing with step-level rollback states. Never retry the entire graph; isolate the failed subagent node and pass the validation error message back as a correction hint.',
                'answered_at' => '2026-11-01',
            ],
            [
                'id' => 2,
                'author' => 'Elena (Founder)',
                'question' => 'For an early-stage MVP, when should you switch from SQLite to Managed Postgres?',
                'upvotes' => 38,
                'answer' => 'Only when you require multi-region write replicas or concurrent connection pools exceeding 50 web workers. SQLite with WAL mode easily handles 200 req/sec with sub-millisecond response times.',
                'answered_at' => '2026-10-30',
            ],
        ];

        return response()->json([
            'kits' => self::DEFAULT_KITS,
            'dispatches' => self::DEFAULT_DISPATCHES,
            'perks' => self::DEFAULT_PERKS,
            'ama' => $amaList,
            'member' => [
                'name' => $request->user()->name,
                'role' => $request->user()->role()->label(),
                'joined' => $request->user()->created_at->format('M Y'),
            ],
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|min:10|max:500',
        ]);

        $amaList = json_decode((string)Setting::get('community_ama_questions', '[]'), true) ?: [];
        $newQ = [
            'id' => count($amaList) + 1,
            'author' => $request->user()->name,
            'question' => trim($data['question']),
            'upvotes' => 1,
            'answer' => null,
            'answered_at' => null,
            'created_at' => now()->toIso8601String(),
        ];
        array_unshift($amaList, $newQ);
        Setting::put('community_ama_questions', json_encode(array_slice($amaList, 0, 50)));

        AgentEvent::log('community', "New AMA question from {$request->user()->name}: \"{$newQ['question']}\"");

        return response()->json([
            'ok' => true,
            'message' => 'Your question has been submitted to Delia’s AMA board!',
            'item' => $newQ,
        ]);
    }

    public function upvote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'required|integer',
        ]);

        $amaList = json_decode((string)Setting::get('community_ama_questions', '[]'), true) ?: [];
        $found = false;
        foreach ($amaList as &$q) {
            if ($q['id'] === (int)$data['id']) {
                $q['upvotes'] = ($q['upvotes'] ?? 0) + 1;
                $found = true;
                break;
            }
        }
        if ($found) {
            Setting::put('community_ama_questions', json_encode($amaList));
        }

        return response()->json(['ok' => true]);
    }
}
