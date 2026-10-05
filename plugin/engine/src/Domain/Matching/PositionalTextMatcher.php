<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\TextElement;

/**
 * Matches design texts to page texts by wording and position.
 *
 * 1. Candidates: same wording (ignoring case, punctuation, spacing), or clearly similar wording
 *    (most words shared, for longer texts), within a vertical distance of the same relative place.
 * 2. Score = wording score minus distance, so repeated texts ("Reviews" in the menu and as a
 *    section title, three "User Name" cards) go to the nearest page text.
 * 3. Greedy assignment, best score first; ties are broken by order, so results are deterministic.
 * 4. When a second free candidate is practically as good, the match is uncertain: it is listed
 *    for a manual check instead of guessed.
 * 5. Passes, strictest first:
 *    a. same wording, whole texts;
 *    b. same wording per paragraph, for texts with several paragraphs (Figma keeps them in one
 *       layer, the page in several <p>);
 *    c. similar wording, whole texts of which no paragraph was found;
 *    d. similar wording for the paragraphs still left;
 *    e. short texts (buttons, labels, placeholders) whose wording changed: lined up in order between
 *       the already matched texts around them, with letters or style alike.
 *       Without this, "Get the book" vs "Buy the book" would only show up as a missing text.
 */
final readonly class PositionalTextMatcher implements Matcher
{
    /** Largest vertical distance, as a share of the screen height, for two texts to match. */
    public const MAX_VERTICAL_DISTANCE = 0.12;

    private const DISTANCE_PENALTY = 2.0;

    /** Similar wording: share of words in common (Dice coefficient), and the fewest words needed. */
    private const SIMILAR_MIN_SHARE = 0.75;
    private const SIMILAR_MIN_WORDS = 4;
    private const SIMILAR_SCORE = 0.9;

    /** Two candidates closer than this in score are equally good. */
    private const AMBIGUITY_MARGIN = 0.01;

    /** Pass e: texts this short, this close to the gap between neighbours, and this alike. */
    private const SHORT_MAX_WORDS = 6;
    /** Margin around the gap between two matched neighbours on the page. */
    private const SHORT_MAX_VERTICAL_PX = 24.0;
    /** Buttons shift sideways when a sibling button is added or removed, so allow more here. */
    private const SHORT_MAX_HORIZONTAL = 0.1;
    private const SHORT_MIN_LETTERS_ALIKE = 0.5;
    /**
     * A same-size, same-weight pair lowers the letters-alike bar (a button keeps its style when
     * reworded) but must not waive it: two unrelated short texts that simply share a font size and
     * weight (e.g. a card's hover link and a cookie banner's "Accept All" button) are not the same
     * text just because they happen to look alike and sit in the same gap.
     */
    private const SHORT_MIN_LETTERS_ALIKE_WITH_SAME_STYLE = 0.4;
    private const SHORT_SCORE = 0.6;

    public function __construct(private TextNormalizer $normalizer = new TextNormalizer()) {}

    public function match(Screen $design, Screen $page): MatchResult
    {
        $parts = [];
        $symbols = [];
        foreach ($design->texts as $text) {
            if ($this->normalizer->key($text->content) === '') {
                $symbols[] = $text;
            } else {
                $parts[] = TextPart::whole($text);
            }
        }

        $geometry = new ScreenGeometry($design, $page);
        // A page text without letters or digits (e.g. a stray bullet) has no Figma counterpart to
        // match and would otherwise end up reported as "extra on the page", the same as a design
        // symbol would wrongly end up "missing" without this filter on the design side above.
        $pages = [];
        $pageSymbols = [];
        foreach ($page->texts as $text) {
            if ($this->normalizer->key($text->content) === '') {
                $pageSymbols[] = $text;
            } else {
                $pages[] = $text;
            }
        }
        $pageKeys = array_map(fn(TextElement $t): string => $this->normalizer->key($t->content), $pages);

        // A text whose wording is unique on both sides cannot be confused with another text, so an
        // exact match for it is trusted regardless of position: unlike a static mockup, a real page
        // can shift a section much further up or down than Figma's own layout implies (e.g. a
        // shorter card above it, or a feature tab that renders a different height of content).
        $designKeyCounts = array_count_values(array_map(fn(TextPart $p): string => $this->normalizer->key($p->content()), $parts));
        $pageKeyCounts = array_count_values($pageKeys);

        // a. same wording, whole texts
        $whole = $this->assign($parts, $pages, $pageKeys, $geometry, similar: false, designKeyCounts: $designKeyCounts, pageKeyCounts: $pageKeyCounts);

        // b. same wording per paragraph
        $paragraphs = [];
        foreach ($whole['unmatched'] as $part) {
            $split = array_values(array_filter(
                TextPart::paragraphs($part->text),
                fn(TextPart $p): bool => $this->normalizer->key($p->content()) !== '', // "* * *" etc. cannot be matched
            ));
            array_push($paragraphs, ...(count($split) > 1 ? $split : [$part]));
        }
        $split = $this->assign($paragraphs, $whole['free'], $pageKeys, $geometry, similar: false);

        // c. similar wording, whole texts with no paragraph found
        $found = [];
        foreach ([...$split['matches'], ...$split['uncertain']] as $decided) {
            $found[$decided->design->text->id] = true;
        }
        $wholeLeft = [];
        $paragraphsLeft = [];
        foreach ($split['unmatched'] as $part) {
            if (isset($found[$part->text->id])) {
                $paragraphsLeft[] = $part;
            } elseif (!isset($wholeLeft[$part->text->id])) {
                $wholeLeft[$part->text->id] = TextPart::whole($part->text);
            }
        }
        $similarWhole = $this->assign(array_values($wholeLeft), $split['free'], $pageKeys, $geometry, similar: true);

        // d. similar wording for the paragraphs still left (only texts that really were split: a
        //    single-paragraph text already had its similar-wording chance in pass c)
        $retried = [];
        foreach ($similarWhole['unmatched'] as $part) {
            foreach ($split['unmatched'] as $paragraph) {
                if ($paragraph->text->id === $part->text->id && !$paragraph->isWhole()) {
                    $paragraphsLeft[] = $paragraph;
                    $retried[$part->text->id] = true;
                }
            }
        }
        // Whole texts not retried as paragraphs go straight on to pass e (and stay in the result).
        $singles = array_values(array_filter($similarWhole['unmatched'], static fn(TextPart $p): bool => !isset($retried[$p->text->id])));
        $similar = $this->assign($paragraphsLeft, $similarWhole['free'], $pageKeys, $geometry, similar: true);

        // e. short texts with changed wording, lined up between already matched texts
        $anchors = [...$whole['matches'], ...$split['matches'], ...$similarWhole['matches'], ...$similar['matches']];
        $short = $this->assignShort([...$singles, ...$similar['unmatched']], $similar['free'], $pageKeys, $geometry, $anchors);

        $matches = [...$whole['matches'], ...$split['matches'], ...$similarWhole['matches'], ...$similar['matches'], ...$short['matches']];
        $uncertain = [...$whole['uncertain'], ...$split['uncertain'], ...$similarWhole['uncertain'], ...$similar['uncertain'], ...$short['uncertain']];

        return new MatchResult(
            $matches,
            $uncertain,
            $this->wholeTextWhenNothingMatched($matches, $uncertain, $short['unmatched']),
            array_values($short['free']),
            $symbols,
            $pageSymbols,
        );
    }

    /**
     * A design text split into paragraphs (pass b) that found no match anywhere, for any of its
     * paragraphs, is reported as the one text it is, not as separate, meaningless line fragments
     * ("Carry-based Differential", "Power Analysis (CDPA)", ...): the page never had anything to
     * match those fragments against in the first place, so splitting bought nothing. A text with at
     * least one matched or uncertain paragraph keeps its remaining fragments as they are: that text
     * genuinely was found in parts, so reporting which part is still missing stays useful.
     *
     * @param list<TextMatch>      $matches
     * @param list<UncertainMatch> $uncertain
     * @param list<TextPart>       $unmatched
     *
     * @return list<TextPart>
     */
    private function wholeTextWhenNothingMatched(array $matches, array $uncertain, array $unmatched): array
    {
        $found = [];
        foreach ([...$matches, ...$uncertain] as $decided) {
            $found[$decided->design->text->id] = true;
        }

        $collapsed = [];
        $wholeAdded = [];
        foreach ($unmatched as $part) {
            $id = $part->text->id;
            if ($part->isWhole() || isset($found[$id])) {
                $collapsed[] = $part;
                continue;
            }
            if (!isset($wholeAdded[$id])) {
                $wholeAdded[$id] = true;
                $collapsed[] = TextPart::whole($part->text);
            }
        }

        return $collapsed;
    }

    /**
     * @param list<TextPart>          $parts
     * @param array<int, TextElement> $pages           page texts still free, keyed by their index on the screen
     * @param array<int, string>      $pageKeys         matching key of every page text, same keys
     * @param array<string, int>      $designKeyCounts  how often each design key occurs overall (whole screen);
     *                                                  empty: position always applies (pass a only has this)
     * @param array<string, int>      $pageKeyCounts    how often each page key occurs overall (whole screen)
     *
     * @return array{matches: list<TextMatch>, uncertain: list<UncertainMatch>, unmatched: list<TextPart>, free: array<int, TextElement>}
     */
    private function assign(array $parts, array $pages, array $pageKeys, ScreenGeometry $geometry, bool $similar, array $designKeyCounts = [], array $pageKeyCounts = []): array
    {
        $candidates = [];
        foreach ($parts as $i => $part) {
            $designKey = $this->normalizer->key($part->content());
            foreach ($pages as $j => $page) {
                $wording = $this->wording($designKey, $pageKeys[$j], $similar);
                if ($wording === null) {
                    continue;
                }
                // Position only exists to tell apart texts that could otherwise be confused (e.g.
                // "Reviews" in the nav and as a section title): it is not needed, and not trusted
                // over the wording, when this exact wording occurs exactly once on each side.
                $unambiguous = $wording['kind'] === MatchKind::Exact
                    && ($designKeyCounts[$designKey] ?? 0) === 1
                    && ($pageKeyCounts[$pageKeys[$j]] ?? 0) === 1;
                $tooFar = $geometry->offsets($part, $page)[0] > self::MAX_VERTICAL_DISTANCE;
                $distance = $geometry->distance($part, $page, $unambiguous);
                if ($distance === null) {
                    continue;
                }
                $candidates[] = [
                    'score' => $wording['score'] - $distance * self::DISTANCE_PENALTY,
                    'part' => $i,
                    'page' => $j,
                    'kind' => $wording['kind'],
                    'wording' => $wording['score'],
                    'distance' => $distance,
                    // True only when the match exists purely because the wording is unique on both
                    // sides (see $unambiguous above): the text is unmistakably the same, just found
                    // much further from its Figma position than usual.
                    'moved' => $tooFar,
                ];
            }
        }

        return $this->decide($parts, $pages, $candidates);
    }

    /**
     * Pass e. Short texts whose wording changed (buttons, labels, placeholders).
     *
     * Between two neighbouring texts that are already matched, the leftover design texts and the
     * leftover page texts appear in the same order from top to bottom. They are lined up in that
     * order, choosing the pairing with the most alike wording, and skipping texts that exist on only
     * one side (a field missing on the page, an extra button). A pair must also sit at a similar
     * position (within the usual vertical limit and a wider horizontal one), and have alike letters
     * or the same main style.
     *
     * @param list<TextPart>          $parts
     * @param array<int, TextElement> $pages
     * @param array<int, string>      $pageKeys
     * @param list<TextMatch>         $anchors matches from the earlier passes
     *
     * @return array{matches: list<TextMatch>, uncertain: list<UncertainMatch>, unmatched: list<TextPart>, free: array<int, TextElement>}
     */
    private function assignShort(array $parts, array $pages, array $pageKeys, ScreenGeometry $geometry, array $anchors): array
    {
        $isShort = fn(string $key): bool => $key !== '' && count($this->normalizer->wordsOfKey($key)) <= self::SHORT_MAX_WORDS;
        $designShort = array_values(array_filter($parts, fn(TextPart $p): bool => $isShort($this->normalizer->key($p->content()))));
        $pageShort = array_filter($pages, static fn(int $j): bool => $isShort($pageKeys[$j]), ARRAY_FILTER_USE_KEY);

        // Neighbours in design order; each gap lies between two of them (or before the first / after the last).
        usort($anchors, static fn(TextMatch $a, TextMatch $b): int => ScreenGeometry::designY($a->design) <=> ScreenGeometry::designY($b->design));
        $bounds = [[-INF, -INF]];
        foreach ($anchors as $anchor) {
            $bounds[] = [ScreenGeometry::designY($anchor->design), $anchor->page->box->y];
        }
        $bounds[] = [INF, INF];

        $matches = [];
        $taken = [];
        $used = []; // design parts already matched in an earlier gap (gap ends touch)
        for ($g = 0, $gaps = count($bounds) - 1; $g < $gaps; ++$g) {
            [$designTop, $pageTop] = $bounds[$g];
            [$designBottom, $pageBottom] = $bounds[$g + 1];
            if ($pageBottom < $pageTop) {
                continue; // neighbours in a different order on the page: no reliable gap
            }
            $design = array_values(array_filter(
                $designShort,
                static fn(TextPart $p): bool => !isset($used[spl_object_id($p)])
                    && ScreenGeometry::designY($p) >= $designTop && ScreenGeometry::designY($p) <= $designBottom,
            ));
            $page = array_filter(
                $pageShort,
                static fn(TextElement $t, int $j): bool => !isset($taken[$j])
                    && $t->box->y >= $pageTop - self::SHORT_MAX_VERTICAL_PX && $t->box->y <= $pageBottom + self::SHORT_MAX_VERTICAL_PX,
                ARRAY_FILTER_USE_BOTH,
            );
            if ($design === [] || $page === []) {
                continue;
            }
            usort($design, static fn(TextPart $a, TextPart $b): int => [ScreenGeometry::designY($a), $a->text->box->x] <=> [ScreenGeometry::designY($b), $b->text->box->x]);
            uasort($page, static fn(TextElement $a, TextElement $b): int => [$a->box->y, $a->box->x] <=> [$b->box->y, $b->box->x]);

            foreach ($this->lineUp($design, $page, $pageKeys, $geometry) as [$part, $j, $alike]) {
                $matches[] = new TextMatch($part, $pages[$j], MatchKind::Similar, round(self::SHORT_SCORE * $alike, 3));
                $taken[$j] = true;
                $used[spl_object_id($part)] = true;
            }
        }

        $matched = array_map(static fn(TextMatch $m): TextPart => $m->design, $matches);

        return [
            'matches' => $matches,
            'uncertain' => [],
            'unmatched' => array_values(array_filter($parts, static fn(TextPart $part): bool => !in_array($part, $matched, true))),
            'free' => array_diff_key($pages, $taken),
        ];
    }

    /**
     * Order-preserving pairing with the highest total likeness (like a text diff).
     *
     * @param list<TextPart>          $design   in reading order
     * @param array<int, TextElement> $page     in reading order, keyed by page index
     * @param array<int, string>      $pageKeys
     *
     * @return list<array{TextPart, int, float}> design part, page index, likeness
     */
    private function lineUp(array $design, array $page, array $pageKeys, ScreenGeometry $geometry): array
    {
        $pageIndexes = array_keys($page);
        $n = count($design);
        $m = count($pageIndexes);

        /** @var array<int, array<int, float|null>> $alike likeness of design[i] and page[k], null when they cannot pair */
        $alike = [];
        foreach ($design as $i => $part) {
            $designKey = $this->normalizer->key($part->content());
            $designStyle = $part->text->dominantStyle();
            foreach ($pageIndexes as $k => $j) {
                [$vertical, $horizontal] = $geometry->offsets($part, $page[$j]);
                similar_text($designKey, $pageKeys[$j], $percent);
                $letters = $percent / 100;
                $pageStyle = $page[$j]->dominantStyle();
                $sameStyle = abs($designStyle->fontSize - $pageStyle->fontSize) < 0.5 && $designStyle->fontWeight === $pageStyle->fontWeight;
                // Same limits as the other passes vertically (a gap without neighbours can span the screen).
                $canPair = $vertical <= self::MAX_VERTICAL_DISTANCE && $horizontal <= self::SHORT_MAX_HORIZONTAL
                    && ($letters >= self::SHORT_MIN_LETTERS_ALIKE
                        || ($sameStyle && $letters >= self::SHORT_MIN_LETTERS_ALIKE_WITH_SAME_STYLE));
                $alike[$i][$k] = $canPair ? $letters : null;
            }
        }

        // best[i][k]: highest total likeness when pairing design[0..i) with page[0..k) in order
        $best = array_fill(0, $n + 1, array_fill(0, $m + 1, 0.0));
        for ($i = 1; $i <= $n; ++$i) {
            for ($k = 1; $k <= $m; ++$k) {
                $score = $alike[$i - 1][$k - 1];
                $best[$i][$k] = max($best[$i - 1][$k], $best[$i][$k - 1], $score === null ? -1.0 : $best[$i - 1][$k - 1] + $score + 1.0);
            }
        }

        $pairs = [];
        for ($i = $n, $k = $m; $i > 0 && $k > 0;) {
            $score = $alike[$i - 1][$k - 1];
            if ($score !== null && abs($best[$i][$k] - ($best[$i - 1][$k - 1] + $score + 1.0)) < 1e-9) {
                $pairs[] = [$design[$i - 1], $pageIndexes[$k - 1], $score];
                --$i;
                --$k;
            } elseif ($best[$i - 1][$k] >= $best[$i][$k - 1]) {
                --$i;
            } else {
                --$k;
            }
        }

        return array_reverse($pairs);
    }

    /**
     * Best score first; ties broken by order. A part with a practically equal second candidate is
     * uncertain (check manually), never guessed.
     *
     * @param list<TextPart>                                                                                    $parts
     * @param array<int, TextElement>                                                                           $pages
     * @param list<array{score: float, part: int, page: int, kind: MatchKind, wording: float, distance: float, moved: bool}> $candidates
     *
     * @return array{matches: list<TextMatch>, uncertain: list<UncertainMatch>, unmatched: list<TextPart>, free: array<int, TextElement>}
     */
    private function decide(array $parts, array $pages, array $candidates): array
    {
        usort($candidates, static fn(array $a, array $b): int => [$b['score'], $a['part'], $a['page']] <=> [$a['score'], $b['part'], $b['page']]);
        $byPart = [];
        foreach ($candidates as $candidate) {
            $byPart[$candidate['part']][] = $candidate;
        }

        $matches = [];
        $uncertain = [];
        $decided = [];
        foreach ($candidates as $candidate) {
            ['part' => $i, 'page' => $j] = $candidate;
            if (isset($decided[$i]) || !isset($pages[$j])) {
                continue;
            }
            $decided[$i] = true;

            $rivals = $this->rivals($byPart[$i], $candidate, $pages);
            if ($rivals !== []) {
                $uncertain[] = new UncertainMatch($parts[$i], [$pages[$j], ...$rivals]);
                continue;
            }

            $matches[] = new TextMatch(
                $parts[$i],
                $pages[$j],
                $candidate['kind'],
                round(max(0.0, $candidate['wording'] * (1 - $candidate['distance'] / self::MAX_VERTICAL_DISTANCE / 2)), 3),
                moved: $candidate['moved'],
            );
            unset($pages[$j]);
        }

        $unmatched = array_values(array_filter($parts, static fn(int $i): bool => !isset($decided[$i]), ARRAY_FILTER_USE_KEY));

        return ['matches' => $matches, 'uncertain' => $uncertain, 'unmatched' => $unmatched, 'free' => $pages];
    }

    /**
     * Other free page texts that are practically as good a match for the same design part.
     *
     * @param list<array{score: float, part: int, page: int, kind: MatchKind, wording: float, distance: float, moved: bool}> $partCandidates the part's candidates, best first
     * @param array{score: float, part: int, page: int, kind: MatchKind, wording: float, distance: float, moved: bool}       $best
     * @param array<int, TextElement>                                                                          $pages
     *
     * @return list<TextElement>
     */
    private function rivals(array $partCandidates, array $best, array $pages): array
    {
        $rivals = [];
        foreach ($partCandidates as $other) {
            if ($best['score'] - $other['score'] >= self::AMBIGUITY_MARGIN) {
                break; // sorted: the rest are worse
            }
            if ($other['page'] !== $best['page'] && isset($pages[$other['page']])) {
                $rivals[] = $pages[$other['page']];
            }
        }

        return $rivals;
    }

    /**
     * @return array{score: float, kind: MatchKind}|null
     */
    private function wording(string $designKey, string $pageKey, bool $similar): ?array
    {
        if ($designKey === '' || $pageKey === '') {
            return null;
        }
        if ($designKey === $pageKey) {
            return ['score' => 1.0, 'kind' => MatchKind::Exact];
        }
        if (!$similar) {
            return null;
        }

        $designWords = $this->normalizer->wordsOfKey($designKey);
        $pageWords = $this->normalizer->wordsOfKey($pageKey);
        if (min(count($designWords), count($pageWords)) < self::SIMILAR_MIN_WORDS) {
            return null;
        }
        $share = self::sharedWords($designWords, $pageWords);

        return $share >= self::SIMILAR_MIN_SHARE ? ['score' => $share * self::SIMILAR_SCORE, 'kind' => MatchKind::Similar] : null;
    }

    /**
     * Dice coefficient over words (counting repeats): 1 = same words, 0 = none in common.
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function sharedWords(array $a, array $b): float
    {
        $counts = array_count_values($b);
        $shared = 0;
        foreach ($a as $word) {
            if (($counts[$word] ?? 0) > 0) {
                --$counts[$word];
                ++$shared;
            }
        }

        return 2 * $shared / (count($a) + count($b));
    }
}
