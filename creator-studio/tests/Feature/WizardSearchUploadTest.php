<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Memory\MemoryChunk;
use App\Domains\Toolkit\DocumentImporter;
use App\Domains\Toolkit\Models\Document;
use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Services\Llm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * Script wizard, Ctrl+K search and document upload (ported from MagicAI).
 */
class WizardSearchUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'd@example.com',
            'password' => 'secret123',
        ]));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    // ---- Upload ----------------------------------------------------------------------

    public function test_pdf_upload_becomes_library_document_and_memory(): void
    {
        $file = UploadedFile::fake()->createWithContent('Sponsor brief.pdf', $this->pdf('Mention the student discount code DELIA20 in the first minute'));
        $res = $this->post('/api/documents/upload', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $doc = Document::findOrFail($res->json('document.id'));
        $this->assertSame('Sponsor Brief', $doc->title);
        $this->assertSame('upload:pdf', $doc->tool);
        $this->assertStringContainsString('DELIA20', $doc->content);
        $this->assertGreaterThan(0, MemoryChunk::where('source_type', 'document')->where('source_id', $doc->id)->count());
        $this->getJson('/api/memory/search?q=discount+code')->assertJsonPath('results.0.source_id', $doc->id);
    }

    public function test_subtitles_text_and_bad_files(): void
    {
        $srt = "1\n00:00:01,000 --> 00:00:03,000\nHey it's me\n\n2\n00:00:03,000 --> 00:00:05,000\n<i>Today we test</i> budget apps\n";
        $this->assertSame("Hey it's me Today we test budget apps", DocumentImporter::subtitles($srt));
        $vtt = "WEBVTT\n\n00:00.000 --> 00:02.000\nline one\n\n00:02.000 --> 00:04.000\nline one\n\n00:04.000 --> 00:06.000\nline two";
        $this->assertSame('line one line two', DocumentImporter::subtitles($vtt), 'rolling auto-caption repeats removed');

        $this->post('/api/documents/upload', [
            'file' => UploadedFile::fake()->createWithContent('old-video.srt', $srt),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()->assertJsonPath('document.tool', 'upload:srt');
        $this->post('/api/documents/upload', [
            'file' => UploadedFile::fake()->createWithContent('notes.md', "# Brand rules\nNever say guys. Always warm."),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()->assertJsonPath('document.title', 'Notes');
        $this->post('/api/documents/upload', [
            'file' => UploadedFile::fake()->create('photo.png', 10, 'image/png'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'Unsupported'));
        $this->post('/api/documents/upload', [
            'file' => UploadedFile::fake()->createWithContent('scan.pdf', $this->pdf('')),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'scan'));
    }

    public function test_docx_upload(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension not loaded');
        }
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="x"><w:body><w:p><w:r><w:t>Brand deal &amp; rules</w:t></w:r></w:p><w:p><w:r><w:t>Second paragraph</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $res = $this->post('/api/documents/upload', [
            'file' => new UploadedFile($path, 'Deal terms.docx', null, null, true),
        ], [
            'Accept' => 'application/json',
        ])->assertOk();
        $this->assertSame("Brand deal & rules\n\nSecond paragraph", Document::find($res->json('document.id'))->content);
        $this->getJson('/api/documents/' . $res->json('document.id'))->assertOk()->assertJsonPath('document.title', 'Deal Terms');
    }

    // ---- Ctrl+K search ---------------------------------------------------------------

    public function test_global_search_groups(): void
    {
        $script = Script::create([
            'title' => 'Thrift flip budget',
            'body' => 'x',
        ]);
        Idea::create([
            'title' => 'Budget grocery haul',
            'status' => 'new',
        ]);
        Document::create([
            'title' => 'Budget titles',
            'content' => 'Option 1',
            'tool' => 'tool:yt_titles',
        ]);
        $chat = Conversation::create([
            'title' => 'Random chat',
        ]);
        $chat->messages()->create([
            'role' => 'user',
            'content' => 'how do I budget for a camera',
        ]);

        $groups = collect($this->getJson('/api/search?q=budget')->assertOk()->json('groups'))->keyBy('title');
        $this->assertSame("/scripts/{$script->id}", $groups['Scripts']['items'][0]['url']);
        $this->assertSame('Idea · new', $groups['Ideas']['items'][0]['context']);
        $this->assertStringStartsWith('/library?doc=', $groups['Library']['items'][0]['url']);
        $this->assertSame("/chat/{$chat->id}", $groups['Chats']['items'][0]['url'], 'matches message content');

        $groups = collect($this->getJson('/api/search?q=thumbnail')->json('groups'))->keyBy('title');
        $this->assertSame('/tools/yt_thumbnail', $groups['Toolkit']['items'][0]['url']);
        $this->assertSame('Toolkit', $groups['Pages']['items'][0]['label']);

        $this->assertSame([], $this->getJson('/api/search?q=zzzqqq')->json('groups'));
        $this->getJson('/api/search?q=100%25')->assertOk(); // LIKE wildcards are escaped
    }

    // ---- Script wizard ---------------------------------------------------------------

    public function test_wizard_titles_outlines_create_and_draft_follows_plan(): void
    {
        $idea = Idea::create([
            'title' => 'Budget apps',
            'premise' => 'I tested 5 apps',
            'status' => 'picked',
        ]);

        Llm::$fake = fn () => json_encode([
            'titles' => [[
                'title' => 'I Let 5 Apps Run My Money',
                'style' => 'curiosity',
                'why' => 'stakes',
            ], [
                'title' => '',
            ]],
        ]);
        $this->postJson('/api/wizard/titles', [
            'idea_id' => $idea->id,
        ])->assertOk()
            ->assertJsonCount(1, 'titles')->assertJsonPath('titles.0.style', 'curiosity');
        $this->postJson('/api/wizard/titles', [])->assertStatus(422);

        Llm::$fake = fn () => json_encode([
            'outlines' => [
                [
                    'name' => 'Countdown',
                    'chapters' => [[
                        'title' => 'Cold open',
                        'beat' => 'the £300 surprise',
                    ], [
                        'title' => 'App 5',
                        'beat' => '...',
                    ], [
                        'title' => 'Payoff',
                    ]],
                ],
                [
                    'name' => 'Too short',
                    'chapters' => [[
                        'title' => 'Only one',
                    ]],
                ],
            ],
        ]);
        $this->postJson('/api/wizard/outlines', [
            'idea_id' => $idea->id,
            'title' => 'I Let 5 Apps Run My Money',
            'minutes' => 12,
        ])->assertOk()
            ->assertJsonCount(1, 'outlines')->assertJsonPath('outlines.0.chapters.2.beat', '');

        $id = $this->postJson('/api/wizard/create', [
            'idea_id' => $idea->id,
            'title' => 'I Let 5 Apps Run My Money',
            'minutes' => 12,
            'chapters' => [[
                'title' => 'Cold open',
                'beat' => 'the £300 surprise',
            ], [
                'title' => 'Verdict',
                'beat' => '',
            ]],
        ])->assertOk()->json('script.id');
        $this->assertSame('scripted', $idea->fresh()->status);
        $this->assertSame(12, Script::find($id)->outline['minutes']);

        $prompt = null;
        Llm::$fake = function ($system, $user) use (&$prompt) {
            $prompt = $user;

            return 'Draft text';
        };
        $this->postJson("/api/stream/scripts/{$id}/draft")->assertOk()->streamedContent();
        $this->assertStringContainsString('Approved title: I Let 5 Apps Run My Money', $prompt);
        $this->assertStringContainsString('1. Cold open: the £300 surprise', $prompt);
        $this->assertStringContainsString('~1800 spoken words', $prompt);
        $this->assertSame('Draft text', Script::find($id)->body);
    }

    public function test_wizard_works_without_an_idea(): void
    {
        $id = $this->postJson('/api/wizard/create', [
            'topic' => 'My first film camera',
            'title' => 'Film for beginners',
            'chapters' => [[
                'title' => 'A',
            ], [
                'title' => 'B',
            ]],
        ])->assertOk()->json('script.id');

        Llm::$fake = fn () => 'Draft';
        $this->postJson("/api/stream/scripts/{$id}/draft")->assertOk()->streamedContent();
        $this->assertSame('Draft', Script::find($id)->body);
        $this->postJson('/api/wizard/create', [
            'title' => 'x',
            'chapters' => [[
                'title' => 'only',
            ]],
        ])->assertStatus(422);
    }

    /**
     * A minimal valid one-page PDF with real text, xref offsets computed.
     */
    private function pdf(string $text): string
    {
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
