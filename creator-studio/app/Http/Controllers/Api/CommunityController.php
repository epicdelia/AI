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

    private const DEFAULT_BLUEPRINTS = [
        [
            'id' => 'bp-rag-hybrid',
            'title' => 'Hybrid Semantic + BM25 RAG Architecture',
            'category' => 'AI Retrieval',
            'desc' => 'High-throughput vector search pipeline combining pgvector embeddings with Elasticsearch/BM25 reciprocal rank fusion.',
            'components' => ['FastAPI Gateway', 'PostgreSQL + pgvector', 'Redis Cache', 'OpenAI / Gemini Embeddings', 'Reranker Model'],
            'latency_p95' => '42ms',
            'cost_tier' => 'Low ($15/mo Cloud Run)',
            'recommended_stack' => 'Python 3.12 + FastAPI + pgvector',
        ],
        [
            'id' => 'bp-agent-swarm',
            'title' => 'Hierarchical Multi-Agent Task Swarm',
            'category' => 'Agent Systems',
            'desc' => 'Autonomous supervisor and subagent worker swarm with state checkpointing, error recovery, and reflection loops.',
            'components' => ['LangGraph Supervisor', 'Worker Nodes', 'Tool Router', 'SQLite/Postgres State', 'Streaming SSE'],
            'latency_p95' => '120ms (first token)',
            'cost_tier' => 'Medium (Token based)',
            'recommended_stack' => 'Python / TypeScript + LiteLLM',
        ],
        [
            'id' => 'bp-zero-cls',
            'title' => 'Zero-CLS Edge Rendered Web Pipeline',
            'category' => 'Frontend Perf',
            'desc' => '100/100 Lighthouse web application architecture with intrinsic aspect-ratio reservation, pre-compressed Brotli, and zero FOUC.',
            'components' => ['Vite Build Precompressor', 'Edge Worker Cache', 'Optimized Font Fallbacks', 'SSR Layout Injection'],
            'latency_p95' => '18ms (Edge TTFB)',
            'cost_tier' => 'Free (Cloudflare Pages / Vercel)',
            'recommended_stack' => 'Vanilla JS / React + Vite',
        ],
        [
            'id' => 'bp-event-bus',
            'title' => 'Real-Time WebSocket & Event Dispatch Bus',
            'category' => 'Backend Systems',
            'desc' => 'Fault-tolerant pub/sub event pipeline handling 100,000+ simultaneous connections with sub-10ms delivery.',
            'components' => ['Redis Pub/Sub', 'WebSocket Server', 'Worker Queue', 'Postgres TimescaleDB'],
            'latency_p95' => '8ms',
            'cost_tier' => 'Low ($25/mo)',
            'recommended_stack' => 'Node.js / Go + Redis',
        ],
    ];

    private const DEFAULT_SHOWCASE = [
        [
            'id' => 1,
            'title' => 'AetherCLI: Autonomous Terminal Agent',
            'author' => 'Marcus Chen',
            'desc' => 'Built using Delia’s AI Agent Starter kit. Implements local Git reflection and automatic commit message generation.',
            'demo_url' => 'https://github.com/aethercli/agent',
            'repo_url' => 'https://github.com/aethercli/agent',
            'upvotes' => 128,
            'is_featured' => true,
            'tags' => ['Agent', 'CLI', 'Python'],
            'created_at' => '2026-10-25',
        ],
        [
            'id' => 2,
            'title' => 'PromptMatrix: Visual Hook Tester',
            'author' => 'Sarah Lindqvist',
            'desc' => 'Adapted from Delia’s viral hook matrix prompts into an interactive web app with instant sentiment scores.',
            'demo_url' => 'https://promptmatrix.dev',
            'repo_url' => 'https://github.com/sarah/prompt-matrix',
            'upvotes' => 94,
            'is_featured' => true,
            'tags' => ['Frontend', 'Vite', 'YouTube'],
            'created_at' => '2026-10-29',
        ],
        [
            'id' => 3,
            'title' => 'DocuVector: Self-Hosted PDF RAG',
            'author' => 'Devon Vance',
            'desc' => 'Drop-in semantic search for technical manuals with offline Ollama embedding fallback.',
            'demo_url' => 'https://github.com/devon/docu-vector',
            'repo_url' => 'https://github.com/devon/docu-vector',
            'upvotes' => 76,
            'is_featured' => false,
            'tags' => ['RAG', 'PostgreSQL', 'Docker'],
            'created_at' => '2026-11-01',
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

        $showcaseList = json_decode((string)Setting::get('community_showcase_projects', '[]'), true) ?: self::DEFAULT_SHOWCASE;

        return response()->json([
            'kits' => self::DEFAULT_KITS,
            'blueprints' => self::DEFAULT_BLUEPRINTS,
            'dispatches' => self::DEFAULT_DISPATCHES,
            'perks' => self::DEFAULT_PERKS,
            'ama' => $amaList,
            'showcase' => $showcaseList,
            'member' => [
                'name' => $request->user()->name,
                'role' => $request->user()->role()->label(),
                'joined' => $request->user()->created_at->format('M Y'),
            ],
        ]);
    }

    public function blueprints(Request $request): JsonResponse
    {
        return response()->json([
            'blueprints' => self::DEFAULT_BLUEPRINTS,
        ]);
    }

    public function scaffoldBlueprint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'blueprint_id' => 'required|string',
            'project_name' => 'nullable|string|max:50',
            'database' => 'nullable|string|in:postgres,sqlite',
            'cache' => 'nullable|string|in:redis,memory',
        ]);

        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['project_name'] ?? 'tech-unicorn-blueprint');
        $db = $data['database'] ?? 'postgres';
        $cache = $data['cache'] ?? 'redis';
        $dbImage = ($db === 'postgres') ? 'pgvector/pgvector:pg16' : 'alpine:latest';

        // Generate tailored scaffold manifest
        $dockerCompose = <<<YAML
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - APP_NAME={$name}
      - DB_TYPE={$db}
      - CACHE_DRIVER={$cache}
    depends_on:
      - db
      - cache

  db:
    image: {$dbImage}
    ports:
      - "5432:5432"
    environment:
      - POSTGRES_DB={$name}
      - POSTGRES_USER=app
      - POSTGRES_PASSWORD=secret

  cache:
    image: redis:7-alpine
    ports:
      - "6379:6379"
YAML;

        $readme = <<<MD
# {$name}

Built with Delia's Architecture Blueprint Scaffold.
- Database: {$db}
- Cache: {$cache}

## Quickstart
```bash
docker compose up -d
curl http://localhost:8000/health
```
MD;

        return response()->json([
            'ok' => true,
            'project_name' => $name,
            'blueprint_id' => $data['blueprint_id'],
            'files' => [
                'docker_compose' => $dockerCompose,
                'docker-compose.yml' => $dockerCompose,
                'readme' => $readme,
                'README.md' => $readme,
                'env' => "APP_NAME={$name}\nDB_TYPE={$db}\nCACHE_DRIVER={$cache}\nAPI_KEY=your_key_here\n",
                '.env.example' => "APP_NAME={$name}\nDB_TYPE={$db}\nCACHE_DRIVER={$cache}\nAPI_KEY=your_key_here\n",
            ],
            'message' => 'Tailored architecture blueprint scaffold ready for instant deployment.',
        ]);
    }

    public function showcase(Request $request): JsonResponse
    {
        $showcaseList = json_decode((string)Setting::get('community_showcase_projects', '[]'), true) ?: self::DEFAULT_SHOWCASE;

        return response()->json([
            'showcase' => $showcaseList,
            'showcases' => $showcaseList,
        ]);
    }

    public function submitShowcase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|min:5|max:100',
            'desc' => 'nullable|string|min:10|max:500',
            'description' => 'nullable|string|min:10|max:500',
            'repo_url' => 'required|url',
            'demo_url' => 'nullable|url',
            'tags' => 'nullable|string',
        ]);

        $showcaseList = json_decode((string)Setting::get('community_showcase_projects', '[]'), true) ?: self::DEFAULT_SHOWCASE;
        $description = trim($data['description'] ?? $data['desc'] ?? 'A community project built with Tech Unicorn blueprints.');

        $newShowcase = [
            'id' => count($showcaseList) + 1,
            'title' => trim($data['title']),
            'author' => $request->user()->name,
            'desc' => $description,
            'description' => $description,
            'repo_url' => $data['repo_url'],
            'demo_url' => $data['demo_url'] ?? $data['repo_url'],
            'upvotes' => 1,
            'is_featured' => false,
            'tags' => array_filter(array_map('trim', explode(',', $data['tags'] ?? 'AI,Web'))),
            'created_at' => now()->toDateString(),
        ];

        array_unshift($showcaseList, $newShowcase);
        Setting::put('community_showcase_projects', json_encode(array_slice($showcaseList, 0, 100)));

        AgentEvent::log('community', "New showcase submission from {$request->user()->name}: \"{$newShowcase['title']}\"");

        return response()->json([
            'ok' => true,
            'message' => 'Project submitted to Delia’s community showcase!',
            'item' => $newShowcase,
            'project' => $newShowcase,
        ], 201);
    }

    public function upvoteShowcase(Request $request, int $id): JsonResponse
    {
        $showcaseList = json_decode((string)Setting::get('community_showcase_projects', '[]'), true) ?: self::DEFAULT_SHOWCASE;
        $found = false;

        $upvotes = 1;
        foreach ($showcaseList as &$item) {
            if ($item['id'] === $id) {
                $item['upvotes'] = ($item['upvotes'] ?? 0) + 1;
                $upvotes = $item['upvotes'];
                $found = true;
                break;
            }
        }

        if ($found) {
            Setting::put('community_showcase_projects', json_encode($showcaseList));
        }

        return response()->json(['ok' => true, 'upvotes' => $upvotes]);
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

