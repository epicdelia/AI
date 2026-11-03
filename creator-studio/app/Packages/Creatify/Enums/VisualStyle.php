<?php

namespace App\Packages\Creatify\Enums;

use App\Concerns\HasEnumConvert;

enum VisualStyle: string
{
    use HasEnumConvert;

    case AVATAR_BUBBLE_TEMPLATE = 'AvatarBubbleTemplate';
    case DRAMATIC_TEMPLATE = 'DramaticTemplate';
    case DYNAMIC_GREEN_SCREEN_EFFECT = 'DynamicGreenScreenEffect';
    case DYNAMIC_PRODUCT_TEMPLATE = 'DynamicProductTemplate';
    case DYNAMIC_RESPONSE_BUBBLE_TEMPLATE = 'DynamicResponseBubbleTemplate';
    case ENHANCED_VANILLA_TEMPLATE = 'EnhancedVanillaTemplate';
    case FEATURE_HIGHLIGHT_TEMPLATE = 'FeatureHighlightTemplate';
    case FULLSCREEN_TEMPLATE = 'FullScreenTemplate';
    case FULLSCREEN_V2_TEMPLATE = 'FullScreenV2Template';
    case GREENSCREEN_EFFECT_TEMPLATE = 'GreenScreenEffectTemplate';
    case LEGO_TEMPLATE = 'LegoTemplate';
    case MOTIONCARDS_TEMPLATE = 'MotionCardsTemplate';
    case OVERCARDS_TEMPLATE = 'OverCardsTemplate';
    case QUICK_TRANSITION_TEMPLATE = 'QuickTransitionTemplate';
    case SCRIBBLE_TEMPLATE = 'ScribbleTemplate';
    case SIDEBYSIDE_TEMPLATE = 'SideBySideTemplate';
    case SIMPLE_AVATAR_OVERLAY_TEMPLATE = 'SimpleAvatarOverlayTemplate';
    case TOPBOTTOM_TEMPLATE = 'TopBottomTemplate';
    case TWITTERFRAME_TEMPLATE = 'TwitterFrameTemplate';
    case VANILLA_TEMPLATE = 'VanillaTemplate';
    case VLOG_TEMPLATE = 'VlogTemplate';
    case LEGO_VISUAL_EMOTIONAL = 'LegoVisualEmotional';
    case LEGOVISUAL_CUTEPETS = 'LegoVisualCutePets';
    case LEGO_VISUAL_AVATAR_FOCUS_INTRO = 'LegoVisualAvatarFocusIntro';
    case LEGO_SCRIPT_NEGATIVE_HOOK = 'LegoScriptNegativeHook';
    case LEGO_SCRIPT_NUMBER_ONE_HOOK = 'LegoScriptNumberOneHook';
    case LEGO_SCRIPT_EVER_WONDER_HOOK = 'LegoScriptEverWonderHook';
    case LEGO_SCRIPT_SECRET_HOOK = 'LegoScriptSecretHook';
    case LEGO_SCRIPT_WHAT_HAPPENS_HOOK = 'LegoScriptWhatHappensHook';
    case LEGO_SCRIPT_ANYONEELSE_HOOK = 'LegoScriptAnyoneElseHook';
    case LEGO_SCRIPT_AMAZED_HOOK = 'LegoScriptAmazedHook';
    case LEGO_SCRIPT_2025_HOOK = 'LegoScript2025Hook';
    case LOGO_SCRIPT_HATTEME_HOOK = 'LegoScriptHateMeHook';
    case LOGO_SCRIPT_RESPONSEBUBBLE_TEXTOVERLAY = 'LegoScriptResponseBubbleTextOverlay';
    case LEGO_SCRIPT_HEY_ALGORITHMHOOK = 'LegoScriptHeyAlgorithmHook';
    case LEGO_SCRIPT_CANNOT_BELIEVEHOOK = 'LegoScriptCannotBelieveHook';
    case LEGO_SCRIPT_FINALLY_CONFIDENTHOOK = 'LegoScriptFinallyConfidentHook';
    case LEGO_SCRIPT_PREMIUMFELLING_HOOK = 'LegoScriptPremiumFeelingHook';
    case LEGO_SCRIPT_FIRSTTIME_FULLOOK = 'LegoScriptFirstTimeFullHook';
    case LEGO_SCRIPT_LONGLINE_REASONHOOK = 'LegoScriptLongLineReasonHook';
    case LEGO_SCRIPT_MISSINGOUT_ALERTHOOK = 'LegoScriptMissingOutAlertHook';
    case LEGO_SCRIPT_BETTERTODAYVERSION_TOOK = 'LegoScriptBetterTodayVersionHook';
    case LEGO_SCRIPT_BIGHAPPENING_HOOK = 'LegoScriptBigHappeningHook ';
    case LEGO_SCRIPT_PRICE_DROPREGRET_HOOK = 'LegoScriptPriceDropRegretHook';
    case LEGO_SCRIPT_GROUPBUY_FRUSTRATION_HOOK = 'LegoScriptGroupBuyFrustrationHook';
    case LEGO_SCRIPT_STOPEAFFOD_EXCUSE_HOOK = 'LegoScriptStopAffordExcuseHook';
    case LEGO_SCRIPT_LEFETOOSHORT_DISCOUNDHOOK = 'LegoScriptLifeTooShortDiscountHook';
    case LEGO_SCRIPT_HUGEPRICE_DROPRUSH_HOOK = 'LegoScriptHugePriceDropRushHook';
    case LEGO_SCRIPT_PRICECOULD_GOLOWER_HOOK = 'LegoScriptPriceCouldGoLowerHook';
    case LEGO_SCRIPT_STILLFULL_PRICEHOOK = 'LegoScriptStillFullPriceHook';
    case LEGO_SCRIPT_SECRETDISCOUNT_SHAREHOOK = 'LegoScriptSecretDiscountShareHook';
    case LEGO_SCRIPT_VOUCHERSAVINGS_ALERTHOOK = 'LegoScriptVoucherSavingsAlertHook';
    case LEGO_SCRIPT_COUPONDISCOVERY_REGRETHOOK = 'LegoScriptCouponDiscoveryRegretHook';
    case LEGO_SCRIPT_BESTDEAL_EVERHOOK = 'LegoScriptBestDealEverHook';

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::AVATAR_BUBBLE_TEMPLATE                 => 'Avatar Bubble (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::DRAMATIC_TEMPLATE                      => 'Dramatic (Applicable to aspect_ratio 9x16)',
            self::DYNAMIC_GREEN_SCREEN_EFFECT            => 'Dynamic Green Screen Effect (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::DYNAMIC_PRODUCT_TEMPLATE               => 'Product (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::DYNAMIC_RESPONSE_BUBBLE_TEMPLATE       => 'Response Bubble (Applicable to aspect_ratio 9x16)',
            self::ENHANCED_VANILLA_TEMPLATE              => 'Dynamic Vanilla (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::FEATURE_HIGHLIGHT_TEMPLATE             => 'Feature Highlight (Applicable to aspect_ratio 9x16, 1x1)',
            self::FULLSCREEN_TEMPLATE                    => 'Full Screen (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::FULLSCREEN_V2_TEMPLATE                 => 'Full Screen V2 (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::GREENSCREEN_EFFECT_TEMPLATE            => 'Green Screen Effect (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::LEGO_TEMPLATE                          => 'Lego (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::MOTIONCARDS_TEMPLATE                   => 'Motion Cards (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::OVERCARDS_TEMPLATE                     => 'Over Cards (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::QUICK_TRANSITION_TEMPLATE              => 'Quick Transition (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::SCRIBBLE_TEMPLATE                      => 'Scribble (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::SIDEBYSIDE_TEMPLATE                    => 'Side by Side (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::SIMPLE_AVATAR_OVERLAY_TEMPLATE         => 'Product Presenter (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::TOPBOTTOM_TEMPLATE                     => 'Top & Bottom (Applicable to aspect_ratio 9x16, 1x1)',
            self::TWITTERFRAME_TEMPLATE                  => 'Twitter Frame (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::VANILLA_TEMPLATE                       => 'Vanilla (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::VLOG_TEMPLATE                          => 'Vlog (Applicable to aspect_ratio 9x16)',
            self::LEGO_VISUAL_EMOTIONAL                  => 'Emotional (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::LEGOVISUAL_CUTEPETS                    => 'Cute Pets (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::LEGO_VISUAL_AVATAR_FOCUS_INTRO         => 'Avatar Focus Intro (Applicable to aspect_ratio 9x16, 16x9, 1x1)',
            self::LEGO_SCRIPT_NEGATIVE_HOOK              => "Don't Use This If You Want... (Applicable to aspect_ratio 9x16, 1x1, 16x9",
            self::LEGO_SCRIPT_NUMBER_ONE_HOOK            => 'The #1 Hack You Need (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_EVER_WONDER_HOOK           => 'Ever Wonder Why? (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_SECRET_HOOK                => 'Wish I Could Keep This Secret (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_WHAT_HAPPENS_HOOK          => 'What Happens If You Try This? (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_ANYONEELSE_HOOK            => 'That One Thing We All Have (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_AMAZED_HOOK                => 'Still Amazed by This (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_2025_HOOK                  => 'Hack 2025 Like This (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LOGO_SCRIPT_HATTEME_HOOK               => "They'll Hate Me for This (Applicable to aspect_ratio 9x16, 1x1, 16x9)",
            self::LOGO_SCRIPT_RESPONSEBUBBLE_TEXTOVERLAY => 'Response bubble (Applicable to aspect_ratio 9x16, 1x1)',
            self::LEGO_SCRIPT_HEY_ALGORITHMHOOK          => 'Hey Algorithm (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_CANNOT_BELIEVEHOOK         => 'Cannot Believe This (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_FINALLY_CONFIDENTHOOK      => 'Finally Confident (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_PREMIUMFELLING_HOOK        => 'Premium Feeling (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_FIRSTTIME_FULLOOK          => 'First Time Full (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_LONGLINE_REASONHOOK        => 'Long Line Reason (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_MISSINGOUT_ALERTHOOK       => 'Missing Out Alert (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_BETTERTODAYVERSION_TOOK    => 'Better Today Version (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_BIGHAPPENING_HOOK          => 'Big Happening (Applicable to aspect_ratio 9x16, 1x1, 16x9) ',
            self::LEGO_SCRIPT_PRICE_DROPREGRET_HOOK      => 'Price Drop Regret (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_GROUPBUY_FRUSTRATION_HOOK  => 'Group Buy Frustration (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_STOPEAFFOD_EXCUSE_HOOK     => 'Stop Afford Excuse (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_LEFETOOSHORT_DISCOUNDHOOK  => 'Life Too Short Discount (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_HUGEPRICE_DROPRUSH_HOOK    => 'Huge Price Drop Rush (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_PRICECOULD_GOLOWER_HOOK    => 'Price Could Go Lower (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_STILLFULL_PRICEHOOK        => 'Still Full Price (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_SECRETDISCOUNT_SHAREHOOK   => 'Secret Discount Share (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_VOUCHERSAVINGS_ALERTHOOK   => 'Voucher Savings Alert (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_COUPONDISCOVERY_REGRETHOOK => ' Coupon Discovery Regret (Applicable to aspect_ratio 9x16, 1x1, 16x9)',
            self::LEGO_SCRIPT_BESTDEAL_EVERHOOK          => 'Best Deal Ever (Applicable to aspect_ratio 9x16, 1x1, 16x9)'
        };
    }
}
