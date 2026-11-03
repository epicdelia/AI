<?php

namespace App\Services\Ai;

use App\Domains\Engine\Concerns\HasStatusJsonResponse;
use App\Models\SettingTwo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsService
{
    use HasStatusJsonResponse;

    public const DEFAULT_ELEVENLABS_MODEL = 'eleven_flash_v2_5';

    public const DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH = 'eleven_turbo_v2';

    public const DEFAULT_ELEVENLABS_VOICE_ID = 'cjVigY5qzO86Huf0OWal';

    private $xi_api_key;

    // constructor
    public function __construct(
        private string $endpoint = 'https://api.elevenlabs.io/'
    ) {
        $settings = SettingTwo::getCache();
        $this->xi_api_key = $settings?->elevenlabs_api_key;
    }

    // ======= TTS and STT =======//

    /**
     * list of voices
     *
     * @param  string|null  $next_page_token
     * @param  int|null  $page_size
     * @param  string|null  $search
     * @param  string|null  $sort
     * @param  string|null  $sort_direction
     * @param  string|null  $voice_type
     * @param  string|null  $category
     * @param  string|null  $fine_tuning_state
     * @param  string|null  $collection_id
     * @param  bool|null  $include_total_count
     */
    /**
     * list shared (public library) voices
     *
     * @param  array<string, mixed>  $filters
     */
    public function getSharedVoices(array $filters = []): JsonResponse
    {
        $allowed = [
            'page_size', 'page', 'search', 'category', 'gender', 'age', 'accent',
            'language', 'use_cases', 'descriptives', 'featured', 'min_notice_period_days',
            'include_custom_rates', 'reader_app_enabled', 'owner_id', 'sort',
        ];
        $queryParams = $this->getParams(array_intersect_key($filters, array_flip($allowed)));

        $url = $this->endpoint . 'v1/shared-voices';
        $res = Http::withHeaders($this->getHeaders())->get($url, $queryParams);

        return $this->statusJsonResponse($res, 'get shared voices error:');
    }

    /**
     * add a shared voice to the account library
     */
    public function addSharedVoice(string $publicUserId, string $voiceId, string $newName): JsonResponse
    {
        $url = $this->endpoint . "v1/voices/add/{$publicUserId}/{$voiceId}";
        $res = Http::withHeaders($this->getHeaders())->post($url, ['new_name' => $newName]);

        if ($res->successful()) {
            return response()->json([
                'status'  => 'success',
                'resData' => $res->json(),
            ]);
        }

        Log::error('add shared voice error:', [$res->body()]);

        $detail = $res->json('detail');
        $code = is_array($detail) ? ($detail['code'] ?? $detail['type'] ?? null) : null;
        $apiMessage = is_array($detail) ? ($detail['message'] ?? null) : (is_string($detail) ? $detail : null);

        return response()->json([
            'status'  => 'error',
            'code'    => $code,
            'message' => $apiMessage ?: __('Something went wrong!'),
        ]);
    }

    /**
     * delete a voice from the user's account library
     */
    public function deleteVoice(string $voiceId): JsonResponse
    {
        $url = $this->endpoint . "v1/voices/{$voiceId}";
        $res = Http::withHeaders($this->getHeaders())->delete($url);

        return $this->statusJsonResponse($res, 'delete voice error:');
    }

    public function getListOfVoices(
        $next_page_token = null,
        $page_size = null,
        $search = null,
        $sort = null,
        $sort_direction = null,
        $voice_type = null,
        $category = null,
        $fine_tuning_state = null,
        $collection_id = null,
        $include_total_count = null,
    ): JsonResponse {
        $queryParams = getFuncArgs($this, __FUNCTION__, func_get_args());
        $url = $this->endpoint . 'v2/voices';
        $res = Http::withHeaders($this->getHeaders())->get($url, $queryParams);

        return $this->statusJsonResponse($res, 'get list of voice error:');
    }

    /**
     * text to speech
     *
     * @param  string  $voice_id
     * @param  bool|null  $enable_logging
     * @param  string|null  $output_format
     * @param  string  $text
     * @param  string|null  $model_id
     * @param  string|null  $language_code
     * @param  object|null  $voice_setting
     * @param  array|null  $pronunciation_dictionary_locators
     * @param  int|null  $seed
     * @param  string|null  $previous_text
     * @param  string|null  $next_text
     * @param  array|null  $previous_request_ids
     * @param  array|null  $next_request_ids
     * @param  string|null  $apply_text_normalization
     * @param  bool|null  $apply_language_text_normalization
     *
     * @return JsonRepsone
     */
    public function createSpeech(
        $voice_id,
        $text,

        $enable_logging = null,
        $output_format = null,
        $model_id = null,
        $language_code = null,
        $voice_setting = null,
        $pronunciation_dictionary_locators = null,
        $seed = null,
        $previous_text = null,
        $next_text = null,
        $previous_request_ids = null,
        $next_request_ids = null,
        $apply_text_normalization = null,
        $apply_language_text_normalization = null
    ): JsonResponse {
        $url = $this->endpoint . 'v1/text-to-speech/' . $voice_id;
        $queryParams = $this->getParams([
            'enable_logging' => $enable_logging,
            'output_format'  => $output_format,
        ]);

        $requestParams = $this->getParams([
            'text'                              => $text,
            'model_id'                          => $model_id,
            'language_code'                     => $language_code,
            'voice_setting'                     => $voice_setting,
            'pronunciation_dictionary_locators' => $pronunciation_dictionary_locators,
            'seed'                              => $seed,
            'previous_text'                     => $previous_text,
            'next_text'                         => $next_text,
            'previous_request_ids'              => $previous_request_ids,
            'next_request_ids'                  => $next_request_ids,
            'apply_text_normalization'          => $apply_text_normalization,
            'apply_language_text_normalization' => $apply_language_text_normalization,
        ]);

        if (! empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $res = Http::withHeaders($this->getHeaders())->post($url, $requestParams);

        if ($res->successful()) {
            return response()->json([
                'status'  => 'success',
                'resData' => base64_encode($res->body()),
            ]);
        }

        Log::error('text to speech error: ', [$res->body()]);

        return response()->json([
            'status'  => 'error',
            'message' => __('Something went wrong!'),
        ]);
    }

    /**
     * text to speech with steaming audio
     *
     * @todo we have to use stream, but now It returns result in one response.
     *
     * @param  string  $voice_id
     * @param  bool|null  $enable_logging
     * @param  string|null  $output_format
     * @param  string  $text
     * @param  string|null  $model_id
     * @param  string|null  $language_code
     * @param  object|null  $voice_setting
     * @param  array|null  $pronunciation_dictionary_locators
     * @param  int|null  $seed
     * @param  string|null  $previous_text
     * @param  string|null  $next_text
     * @param  array|null  $previous_request_ids
     * @param  array|null  $next_request_ids
     * @param  string|null  $apply_text_normalization
     * @param  bool|null  $apply_language_text_normalization
     */
    public function streamSpeech(
        $voice_id,
        $text,

        $enable_logging = null,
        $output_format = null,
        $model_id = null,
        $language_code = null,
        $voice_setting = null,
        $pronunciation_dictionary_locators = null,
        $seed = null,
        $previous_text = null,
        $next_text = null,
        $previous_request_ids = null,
        $next_request_ids = null,
        $apply_text_normalization = null,
        $apply_language_text_normalization = null
    ): JsonResponse {
        $url = $this->endpoint . 'v1/text-to-speech/' . $voice_id . '/stream';
        $queryParams = $this->getParams([
            'enable_logging' => $enable_logging,
            'output_format'  => $output_format,
        ]);

        $requestParams = $this->getParams([
            'text'                              => $text,
            'model_id'                          => $model_id,
            'language_code'                     => $language_code,
            'voice_setting'                     => $voice_setting,
            'pronunciation_dictionary_locators' => $pronunciation_dictionary_locators,
            'seed'                              => $seed,
            'previous_text'                     => $previous_text,
            'next_text'                         => $next_text,
            'previous_request_ids'              => $previous_request_ids,
            'next_request_ids'                  => $next_request_ids,
            'apply_text_normalization'          => $apply_text_normalization,
            'apply_language_text_normalization' => $apply_language_text_normalization,
        ]);

        if (! empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $res = Http::withHeaders($this->getHeaders())->post($url, $requestParams);

        if ($res->successful()) {
            return response()->json([
                'status'  => 'success',
                'resData' => base64_encode($res->body()),
            ]);
        }

        Log::error('text to speech stream error: ', [$res->body()]);

        return response()->json([
            'status'  => 'error',
            'message' => __('Something went wrong!'),
        ]);
    }

    /**
     * speech to text
     *
     * @param  bool|null  $enable_logging
     * @param  string|null  $model_id  `scribe_v1` and `scribe_v1_experimental` are available now
     * @param  file|null  $file
     * @param  string|null  $language_code
     * @param  bool|null  $tag_audio_events
     * @param  int|null  $num_speakers
     * @param  string|null  $timestamps_granularity
     * @param  bool|null  $diarize
     * @param  array|null  $additional_formats
     * @param  string|null  $file_format
     * @param  string|null  $cloud_storage_url
     */
    public function createTranscript(
        ?UploadedFile $file = null,
        $model_id = 'scribe_v1',

        $enable_logging = null,
        $language_code = null,
        $tag_audio_events = null,
        $num_speakers = null,
        $timestamps_granularity = null,
        $diarize = null,
        $additional_formats = null,
        $file_format = null,
        $cloud_storage_url = null
    ): JsonResponse {
        $url = $this->endpoint . '/v1/speech-to-text';
        $queryParams = $this->getParams([
            'enable_logging' => $enable_logging,
        ]);

        $headers = $this->getHeaders();
        unset($headers['Content-Type']);

        $requestParams = $this->getParams([
            'model_id'               => $model_id,
            'language_code'          => $language_code,
            'tag_audio_events'       => $tag_audio_events,
            'num_speakers'           => $num_speakers,
            'timestamps_granularity' => $timestamps_granularity,
            'diarize'                => $diarize,
            'additional_formats'     => $additional_formats,
            'file_format'            => $file_format,
            'cloud_storage_url'      => $cloud_storage_url,
        ]);

        if (! empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $res = null;

        if ($file != null) {
            $res = Http::withHeaders($headers)->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())->asMultipart()->post($url, $requestParams);
        } else {
            if (! isset($requestParams['cloud_storage_url'])) {
                return response()->json([
                    'stauts'  => 'error',
                    'message' => 'You must provide file or cloud_storage_url',
                ]);
            }
            $res = Http::withHeaders($headers)->asMultipart()->post($url, $requestParams);
        }

        return $this->statusJsonResponse($res, 'speech to text error: ');
    }

    // ======= Agent =======//

    /**
     * create agent
     */
    public function createAgent(
        array $conversation_config,
        ?array $platform_settings = null,
        ?string $name = null
    ): JsonResponse {
        $url = $this->endpoint . 'v1/convai/agents/create';
        $requestParams = getFuncArgs($this, __FUNCTION__, func_get_args());
        $res = Http::withHeaders($this->getHeaders())->post($url, $requestParams);

        return $this->statusJsonResponse($res, 'create voice chatbot error:');
    }

    /**
     * get agent
     */
    public function getAgent(string $agent_id): JsonResponse
    {
        $url = $this->endpoint . 'v1/convai/agents/' . $agent_id;

        $res = Http::withHeaders($this->getHeaders())->get($url);

        return $this->statusJsonResponse($res, 'get agent error:');
    }

    /**
     * list agents
     */
    public function listAgents(
        ?string $cursor = null,
        ?int $page_size = null,
        ?string $search = null
    ): JsonResponse {
        $url = $this->endpoint . 'v1/convai/agents';
        $queryParams = getFuncArgs($this, __FUNCTION__, func_get_args());

        $res = Http::withHeaders($this->getHeaders())->get($url, $queryParams);

        return $this->statusJsonResponse($res, 'list agent error:');
    }

    /**
     * update agent
     */
    public function updateAgent(
        string $agent_id,
        ?array $conversation_config = null,
        ?array $platform_settings = null,
        ?string $name = null
    ): JsonResponse {
        $url = $this->endpoint . 'v1/convai/agents/' . $agent_id;
        $requestParams = $this->getParams([
            'conversation_config' => $conversation_config,
            'platform_settings'   => $platform_settings,
            'name'                => $name,
        ]);

        $res = Http::withHeaders($this->getHeaders())->patch($url, $requestParams);

        return $this->statusJsonResponse($res, 'update voice chatbot error:');
    }

    /**
     * delete agent
     */
    public function deleteAgent(string $agent_id): JsonResponse
    {
        $url = $this->endpoint . '/v1/convai/agents/' . $agent_id;

        $res = Http::withHeaders($this->getHeaders())->delete($url);

        return $this->statusJsonResponse($res, 'delete agent error:');
    }

    // ======= Tools =======//

    /**
     * create standalone tool
     *
     * @param  array<string, mixed>  $tool_config
     */
    public function createTool(array $tool_config): JsonResponse
    {
        $url = $this->endpoint . 'v1/convai/tools';

        $res = Http::withHeaders($this->getHeaders())->post($url, ['tool_config' => $tool_config]);

        return $this->statusJsonResponse($res, 'create tool error:');
    }

    /**
     * delete standalone tool
     */
    public function deleteTool(string $tool_id, bool $force = true): JsonResponse
    {
        $url = $this->endpoint . 'v1/convai/tools/' . $tool_id . '?force=' . ($force ? 'true' : 'false');

        $res = Http::withHeaders($this->getHeaders())->delete($url);

        return $this->statusJsonResponse($res, 'delete tool error:');
    }

    // ======= Knowledge Base =======//

    /**
     * list knowledgebase
     */
    public function listKnowledgebase(
        ?string $cursor = null,
        ?int $page_size = null,
        ?string $search = null,
        ?bool $show_only_owned_documents = null,
        ?string $types = null,
        ?bool $use_typesense = null
    ): JsonResponse {
        $url = $this->endpoint . 'v1/convai/knowledge-base';
        $queryParams = getFuncArgs($this, __FUNCTION__, func_get_args());

        $res = Http::withHeaders($this->getHeaders())->get($url, $queryParams);

        return $this->statusJsonResponse($res, 'list knowledge base error:');
    }

    /**
     * delete knowledge base
     */
    public function deleteKnowledgebaseDocument(
        string $documentation_id
    ): JsonResponse {
        $url = $this->endpoint . '/v1/convai/knowledge-base/' . $documentation_id;

        $res = Http::withHeaders($this->getHeaders())->delete($url);

        return $this->statusJsonResponse($res, 'delete documentation error:');
    }

    /**
     * get knowledge base document
     */
    public function getKnowledgebaseDocument(
        string $documentation_id
    ): JsonResponse {
        $url = $this->endpoint . '/v1/convai/knowledge-base/' . $documentation_id;

        $res = Http::withHeaders($this->getHeaders())->get($url);

        return $this->statusJsonResponse($res, 'get document error:');
    }

    /**
     * create knowledge base document from url
     */
    public function createKnowledgebaseDocFromUrl(
        string $url,
        ?string $name = null
    ): JsonResponse {
        $reqUrl = $this->endpoint . 'v1/convai/knowledge-base/url';
        $reqParams = getFuncArgs($this, __FUNCTION__, func_get_args());

        $res = Http::withHeaders($this->getHeaders())->post($reqUrl, $reqParams);

        return $this->statusJsonResponse($res, 'create doc from url error:');
    }

    /**
     * create knowledge base document from text
     */
    public function createKnowledgebaseDocFromText(
        string $text,
        ?string $name = null
    ): JsonResponse {
        $reqUrl = $this->endpoint . 'v1/convai/knowledge-base/text';
        $reqParams = getFuncArgs($this, __FUNCTION__, func_get_args());

        $res = Http::withHeaders($this->getHeaders())->post($reqUrl, $reqParams);

        return $this->statusJsonResponse($res, 'create doc from text error:');
    }

    /**
     * create knowledge base document from file
     */
    public function createKnowledgebaseDocFromFile(
        UploadedFile $file,
        ?string $name = null
    ): JsonResponse {
        $reqUrl = $this->endpoint . 'v1/convai/knowledge-base/file';
        $reqParams = getFuncArgs($this, __FUNCTION__, func_get_args());

        $headers = $this->getHeaders();
        unset($headers['Content-Type']);

        $res = Http::withHeaders($headers)->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())->asMultipart()->post($reqUrl, $reqParams);

        return $this->statusJsonResponse($res, 'create doc from file error:');
    }

    /**
     * compute rag index
     *
     * @param  string  $model  allowed values are `e5_mistral_7b_instruct` and `multilingual_e5_large_instruct`
     */
    public function computeRagIndex(
        string $documentation_id,
        string $model
    ): JsonResponse {
        $url = $this->endpoint . 'v1/convai/knowledge-base/' . $documentation_id;
        $res = Http::withHeaders($this->getHeaders())->post($url, ['model' => $model]);

        return $this->statusJsonResponse($res, 'compute rag index error:');
    }

    /**
     * get dependent agents
     */
    public function getDependentAgents(
        string $documentation_id,
        ?string $cursor = null,
        ?int $page_size = null
    ): JsonResponse {
        $url = $this->endpoint . "v1/convai/knowledge-base/$documentation_id/dependent-agents";
        $queryParams = $this->getParams([
            'cursor'    => $cursor,
            'page_size' => $page_size,
        ]);

        $res = Http::withHeaders($this->getHeaders())->get($url, $queryParams);

        return $this->statusJsonResponse($res, 'get dependent agents error:');
    }

    /**
     * get document content
     */
    public function getDocumentContent(
        string $documentation_id,
    ): JsonResponse {
        $url = $this->endpoint . "v1/convai/knowledge-base/$documentation_id/content";

        $res = Http::withHeaders($this->getHeaders())->get($url);

        return $this->statusJsonResponse($res, 'get document content error:');
    }

    /**
     * get details about a specific documentation part used by RAG
     */
    public function getDocumentChunk(
        string $documentation_id,
        string $chunk_id,
    ): JsonResponse {
        $url = $this->endpoint . "v1/convai/knowledge-base/$documentation_id/chunk/$chunk_id";

        $res = Http::withHeaders($this->getHeaders())->get($url);

        return $this->statusJsonResponse($res, 'get document content error:');
    }

    // ======= Conversations =======//

    /**
     * get all conversations of agents that user owns
     *
     * @param  string|null  $call_successful  These values are avaibale `succes`, `failure`, 'unknown`
     */
    public function getListConversations(
        ?string $cursor = null,
        ?string $agent_id = null,
        ?string $call_successful = null,
        ?int $page_size = null
    ): JsonResponse {
        $url = $this->endpoint . 'v1/convai/conversations';
        $reqParams = getFuncArgs($this, __FUNCTION__, func_get_args());

        $res = Http::withHeaders($this->getHeaders())->get($url, $reqParams);

        return $this->statusJsonResponse($res, 'get list all conversations error:');
    }

    /**
     * get the detail of particular conversation
     */
    public function getConversationDetail(
        string $conversation_id
    ): JsonResponse {
        $url = $this->endpoint . "v1/convai/conversations/$conversation_id";
        $res = Http::withHeaders($this->getHeaders())->get($url);

        return $this->statusJsonResponse($res, 'get detail of conversation error:');
    }

    /**
     * delete a particular conversation
     */
    public function deleteConversation(
        string $conversation_id
    ): JsonResponse {
        $url = $this->endpoint . "v1/convai/conversations/$conversation_id";
        $res = Http::withHeaders($this->getHeaders())->delete($url);

        return $this->statusJsonResponse($res, 'delete conversation error:');
    }

    /**
     * get conversation audio
     */
    public function getConversationAudio(
        string $conversation_id
    ): JsonResponse {
        $url = $this->endpoint . "v1/convai/conversations/$conversation_id/audio";
        $res = Http::withHeaders($this->getHeaders())->get($url);

        return $this->statusJsonResponse($res, 'get conversation audio error:');
    }

    /**
     * get signed url
     */
    public function getSignedUrl(
        string $agent_id
    ): JsonResponse {
        $url = $this->endpoint . 'v1/convai/conversation/token';
        $res = Http::withHeaders($this->getHeaders())->get($url, ['agent_id' => $agent_id]);

        return $this->statusJsonResponse($res, 'get signed url error:');
    }

    /**
     * send conversation feedback
     *
     * @param  string  $feedback  These values are available `like` and `dislike`
     */
    public function sendConversationFeedback(
        string $conversation_id,
        string $feedback
    ): JsonResponse {
        $url = $this->endpoint . "v1/convai/conversations/$conversation_id/feedback";
        $res = Http::withHeaders($this->getHeaders())->post($url, ['feedback' => $feedback]);

        return $this->statusJsonResponse($res, 'send conversation feedback error:');
    }

    // headers
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'xi-api-key'   => $this->xi_api_key,
        ];
    }

    /**
     * get params
     */
    protected function getParams(array $args): array
    {
        $params = [];
        foreach ($args as $key => $value) {
            if ($value != null && $value != '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
