<?php
/**
 * ai_isms.php - the ONE list of phrases / habits that make the owner's writing sound written by a bot, and the
 * checks that use it. Shared by every app that writes in his voice:
 *   - Reply Radar (me/twitterhelper/api/voice.php + analyze.php): replies and comments on X / LinkedIn
 *   - Assistant posts (assistant/taskapp/postsLib.php): LinkedIn post drafts
 * (Moved here from me/twitterhelper/api/voice.php on 2026-10-18; the names keep their rr prefix so callers did
 * not change.)
 *
 * RR_AI_ISMS: the first 25 are the owner's own list (2026-09-27); the rest were added from the same family. Each
 * entry: [what the AI is told, regex that finds it]. Callers tell the AI never to use them (rrAiIsmRules), clean
 * what can be cleaned mechanically (rrCleanDraft: dashes, emoji, hashtags), check every draft (rrAiIsmsIn), and
 * have the AI rewrite any draft that still has one (once).
 */

const RR_AI_ISMS = [
    // the owner's list
    ['"fast-paced world"', '/fast[- ]paced world/i'],
    ['"uncomfortable truth"', '/uncomfortable truth/i'],
    ['"nobody talks about this" / "no one is talking about"', '/(nobody|no one)(\'s| is)? talk(s|ing) about/i'],
    ['em dashes or en dashes (use a comma, a period or plain words)', '/[\x{2014}\x{2013}]/u'],
    ['"whether you\'re a CEO or..."', '/whether you\'?re an? /i'],
    ['"what would you add?"', '/what would you add/i'],
    ['"that\'s the unlock" / "unlock"', '/\bunlock(s|ed|ing)?\b/i'],
    ['"a framework"', '/\bframework\b/i'],
    ['"we\'ve all been there"', '/we\'?ve all been there/i'],
    ['three-word rhythm lists like "Faster. Smarter. Better."', '/\b\w+(er|ly)?\.\s+\w+(er|ly)?\.\s+\w+(er|ly)?\./i'],
    ['"you\'ve got this"', '/you\'?ve got this/i'],
    ['"let\'s be honest" / "honestly" / "to be honest"', '/\b(let\'?s be honest|to be honest|honestly)\b/i'],
    ['"deeply human"', '/deeply human/i'],
    ['"game-changer"', '/game[- ]?chang(er|ing)/i'],
    ['emoji (on bullets or anywhere)', '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u'],
    ['"here\'s the interesting part" / "here\'s the thing" / "here\'s why"', '/here\'?s (the (interesting|real|thing|kicker|catch)|why|what|how)\b/i'],
    ['"The result?" (and other one-word question setups: "The catch?", "The kicker?", "The best part?")', '/\bthe (result|catch|kicker|best part|twist|answer|problem|secret)\?/i'],
    ['"let that sink in"', '/let that sink in/i'],
    ['"no fluff"', '/no fluff/i'],
    ['"and that\'s okay"', '/and that\'?s (ok|okay|fine)/i'],
    ['"It\'s not X. It\'s Y." / "not just X, but Y"', '/\b(it\'?s|this is|that\'?s) not (just |about )?[^.?!]{1,60}[.,;]\s*(it\'?s|this is|that\'?s)\b|\bnot just\b[^.?!]{1,60}\bbut\b/i'],
    ['"ecosystem"', '/\becosystems?\b/i'],
    ['"it\'s worth noting"', '/(it\'?s )?worth noting/i'],
    ['"small shift, big win" style slogans', '/small (shift|change|tweak)s?[,.]? big (win|impact|result)/i'],
    ['"great question" / "great post" / "love this" / "thanks for sharing"', '/\b(great (question|post|point|share)|love this|thanks for sharing|well said|spot on|so true|couldn\'?t agree more)\b/i'],

    // more of the same family
    ['"delve" / "dive into" / "deep dive"', '/\b(delv(e|es|ing)|dive into|deep[- ]dive)\b/i'],
    ['"landscape" / "realm" / "tapestry"', '/\b(landscape|realm|tapestry)\b/i'],
    ['"navigate" (the complexities...)', '/\bnavigat(e|es|ing)\b/i'],
    ['"leverage" / "harness" / "empower" / "elevate" / "foster"', '/\b(leverag(e|es|ing)|harness(es|ing)?|empower(s|ing)?|elevat(e|es|ing)|foster(s|ing)?)\b/i'],
    ['"in today\'s ..." / "in a world where"', '/\b(in today\'?s|in a world where|imagine a world)\b/i'],
    ['"ever-evolving" / "rapidly evolving" / "cutting-edge"', '/\b(ever[- ]evolving|rapidly (evolving|changing)|cutting[- ]edge)\b/i'],
    ['"transformative" / "revolutionary" / "paradigm shift"', '/\b(transformative|revolutioni[sz]e|revolutionary|paradigm shift)\b/i'],
    ['"seamless" / "robust" / "holistic" / "synergy"', '/\b(seamless(ly)?|robust|holistic|synerg(y|ies))\b/i'],
    ['"testament to" / "a masterclass in"', '/\b(testament to|masterclass)\b/i'],
    ['"crucial" / "pivotal" / "vital"', '/\b(crucial|pivotal|vital)\b/i'],
    ['"journey" (for anything that isn\'t a trip)', '/\bjourney\b/i'],
    ['"north star" / "move the needle" / "secret sauce"', '/\b(north star|move the needle|secret sauce)\b/i'],
    ['"supercharge" / "level up" / "10x"', '/\b(supercharg(e|es|ing)|level up|10x)\b/i'],
    ['"the power of"', '/\bthe power of\b/i'],
    ['"at the end of the day"', '/at the end of the day/i'],
    ['"buckle up" / "spoiler:" / "plot twist"', '/\b(buckle up|spoiler:|plot twist)\b/i'],
    ['"read that again" / "full stop." / "period."', '/\b(read that again|full stop\.|period\.)/i'],
    ['"this resonates" / "hits different"', '/\b(resonat(es|ed|ing)|hits different)\b/i'],
    ['"curious to hear your thoughts" / "Thoughts?" / "Agree?" / "drop a comment"', '/\b(curious (to hear|what)|thoughts\?|agree\?|drop a comment|let me know in the comments)/i'],
    ['"I hope this helps" / "in conclusion" / "ultimately"', '/\b(hope this helps|in conclusion|ultimately)\b/i'],
    ['"moreover" / "furthermore" / "additionally"', '/\b(moreover|furthermore|additionally)\b/i'],
    ['"absolutely" / "certainly" / "indeed" as filler', '/\b(absolutely|certainly|indeed)\b/i'],
    ['"that\'s where X comes in" / "enter: X"', '/(that\'?s where .{1,40} comes in|\benter:)/i'],
    ['"more than just"', '/\bmore than just\b/i'],
    ['"profound" / "intricate" / "nuanced"', '/\b(profound(ly)?|intricate|nuanced)\b/i'],
    ['hashtags', '/(^|\s)#\w+/u'],
];

/** The list as the AI sees it. */
function rrAiIsmRules(): string {
    return implode("\n", array_map(fn($r) => '- ' . $r[0], RR_AI_ISMS));
}

/** Which AI-isms a piece of text has (labels). */
function rrAiIsmsIn(string $text): array {
    $hits = [];
    foreach (RR_AI_ISMS as [$label, $re]) {
        if (preg_match($re, $text)) {
            $hits[] = $label;
        }
    }
    return $hits;
}

/** What can be fixed without the AI: dashes, emoji, hashtags, double spaces. */
function rrCleanDraft(string $text): string {
    $text = preg_replace('/\s*[\x{2014}\x{2013}]\s*/u', ', ', $text);          // "a — b" -> "a, b"
    $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $text);
    $text = preg_replace('/(^|\s)#\w+/u', '$1', $text);
    $text = preg_replace('/ {2,}/', ' ', $text);
    $text = preg_replace('/\s+([,.!?])/', '$1', $text);
    return trim($text);
}
