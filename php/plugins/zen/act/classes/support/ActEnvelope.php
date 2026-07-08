<?php namespace Zen\Act\Classes\Support;

use Zen\Act\Models\Act;

/**
 * Каноническое представление полного состояния акта для хеширования и current.json.
 */
class ActEnvelope
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array{meta?: array<string, string>, grants?: list<array<string, mixed>>}  $access
     * @param  list<string>  $owner_user_tags
     * @return array<string, mixed>
     */
    public function build(Act $act, array $blocks, array $access, array $owner_user_tags = []): array
    {
        return [
            'id' => (string) $act->id,
            'name' => (string) ($act->name ?? ''),
            'description' => $act->description !== null ? (string) $act->description : null,
            'owner_id' => $act->owner_id !== null ? (int) $act->owner_id : null,
            'activate_at' => $act->activate_at?->toIso8601String(),
            'stop_at' => $act->stop_at?->toIso8601String(),
            'created_at' => $act->created_at?->toIso8601String(),
            'updated_at' => $act->updated_at?->toIso8601String(),
            'blocks' => $this->normalizeBlocks($blocks),
            'access' => $access,
            'owner_user_tags' => array_values($owner_user_tags),
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function hash(array $envelope): string
    {
        return hash('sha256', $this->canonicalJson($envelope));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function normalizeBlocks(array $blocks): array
    {
        $normalized = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $normalized[] = [
                'id' => (string) ($block['id'] ?? ''),
                'name' => (string) ($block['name'] ?? ''),
                'data' => $block['data'] ?? null,
                'sort_order' => array_key_exists('sort_order', $block) && $block['sort_order'] !== null
                    ? (int) $block['sort_order']
                    : null,
                'created_at' => (string) ($block['created_at'] ?? ''),
                'updated_at' => (string) ($block['updated_at'] ?? ''),
                'hash' => (string) ($block['hash'] ?? ''),
            ];
        }

        usort($normalized, function (array $a, array $b): int {
            $order_a = $a['sort_order'] ?? PHP_INT_MAX;
            $order_b = $b['sort_order'] ?? PHP_INT_MAX;
            if ($order_a !== $order_b) {
                return $order_a <=> $order_b;
            }

            return strcmp((string) $a['created_at'], (string) $b['created_at']);
        });

        return $normalized;
    }

    public function canonicalJson(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
