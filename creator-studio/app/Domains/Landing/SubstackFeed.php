<?php

namespace App\Domains\Landing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Live, automated Substack feed for Delia's "Tech Unicorns" newsletter (https://techunicorn.substack.com).
 * Automatically pulls her latest real articles from the public Substack RSS feed,
 * caches them for 30 minutes, and keeps the landing page synchronized with her latest writings.
 */
class SubstackFeed
{
    public const CACHE_KEY = 'substack_feed_v1';
    public const RSS_URL = 'https://techunicorn.substack.com/feed';
    public const PROFILE_URL = 'https://substack.com/@techunicorn';
    public const PUBLICATION_URL = 'https://techunicorn.substack.com';

    public static function get(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(30), function () {
            $live = self::fetchRss();
            return [
                'publication' => [
                    'name' => 'Tech Unicorns',
                    'author' => 'Delia Lazarescu',
                    'handle' => '@techunicorn',
                    'profile_url' => self::PROFILE_URL,
                    'url' => self::PUBLICATION_URL,
                    'subscribers' => '24K+',
                    'tagline' => 'Coding, AI experiments, digital nomadism, and building software around the world.',
                    'source' => !empty($live) ? 'live' : 'curated',
                ],
                'posts' => !empty($live) ? $live : self::fallbackPosts(),
            ];
        });
    }

    public static function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return self::get();
    }

    public static function fetchRss(): array
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
                    'timeout' => 8,
                ],
            ]);

            $xmlStr = @file_get_contents(self::RSS_URL, false, $ctx);
            if (!$xmlStr) {
                return [];
            }

            $xml = @simplexml_load_string($xmlStr);
            if (!$xml || !isset($xml->channel->item)) {
                return [];
            }

            $posts = [];
            $id = 1;
            foreach ($xml->channel->item as $item) {
                $title = trim((string)($item->title ?? ''));
                $url = trim((string)($item->link ?? ''));
                $pubDateRaw = (string)($item->pubDate ?? '');
                $descRaw = (string)($item->description ?? '');

                if (!$title || !$url) {
                    continue;
                }

                // Get full body from content:encoded or description
                $contentEncoded = (string)($item->children('content', true)->encoded ?? '');
                $rawSource = !empty($contentEncoded) ? $contentEncoded : $descRaw;

                // Clean description (strip HTML tags, clean whitespace, decode entities)
                $cleanDesc = html_entity_decode(strip_tags($rawSource), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $cleanDesc = trim((string)preg_replace('/\s+/', ' ', $cleanDesc));
                // Remove greeting boilerplate like "Hey tech unicorns!..."
                $cleanDesc = (string)preg_replace('/^(Hey\s+tech\s+(unicorns|friends)[\s\S]*?[!.]\s*)/i', '', $cleanDesc);
                $excerpt = Str::limit(trim($cleanDesc), 175);

                // Extract image from enclosure or content
                $cover = null;
                if (isset($item->enclosure) && isset($item->enclosure['url'])) {
                    $cover = (string)$item->enclosure['url'];
                }

                if (!$cover && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $descRaw, $m)) {
                    $cover = $m[1];
                }

                if (!$cover) {
                    $cover = '/images/delia_hyperagent.jpg';
                }

                // Format publication date (e.g. "Oct 2026", "Jun 2025")
                $timestamp = strtotime($pubDateRaw);
                $formattedDate = $timestamp ? date('M Y', $timestamp) : 'Recent';

                // Calculate approximate reading time based on word count
                $wordCount = str_word_count($cleanDesc);
                $readTime = max(4, min(12, ceil($wordCount / 180))) . ' min read';

                // Categorize based on keywords in title
                $category = 'Engineering';
                $tLower = strtolower($title);
                if (str_contains($tLower, 'ai') || str_contains($tLower, 'clawdbot') || str_contains($tLower, 'bot') || str_contains($tLower, 'agent')) {
                    $category = 'AI & Agents';
                } elseif (str_contains($tLower, 'nomad') || str_contains($tLower, 'travel') || str_contains($tLower, 'davos')) {
                    $category = 'Nomad Life';
                } elseif (str_contains($tLower, 'google') || str_contains($tLower, 'degree') || str_contains($tLower, 'cs degree') || str_contains($tLower, 'career') || str_contains($tLower, 'airbnb')) {
                    $category = 'Career & Tech';
                } elseif (str_contains($tLower, 'hackathon') || str_contains($tLower, 'build')) {
                    $category = 'Hackathons';
                }

                $posts[] = [
                    'id' => $id++,
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'url' => $url,
                    'category' => $category,
                    'read_time' => $readTime,
                    'date' => $formattedDate,
                    'cover' => $cover,
                    'popular' => $id <= 3,
                ];

                if (count($posts) >= 8) {
                    break;
                }
            }

            return $posts;
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    public static function fallbackPosts(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'My new favourite AI has my credit card. Here are the rules I make it follow.',
                'excerpt' => 'Giving an autonomous agent spend authority sounds terrifying until you build deterministic verification guardrails. Here is the exact sandbox architecture.',
                'url' => 'https://techunicorn.substack.com/p/my-new-favourite-ai-has-my-credit',
                'category' => 'AI & Agents',
                'read_time' => '6 min read',
                'date' => 'Oct 2026',
                'cover' => 'https://substackcdn.com/image/youtube/w_728,c_limit/ik8cyqLfLDE',
                'popular' => true,
            ],
            [
                'id' => 2,
                'title' => 'How to set up a 24/7 AI employee (ClawdBot managed my business while I partied in Davos🤖)',
                'excerpt' => 'A practical, unvarnished walkthrough of building background autonomous workflows that triage client leads, schedule tasks, and keep the business running autonomously.',
                'url' => 'https://techunicorn.substack.com/p/how-to-set-up-a-247-ai-employee-my',
                'category' => 'AI & Agents',
                'read_time' => '8 min read',
                'date' => 'Jan 2026',
                'cover' => 'https://substackcdn.com/image/fetch/$s_!c5-b!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2Fdcd9a088-2c27-4744-adf8-4164177f0e97_1200x1600.jpeg',
                'popular' => true,
            ],
            [
                'id' => 3,
                'title' => 'Is Your CS Degree About to Become Worthless?...',
                'excerpt' => 'The software industry shifted under our feet. Syntax memorization is dead; architecture, prompt harness design, and system debugging are the real defensible moats.',
                'url' => 'https://techunicorn.substack.com/p/is-your-cs-degree-about-to-become',
                'category' => 'Career & Tech',
                'read_time' => '7 min read',
                'date' => 'Jul 2025',
                'cover' => 'https://substackcdn.com/image/fetch/$s_!UT14!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2F3d19a0cb-a5df-467b-b5fc-246ae2ff2043_1280x720.png',
                'popular' => true,
            ],
            [
                'id' => 4,
                'title' => '$40/Hour to $400K at Google WITHOUT a Degree - The Roadmap Nobody Talks About 🚀',
                'excerpt' => 'Here is how she went from $40/hour intern to $68K to $130K to $230K to $400K+ as a principal analytic engineer without a computer science degree.',
                'url' => 'https://techunicorn.substack.com/p/40hour-to-400k-at-google-without',
                'category' => 'Career & Tech',
                'read_time' => '10 min read',
                'date' => 'Jun 2025',
                'cover' => 'https://substackcdn.com/image/fetch/$s_!MkHW!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2F40238c89-04be-4b28-b822-be1a7d7cfcab_2240x1260.png',
                'popular' => false,
            ],
            [
                'id' => 5,
                'title' => 'From $150K at Google to $400K at Airbnb (Real Interview Secrets Inside!) 💰',
                'excerpt' => 'Behind closed doors: real interview transcripts, architecture design rounds, and negotiation tactics for top-of-market engineering compensation.',
                'url' => 'https://techunicorn.substack.com/p/from-150k-at-google-to-400k-at-airbnb',
                'category' => 'Career & Tech',
                'read_time' => '9 min read',
                'date' => 'May 2025',
                'cover' => 'https://substackcdn.com/image/fetch/$s_!fVox!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2F399d5dcb-9bbf-48f8-9abb-ac981a42d411_1280x720.png',
                'popular' => false,
            ],
            [
                'id' => 6,
                'title' => '21 Hackathon Wins at 21 Years Old? 🤯🔮',
                'excerpt' => 'There is a secret formula to winning hackathons and turning 48-hour prototypes into venture-backed companies and viral products.',
                'url' => 'https://techunicorn.substack.com/p/21-hackathon-wins-at-21-years-old',
                'category' => 'Hackathons',
                'read_time' => '6 min read',
                'date' => 'May 2025',
                'cover' => 'https://substackcdn.com/image/fetch/$s_!v0NQ!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2Ff035f3e3-4e6e-4613-9c27-9d125f288b27_2048x1365.jpeg',
                'popular' => false,
            ],
        ];
    }
}
