<?php

namespace App\Services\Shopping;

use App\Services\Ai\ShopperAi;

/**
 * Gift mode plan: one RunPod call (when on) turns occasion + recipient + budget
 * + note into a few generic product searches; offline rules otherwise. The plan
 * never contains products — ShoppingSearch finds real in-stock listings.
 */
class GiftIdeas
{
    public const OCCASIONS = ['birthday', 'anniversary', 'wedding', 'graduation', 'baby shower', 'valentine', 'thank you', 'christmas', 'eid', 'just because'];

    public const RECIPIENTS = ['her', 'him', 'mum', 'dad', 'friend', 'kid', 'colleague', 'couple'];

    private const BY_RECIPIENT = [
        'her' => ['perfume', 'handbag', 'jewelry', 'wristwatch', 'dress'],
        'him' => ['wristwatch', 'perfume', 'wallet', 'sneakers', 'shirt'],
        'mum' => ['fabric', 'handbag', 'blender', 'perfume', 'jewelry'],
        'dad' => ['wristwatch', 'shirt', 'perfume', 'shoes', 'cap'],
        'friend' => ['perfume', 'earbuds', 'power bank', 'bag', 'sneakers'],
        'kid' => ['toys', 'school bag', 'sneakers', 'books', 'game'],
        'colleague' => ['mug', 'notebook', 'perfume', 'power bank', 'bag'],
        'couple' => ['bedsheet', 'blender', 'cookware', 'wall art', 'perfume'],
    ];

    private const BY_OCCASION = [
        'wedding' => ['cookware', 'blender', 'bedsheet'],
        'baby shower' => ['baby clothes', 'baby', 'diaper bag'],
        'graduation' => ['wristwatch', 'laptop bag', 'shoes'],
        'valentine' => ['perfume', 'jewelry', 'flowers'],
        'anniversary' => ['perfume', 'jewelry', 'wristwatch'],
        'christmas' => ['perfume', 'clothes', 'toys'],
    ];

    public function __construct(private readonly ShopperAi $ai) {}

    /** @return array{queries: list<string>, note: ?string, ai: bool} */
    public function plan(?string $occasion, ?string $recipient, ?int $budgetMinor, ?string $note): array
    {
        if ($this->ai->enabled()) {
            $plan = $this->ai->plan([
                'task' => 'gift',
                'occasion' => $occasion, 'recipient' => $recipient,
                'budget_naira' => $budgetMinor ? (int) round($budgetMinor / 100) : null,
                'note' => $note ? mb_substr($note, 0, 200) : null,
            ]);
            if ($plan) {
                return $plan + ['ai' => true];
            }
        }

        return ['queries' => $this->rules($occasion, $recipient, $note), 'note' => null, 'ai' => false];
    }

    /** @return list<string> */
    public function rules(?string $occasion, ?string $recipient, ?string $note = null): array
    {
        $q = [];
        if ($note && ($k = QueryHints::keywords($note)) !== '') {
            $q[] = mb_substr($k, 0, 60);
        }
        $q = array_merge($q, self::BY_OCCASION[strtolower((string) $occasion)] ?? [], self::BY_RECIPIENT[strtolower((string) $recipient)] ?? ['perfume', 'wristwatch', 'bag', 'earbuds']);

        return array_values(array_slice(array_unique($q), 0, 5));
    }

    /** Recipient words in free speech ("for my wife") → a recipient key. */
    public static function recipientFrom(string $text): ?string
    {
        $map = ['her' => '/\b(her|wife|girlfriend|sister|daughter|aunt|she|babe)\b/i', 'him' => '/\b(him|husband|boyfriend|brother|son|uncle|he)\b/i',
            'mum' => '/\b(mum|mom|mother|mama)\b/i', 'dad' => '/\b(dad|father|papa)\b/i', 'kid' => '/\b(kid|child|baby|toddler|nephew|niece)\b/i',
            'friend' => '/\bfriend\b/i', 'colleague' => '/\b(colleague|boss|co-?worker)\b/i', 'couple' => '/\b(couple|newlyweds)\b/i'];
        foreach ($map as $k => $re) {
            if (preg_match($re, $text)) {
                return $k;
            }
        }

        return null;
    }

    public static function occasionFrom(string $text): ?string
    {
        foreach (self::OCCASIONS as $o) {
            if (stripos($text, $o) !== false) {
                return $o;
            }
        }

        return null;
    }
}
