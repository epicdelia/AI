<?php

namespace App\Domains\Deals;

use App\Domains\Social\SocialDashboard;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class DealCalculator
{
    /**
     * Compute recommended rate card benchmarks for Delia's channels.
     *
     * @return array<string, mixed>
     */
    public static function calculateRates(): array
    {
        $ytAvg = (int) DB::table('video_stats')->where('age_days', '>=', 7)->avg('views');
        $viewsBenchmark = max(5000, $ytAvg ?: 12500);

        $fData = SocialDashboard::followers();
        $ytSubs = $fData['youtube']['count'] ?? 45000;
        $liFollowers = $fData['linkedin']['count'] ?? 35000;
        $igFollowers = $fData['instagram']['count'] ?? 28000;
        $ttFollowers = $fData['tiktok']['count'] ?? 52000;
        $xFollowers = $fData['x']['count'] ?? 18000;

        // Pricing formula calibrated for Tech / Dev / AI niche (high B2B / SaaS purchasing power)
        $ytIntegration = (int) round(($viewsBenchmark / 1000) * 45, -1); // $45 CPM
        $ytDedicated = (int) round(($viewsBenchmark / 1000) * 95, -1);   // $95 CPM
        $liPost = (int) round(max(800, ($liFollowers / 1000) * 35), -1); // Tech LinkedIn high value
        $igReel = (int) round(max(450, ($igFollowers / 1000) * 25), -1);
        $ttVideo = (int) round(max(400, ($ttFollowers / 1000) * 20), -1);
        $xThread = (int) round(max(350, ($xFollowers / 1000) * 25), -1);

        $bundleItems = [
            'yt_integration' => $ytIntegration,
            'li_post' => $liPost,
            'x_thread' => $xThread,
            'tt_video' => $ttVideo,
        ];
        $bundleTotal = (int) array_sum($bundleItems);
        $bundleDiscounted = (int) round($bundleTotal * 0.85, -1); // 15% discount for 4-platform package

        return [
            'metrics' => [
                'youtube_median_views' => $viewsBenchmark,
                'followers' => [
                    'youtube' => $ytSubs,
                    'linkedin' => $liFollowers,
                    'instagram' => $igFollowers,
                    'tiktok' => $ttFollowers,
                    'x' => $xFollowers,
                ],
                'niche' => 'Tech, AI, Software Engineering & DevTools',
            ],
            'rates' => [
                'youtube_integration_60s' => [
                    'label' => 'YouTube 60s Dedicated Midroll Integration',
                    'rate' => $ytIntegration,
                    'cpm' => 45,
                    'unit' => 'video',
                    'deliverables' => '60s organic mid-roll, first 3 lines of description link, pinned comment',
                ],
                'youtube_dedicated' => [
                    'label' => 'YouTube Fully Dedicated Video',
                    'rate' => $ytDedicated,
                    'cpm' => 95,
                    'unit' => 'video',
                    'deliverables' => 'Entire video focused on the product/topic, title inclusion, description & cards',
                ],
                'linkedin_post' => [
                    'label' => 'LinkedIn Sponsored Post / Carousel',
                    'rate' => $liPost,
                    'unit' => 'post',
                    'deliverables' => 'B2B/tech authority post or PDF carousel, first link in comments or post body',
                ],
                'instagram_reel' => [
                    'label' => 'Instagram Reel / Collab Post',
                    'rate' => $igReel,
                    'unit' => 'reel',
                    'deliverables' => 'Vertical 9:16 video, link in bio / sticker, brand collaboration tag',
                ],
                'tiktok_video' => [
                    'label' => 'TikTok Sponsored Video',
                    'rate' => $ttVideo,
                    'unit' => 'video',
                    'deliverables' => 'Fast-paced tech demo/reaction, caption tag, bio link',
                ],
                'x_thread' => [
                    'label' => 'X (Twitter) Sponsored Thread',
                    'rate' => $xThread,
                    'unit' => 'thread',
                    'deliverables' => '3-5 post educational thread with CTA in final post',
                ],
            ],
            'packages' => [
                'omnichannel_launch' => [
                    'name' => 'Full Omnichannel Tech Launch',
                    'price' => $bundleDiscounted,
                    'regular_price' => $bundleTotal,
                    'savings' => $bundleTotal - $bundleDiscounted,
                    'includes' => [
                        'YouTube 60s Integration',
                        'LinkedIn Authority Post',
                        'TikTok Video',
                        'X Thread',
                    ],
                ],
                'b2b_dev_spotlight' => [
                    'name' => 'B2B Developer Spotlight (LinkedIn + X)',
                    'price' => (int) round(($liPost + $xThread) * 0.9, -1),
                    'regular_price' => $liPost + $xThread,
                    'savings' => ($liPost + $xThread) - (int) round(($liPost + $xThread) * 0.9, -1),
                    'includes' => ['LinkedIn Post', 'X Thread'],
                ],
                'viral_shortform_combo' => [
                    'name' => 'Short-form Viral Combo (TikTok + Reel + YouTube Short)',
                    'price' => (int) round(($ttVideo + $igReel + 450) * 0.85, -1),
                    'regular_price' => $ttVideo + $igReel + 450,
                    'savings' => ($ttVideo + $igReel + 450) - (int) round(($ttVideo + $igReel + 450) * 0.85, -1),
                    'includes' => ['TikTok Video', 'Instagram Reel', 'YouTube Short'],
                ],
            ],
        ];
    }
}
