<?php

namespace App\Domains\Deals;

use App\Domains\Social\SocialDashboard;
use App\Models\Setting;

class MediaKit
{
    /**
     * Compile public-ready media kit dataset for sponsors & agency PR teams.
     *
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $creatorName = Setting::get('creator_name', 'Delia Lazarescu');
        $tagline = Setting::get('creator_tagline', 'Software Engineer & Tech Creator demystifying AI, developer tools, and modern tech careers.');
        $email = Setting::get('business_email', 'delia@techunicorn.com');

        $fData = SocialDashboard::followers();
        $followers = [
            'youtube' => $fData['youtube']['count'] ?? 45000,
            'linkedin' => $fData['linkedin']['count'] ?? 35000,
            'instagram' => $fData['instagram']['count'] ?? 28000,
            'tiktok' => $fData['tiktok']['count'] ?? 52000,
            'x' => $fData['x']['count'] ?? 18000,
            'threads' => $fData['threads']['count'] ?? 12000,
        ];
        $totalAudience = array_sum(array_filter($followers));

        $rates = DealCalculator::calculateRates();

        return [
            'creator' => [
                'name' => $creatorName,
                'handle' => '@Tech.Unicorn',
                'tagline' => $tagline,
                'email' => $email,
                'location' => 'Toronto, Canada / Remote Global',
                'primary_niches' => [
                    'Artificial Intelligence & LLMs',
                    'Developer Tooling & Cloud APIs',
                    'Software Engineering Best Practices',
                    'Tech Career & High-Growth Startups',
                ],
            ],
            'audience' => [
                'total_reach' => $totalAudience,
                'platforms' => [
                    [
                        'platform' => 'youtube',
                        'label' => 'YouTube',
                        'handle' => '@Tech.Unicorn',
                        'url' => 'https://www.youtube.com/@Tech.Unicorn',
                        'followers' => $followers['youtube'],
                        'highlight' => 'Long-form tutorials & in-depth tech deep dives',
                    ],
                    [
                        'platform' => 'linkedin',
                        'label' => 'LinkedIn',
                        'handle' => 'delialazarescu',
                        'url' => 'https://linkedin.com/in/delialazarescu',
                        'followers' => $followers['linkedin'],
                        'highlight' => 'Senior tech decision makers, founders, engineering managers',
                    ],
                    [
                        'platform' => 'tiktok',
                        'label' => 'TikTok',
                        'handle' => '@tech.unicorn',
                        'url' => 'https://www.tiktok.com/@tech.unicorn',
                        'followers' => $followers['tiktok'],
                        'highlight' => 'High-engagement viral tech tips & relatable dev humor',
                    ],
                    [
                        'platform' => 'instagram',
                        'label' => 'Instagram',
                        'handle' => '@tech.unicorn',
                        'url' => 'https://www.instagram.com/tech.unicorn',
                        'followers' => $followers['instagram'],
                        'highlight' => 'Reels, aesthetic workspace setups & community stories',
                    ],
                    [
                        'platform' => 'x',
                        'label' => 'X (Twitter)',
                        'handle' => '@tech__unicorn',
                        'url' => 'https://x.com/tech__unicorn',
                        'followers' => $followers['x'],
                        'highlight' => 'Real-time AI news breakdown & developer threads',
                    ],
                    [
                        'platform' => 'threads',
                        'label' => 'Threads',
                        'handle' => '@tech.unicorn',
                        'url' => 'https://www.threads.net/@tech.unicorn',
                        'followers' => $followers['threads'],
                        'highlight' => 'Conversational tech musings & fast-moving discussion',
                    ],
                ],
                'demographics' => [
                    'professions' => [
                        ['label' => 'Software Engineers & Developers', 'pct' => 54],
                        ['label' => 'Tech Leads, Architects & Founders', 'pct' => 22],
                        ['label' => 'Data & AI Practitioners', 'pct' => 14],
                        ['label' => 'Students & Career Switchers', 'pct' => 10],
                    ],
                    'geography' => [
                        ['country' => 'United States', 'pct' => 45],
                        ['country' => 'United Kingdom & Europe', 'pct' => 26],
                        ['country' => 'Canada', 'pct' => 15],
                        ['country' => 'Australia & Other', 'pct' => 14],
                    ],
                    'age_bracket' => '84% between 21 – 35 years old',
                ],
            ],
            'rates' => $rates['rates'],
            'packages' => $rates['packages'],
            'guidelines' => [
                'All partnerships feature authentic, hands-on testing by Delia.',
                'Clear FTC / ASA disclosure (#ad or #sponsored) on every sponsored integration.',
                'Standard deliverables include 1 round of brand review and draft script signoff.',
                'Payment terms: 50% upon contract signing, 50% within Net 15 of publication date.',
            ],
        ];
    }
}
