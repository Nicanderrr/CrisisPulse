<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class CrisisAnalysisService
{
    /**
     * @return array{
     *     sentiment: string,
     *     sentiment_score: float,
     *     crisis_level: string,
     *     crisis_keywords: array<int, string>,
     *     recommended_response: string,
     *     summary: string,
     *     detailed_analysis: string
     * }
     */
    public function analyze(?string $content, array $attachments = [], ?string $sourceUrl = null): array
    {
        $sourceContext = $this->fetchSourceUrlContext($sourceUrl);
        $apiKey = config('services.openai.key');

        if (blank($apiKey)) {
            return $this->analyzeLocally($content ?? '', $attachments, 'OpenAI API key is not configured.', $sourceContext);
        }

        try {
            return $this->analyzeWithOpenAi($content ?? '', $apiKey, $attachments, $sourceContext);
        } catch (ConnectionException|RuntimeException $exception) {
            return $this->analyzeLocally($content ?? '', $attachments, $exception->getMessage(), $sourceContext);
        }
    }

    /**
     * @return array{
     *     sentiment: string,
     *     sentiment_score: float,
     *     crisis_level: string,
     *     crisis_keywords: array<int, string>,
     *     recommended_response: string,
     *     summary: string,
     *     detailed_analysis: string
     * }
     */
    private function analyzeWithOpenAi(string $content, string $apiKey, array $attachments = [], ?string $sourceContext = null): array
    {
        $visualInputs = $this->buildVisualInputs($attachments);
        $audioTranscripts = $this->transcribeVideoAudio($attachments, $apiKey);
        $userContent = [
            [
                'type' => 'input_text',
                'text' => trim($content) !== ''
                    ? "Message text:\n".$content
                    : 'No message text was provided. Analyze the uploaded visual evidence and any extracted video frames.',
            ],
        ];

        if (filled($sourceContext)) {
            $userContent[] = [
                'type' => 'input_text',
                'text' => "Source link context:\n".$sourceContext,
            ];
        }

        foreach ($visualInputs as $visualInput) {
            $userContent[] = [
                'type' => 'input_text',
                'text' => $visualInput['label'],
            ];
            $userContent[] = [
                'type' => 'input_image',
                'detail' => 'auto',
                'image_url' => $visualInput['data_url'],
            ];
        }

        foreach ($audioTranscripts as $audioTranscript) {
            $userContent[] = [
                'type' => 'input_text',
                'text' => $audioTranscript,
            ];
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => 'Classify hospital public-feedback, source-link context, and uploaded media evidence for crisis communication monitoring. Inspect all provided image inputs. Some image inputs may be extracted video frames. Also consider any transcribed audio from uploaded videos and any fetched source-link text. Return only the requested JSON fields. Sentiment must be positive, neutral, or negative. Crisis level must be low, medium, or high. The detailed analysis should clearly explain what was read from text/link context, what was seen, what was heard or transcribed, how the evidence influenced the conclusion, communication risk, uncertainty, and recommended next steps for hospital communication officers.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'crisis_message_analysis',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'sentiment' => [
                                    'type' => 'string',
                                    'enum' => ['positive', 'neutral', 'negative'],
                                ],
                                'sentiment_score' => [
                                    'type' => 'number',
                                    'minimum' => -1,
                                    'maximum' => 1,
                                ],
                                'crisis_level' => [
                                    'type' => 'string',
                                    'enum' => ['low', 'medium', 'high'],
                                ],
                                'crisis_keywords' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'recommended_response' => [
                                    'type' => 'string',
                                ],
                                'summary' => [
                                    'type' => 'string',
                                ],
                                'detailed_analysis' => [
                                    'type' => 'string',
                                ],
                            ],
                            'required' => [
                                'sentiment',
                                'sentiment_score',
                                'crisis_level',
                                'crisis_keywords',
                                'recommended_response',
                                'summary',
                                'detailed_analysis',
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: 'OpenAI analysis failed.';

            throw new RuntimeException($this->safeOpenAiError($message));
        }

        $payload = json_decode((string) Arr::get($response->json(), 'output.0.content.0.text'), true);

        if (! is_array($payload)) {
            throw new RuntimeException('OpenAI returned an invalid analysis payload.');
        }

        return $this->normalizeAnalysis($payload);
    }

    /**
     * @return array{
     *     sentiment: string,
     *     sentiment_score: float,
     *     crisis_level: string,
     *     crisis_keywords: array<int, string>,
     *     recommended_response: string,
     *     summary: string,
     *     detailed_analysis: string
     * }
     */
    private function analyzeLocally(string $content, array $attachments = [], ?string $fallbackReason = null, ?string $sourceContext = null): array
    {
        $combinedContent = trim($content."\n\n".($sourceContext ?? ''));
        $text = Str::of($combinedContent)->lower()->toString();
        $negativeWords = ['death', 'delay', 'negligence', 'shortage', 'strike', 'emergency', 'complaint', 'poor', 'angry', 'unsafe', 'infection', 'ignored', 'waiting'];
        $positiveWords = ['good', 'excellent', 'helpful', 'fast', 'clean', 'professional', 'kind', 'improved', 'satisfied', 'thank'];
        $crisisWords = ['death', 'negligence', 'shortage', 'strike', 'emergency', 'unsafe', 'infection', 'no doctor', 'blood shortage', 'poor service'];

        $negativeCount = $this->countMatches($text, $negativeWords);
        $positiveCount = $this->countMatches($text, $positiveWords);
        $keywords = $this->matchedWords($text, $crisisWords);

        $score = max(-1, min(1, ($positiveCount - $negativeCount) / max(1, $positiveCount + $negativeCount)));
        $sentiment = match (true) {
            $score > 0.2 => 'positive',
            $score < -0.2 => 'negative',
            default => 'neutral',
        };
        $crisisLevel = match (true) {
            count($keywords) >= 3 || Str::contains($text, ['death', 'negligence', 'unsafe']) => 'high',
            count($keywords) >= 1 || $negativeCount >= 2 => 'medium',
            default => 'low',
        };

        return [
            'sentiment' => $sentiment,
            'sentiment_score' => round($score, 2),
            'crisis_level' => $crisisLevel,
            'crisis_keywords' => $keywords,
            'recommended_response' => $this->localRecommendation($crisisLevel),
            'summary' => trim($combinedContent) !== '' ? Str::limit($combinedContent, 140) : 'Visual evidence submitted for review.',
            'detailed_analysis' => $this->localDetailedAnalysis($content, $sentiment, $score, $crisisLevel, $keywords, $positiveCount, $negativeCount, $attachments, $fallbackReason, $sourceContext),
        ];
    }

    /**
     * @param  array<int, string>  $words
     */
    private function countMatches(string $text, array $words): int
    {
        return count($this->matchedWords($text, $words));
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, string>
     */
    private function matchedWords(string $text, array $words): array
    {
        return array_values(array_filter($words, fn (string $word): bool => Str::contains($text, $word)));
    }

    private function localRecommendation(string $crisisLevel): string
    {
        return match ($crisisLevel) {
            'high' => 'Escalate immediately to hospital communications leadership and prepare a verified public response.',
            'medium' => 'Review the message, verify the issue with the relevant department, and respond with an update.',
            default => 'Monitor for repeated mentions and keep the message in the sentiment trend report.',
        };
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function localDetailedAnalysis(string $content, string $sentiment, float $score, string $crisisLevel, array $keywords, int $positiveCount, int $negativeCount, array $attachments, ?string $fallbackReason, ?string $sourceContext): string
    {
        $keywordText = count($keywords) > 0 ? implode(', ', $keywords) : 'no major crisis keywords';
        $imageCount = count(array_filter($attachments, fn (array $attachment): bool => ($attachment['type'] ?? '') === 'image'));
        $videoCount = count(array_filter($attachments, fn (array $attachment): bool => ($attachment['type'] ?? '') === 'video'));
        $visualNote = ($imageCount + $videoCount) > 0
            ? "The record also includes {$imageCount} image attachment(s) and {$videoCount} video attachment(s). OpenAI visual analysis was unavailable during this request, so the local fallback did not inspect the visual content itself."
            : 'No visual attachment was provided.';
        $sourceNote = filled($sourceContext)
            ? 'The source link was fetched and included as text context for analysis.'
            : 'No readable source-link context was available.';

        $paragraphs = [
            "The system reviewed the submitted message and identified an overall {$sentiment} sentiment with a score of ".round($score, 2).". This score was produced from the balance of positive and negative terms found in the text. The message contained {$negativeCount} negative signal(s) and {$positiveCount} positive signal(s), which influenced the final sentiment classification.",
            "For crisis communication risk, the system classified the record as {$crisisLevel}. It detected {$keywordText}. These indicators are important because public complaints involving emergencies, delays, negligence, shortages, safety, or poor service can quickly affect public trust in the hospital if they are repeated or left unanswered.",
            $sourceNote,
            $visualNote,
            'Recommended action: '.$this->localRecommendation($crisisLevel).' The communication team should verify the claim internally before issuing a response, record the department involved where possible, and monitor whether similar messages appear from other sources.',
            trim($content) !== '' ? 'Original message reviewed: "'.Str::limit($content, 500).'"' : 'No message text was provided, so the local fallback conclusion is limited.',
        ];

        if ($fallbackReason !== null) {
            $paragraphs[] = 'OpenAI visual/audio analysis was not completed because: '.$fallbackReason;
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * @param  array<int, array{path?: string, original_name?: string, mime?: string, type?: string}>  $attachments
     * @return array<int, array{label: string, data_url: string}>
     */
    private function buildVisualInputs(array $attachments): array
    {
        $inputs = [];

        foreach ($attachments as $attachment) {
            $path = $attachment['path'] ?? null;
            $type = $attachment['type'] ?? null;

            if (! is_string($path)) {
                continue;
            }

            $absolutePath = storage_path('app/public/'.$path);

            if (! File::exists($absolutePath)) {
                continue;
            }

            if ($type === 'image') {
                $inputs[] = [
                    'label' => 'Uploaded image evidence: '.($attachment['original_name'] ?? basename($path)),
                    'data_url' => $this->imageDataUrl($absolutePath, $attachment['mime'] ?? null),
                ];
            }

            if ($type === 'video') {
                $framePaths = $this->extractVideoFrames($absolutePath);

                foreach ($framePaths as $index => $framePath) {
                    $inputs[] = [
                        'label' => 'Extracted frame '.($index + 1).' from uploaded video: '.($attachment['original_name'] ?? basename($path)),
                        'data_url' => $this->imageDataUrl($framePath, 'image/jpeg'),
                    ];
                }

                if (count($framePaths) > 0) {
                    File::deleteDirectory(dirname($framePaths[0]));
                }
            }
        }

        return array_slice($inputs, 0, 8);
    }

    /**
     * @param  array<int, array{path?: string, original_name?: string, type?: string}>  $attachments
     * @return array<int, string>
     */
    private function transcribeVideoAudio(array $attachments, string $apiKey): array
    {
        $transcripts = [];

        foreach ($attachments as $attachment) {
            if (($attachment['type'] ?? null) !== 'video' || ! is_string($attachment['path'] ?? null)) {
                continue;
            }

            $videoPath = storage_path('app/public/'.$attachment['path']);

            if (! File::exists($videoPath)) {
                continue;
            }

            $audioPath = $this->extractVideoAudio($videoPath);

            if ($audioPath === null) {
                $transcripts[] = 'Audio transcript unavailable for uploaded video: '.($attachment['original_name'] ?? basename($videoPath)).'. No readable audio track could be extracted.';

                continue;
            }

            $transcript = $this->transcribeAudioFile($audioPath, $apiKey);
            File::delete($audioPath);

            if ($transcript === null || trim($transcript) === '') {
                $transcripts[] = 'Audio transcript unavailable for uploaded video: '.($attachment['original_name'] ?? basename($videoPath)).'. The extracted audio could not be transcribed.';

                continue;
            }

            $transcripts[] = 'Audio transcript from uploaded video '.($attachment['original_name'] ?? basename($videoPath)).":\n".$transcript;
        }

        return $transcripts;
    }

    private function extractVideoAudio(string $videoPath): ?string
    {
        $audioDirectory = storage_path('app/ai-video-audio');
        File::ensureDirectoryExists($audioDirectory);

        $audioPath = $audioDirectory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.mp3';
        $result = Process::timeout(30)->run([
            'ffmpeg',
            '-y',
            '-i',
            $videoPath,
            '-vn',
            '-ac',
            '1',
            '-ar',
            '16000',
            '-t',
            '180',
            $audioPath,
        ]);

        if ($result->failed() || ! File::exists($audioPath) || File::size($audioPath) === 0) {
            File::delete($audioPath);

            return null;
        }

        return $audioPath;
    }

    private function transcribeAudioFile(string $audioPath, string $apiKey): ?string
    {
        $response = Http::withToken($apiKey)
            ->attach('file', (string) file_get_contents($audioPath), basename($audioPath))
            ->timeout(60)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => config('services.openai.transcription_model'),
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('text');
    }

    private function imageDataUrl(string $path, ?string $mime): string
    {
        $mimeType = $mime ?: 'image/jpeg';

        return 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * @return array<int, string>
     */
    private function extractVideoFrames(string $videoPath): array
    {
        $frameDirectory = storage_path('app/ai-video-frames/'.Str::uuid()->toString());
        File::ensureDirectoryExists($frameDirectory);

        $outputPattern = $frameDirectory.DIRECTORY_SEPARATOR.'frame-%02d.jpg';
        $result = Process::timeout(20)->run([
            'ffmpeg',
            '-y',
            '-i',
            $videoPath,
            '-vf',
            'fps=1/3,scale=768:-1',
            '-frames:v',
            '3',
            $outputPattern,
        ]);

        if ($result->failed()) {
            return [];
        }

        return collect(File::files($frameDirectory))
            ->map(fn ($file): string => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     sentiment: string,
     *     sentiment_score: float,
     *     crisis_level: string,
     *     crisis_keywords: array<int, string>,
     *     recommended_response: string,
     *     summary: string,
     *     detailed_analysis: string
     * }
     */
    private function normalizeAnalysis(array $payload): array
    {
        return [
            'sentiment' => in_array($payload['sentiment'] ?? '', ['positive', 'neutral', 'negative'], true) ? $payload['sentiment'] : 'neutral',
            'sentiment_score' => (float) max(-1, min(1, $payload['sentiment_score'] ?? 0)),
            'crisis_level' => in_array($payload['crisis_level'] ?? '', ['low', 'medium', 'high'], true) ? $payload['crisis_level'] : 'low',
            'crisis_keywords' => array_values(array_filter(Arr::wrap($payload['crisis_keywords'] ?? []), 'is_string')),
            'recommended_response' => (string) ($payload['recommended_response'] ?? ''),
            'summary' => (string) ($payload['summary'] ?? ''),
            'detailed_analysis' => (string) ($payload['detailed_analysis'] ?? ''),
        ];
    }

    private function fetchSourceUrlContext(?string $sourceUrl): ?string
    {
        if (blank($sourceUrl)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->withHeaders([
                    'User-Agent' => 'CrisisPulseAI/1.0',
                ])
                ->get($sourceUrl);
        } catch (ConnectionException) {
            return 'The source link could not be reached: '.$sourceUrl;
        }

        if (! $response->successful()) {
            return 'The source link returned HTTP '.$response->status().': '.$sourceUrl;
        }

        $body = (string) $response->body();
        $text = html_entity_decode(strip_tags($body));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        if (blank($text)) {
            return 'The source link was reachable but no readable text could be extracted: '.$sourceUrl;
        }

        return 'URL: '.$sourceUrl."\n".Str::limit(trim($text), 6000);
    }

    private function safeOpenAiError(string $message): string
    {
        return preg_replace('/sk-[A-Za-z0-9_\\-]+/', 'sk-***', $message) ?? 'OpenAI analysis failed.';
    }
}
