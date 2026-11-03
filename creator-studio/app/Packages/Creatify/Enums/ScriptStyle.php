<?php

namespace App\Packages\Creatify\Enums;

use App\Concerns\HasEnumConvert;

enum ScriptStyle: string
{
    use HasEnumConvert;

    case BENEFITS_V2 = 'benefits';
    case BRAND_STORY_V2 = 'brand_story';
    case CALL_TO_ACTION_V2 = 'call_to_action';
    case DISCOVERY_WRITER = 'discovery';
    case DONT_WORRY_WITTER = 'dont_worry';
    case EMOTIONAL_WRITER = 'emotional';
    case GENZ_WRITER = 'gen_z';
    case HOW_TO_V2 = 'how_to';
    case LET_ME_SHOW_YOU_WRITER = 'let_me_show_you';
    case MOTIVATIONAL_WRITER = 'motivational';
    case PROBLEM_SOLUTION_V2 = 'problem_solution';
    case PROBLEM_SOLUTION_WRITER = 'problem_solution_v2';
    case PRODUCT_HIGHLIGHTS_V2 = 'product_highlights';
    case PRODUCT_LIFESTYTLE_V2 = 'product_lifestyle';
    case RESPONSE_BUBBLE_WRITER = 'response_bubble';
    case SPECIAL_OFFER_V2 = 'special_offers';
    case STORY_TIME_WRITER = 'storytime';
    case THREE_REASONS_WRITER = 'three_reasons_writer';
    case TRENDING_TOPICS_V2 = 'trending_topics';
    case DIY = 'diy';

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::BENEFITS_V2             => 'Benefits',
            self::BRAND_STORY_V2          => 'Brand Story',
            self::CALL_TO_ACTION_V2       => 'Call To Action',
            self::DISCOVERY_WRITER        => 'Discovery',
            self::DONT_WORRY_WITTER       => "Don't worry",
            self::EMOTIONAL_WRITER        => 'Emotional',
            self::GENZ_WRITER             => 'Gen Z',
            self::HOW_TO_V2               => 'How To',
            self::LET_ME_SHOW_YOU_WRITER  => 'Let me Show You',
            self::MOTIVATIONAL_WRITER     => 'Motivational',
            self::PROBLEM_SOLUTION_V2     => 'Problem Solution',
            self::PROBLEM_SOLUTION_WRITER => 'Problem-Solution',
            self::PRODUCT_HIGHLIGHTS_V2   => 'Product Highlights',
            self::PRODUCT_LIFESTYTLE_V2   => 'Product Lifestyle',
            self::RESPONSE_BUBBLE_WRITER  => 'Response Bubble',
            self::SPECIAL_OFFER_V2        => 'Special Offers',
            self::STORY_TIME_WRITER       => 'Storytime',
            self::THREE_REASONS_WRITER    => '3 Reasons Why',
            self::TRENDING_TOPICS_V2      => 'Trending Topics',
            self::DIY                     => 'DIY',
        };
    }
}
