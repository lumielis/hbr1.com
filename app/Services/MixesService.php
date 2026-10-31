<?php

namespace App\Services;

/**
 * Mixes: DJ sets and their track listings.
 *
 * Replaces `mixes.cgi` + `include/admin/mixes.pl` (the read side only - the
 * editor is W8). Three levels, exactly as the legacy `action` parameter:
 *
 *   (none)                    -> categories  (`mix_categories.htm`)
 *   action=view&cat_id=N      -> mixes in a category (`mixes.htm`)
 *   action=playlist&mix_id=N  -> one mix and its releases (`mix_details.htm`)
 *
 * `mixes.category` points at `media_category` rows with `flags = 1`, while the
 * gallery's categories live in `MCG_MediaCategory`. These are *different*
 * tables and must not be conflated - see deploy/GALLERY-SCHEMA.md.
 */
class MixesService
{
    /** `media_category.flags` bit that marks a mix category. */
    private const FLAG_MIX = 1;

    public function __construct(private \Azera\Db\Database $db)
    {
    }

    /**
     * Mix categories, with their published-mix counts.
     *
     * All flagged categories are listed even when empty (as the legacy page
     * did); the count lets the view distinguish an empty style from a broken
     * one. Today the table holds a single unfiled row, so this is mostly an
     * empty page - see docs/README.md.
     *
     * @return array<int, array{id: int, name: string, count: int}>
     */
    public function categories(): array
    {
        $rows = $this->db->query(
            'SELECT c.`id`, c.`name`, COUNT(m.`id`) AS `count`'
            . ' FROM `media_category` c'
            . ' LEFT JOIN `mixes` m ON m.`category` = c.`id` AND m.`status` = 1'
            . ' WHERE (c.`flags` & ?) = ?'
            . ' GROUP BY c.`id`, c.`name`'
            . ' ORDER BY c.`name`',
            [self::FLAG_MIX, self::FLAG_MIX]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static fn(array $row): array => [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'count' => (int) $row['count'],
            ],
            $rows
        );
    }

    public function category(int $id): ?array
    {
        $row = $this->db->query(
            'SELECT `id`, `name` FROM `media_category` WHERE `id` = ? AND (`flags` & ?) = ?',
            [$id, self::FLAG_MIX, self::FLAG_MIX]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row === false
            ? null
            : ['id' => (int) $row['id'], 'name' => (string) $row['name']];
    }

    /**
     * Published mixes, optionally limited to one category.
     *
     * @return array<int, array>
     */
    public function mixes(?int $categoryId = null, ?string $search = null): array
    {
        $sql    = 'SELECT * FROM `mixes` WHERE `status` = 1';
        $params = [];

        if ($categoryId !== null) {
            $sql .= ' AND `category` = ?';
            $params[] = $categoryId;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (`name` LIKE ? OR `dj` LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= ' ORDER BY `name`';

        $rows = $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /** One mix plus its track listing. */
    public function find(int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM `mixes` WHERE `id` = ?', [$id])
            ->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $mix = $this->hydrate($row);

        $mix['releases'] = $this->releases($id);
        $mix['labels']   = $this->labels($mix['releases']);

        return $mix;
    }

    /**
     * The track listing, ordered by `pos` as the legacy page did.
     *
     * @return array<int, array>
     */
    public function releases(int $mixId): array
    {
        $rows = $this->db->query(
            'SELECT `id`, `name`, `label`, `type`, `pos`, `hits`'
            . ' FROM `releases` WHERE `mix` = ? ORDER BY `pos`, `id`',
            [$mixId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $types = $this->releaseTypes();

        return array_map(
            static fn(array $row): array => [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'label' => (int) $row['label'],
                'type'  => $types[(int) $row['type']] ?? '',
                'pos'   => (int) $row['pos'],
                'hits'  => (int) $row['hits'],
            ],
            $rows
        );
    }

    /**
     * Label names for the tracks that reference one.
     *
     * @param array<int, array> $releases
     * @return array<int, string> label id => name
     */
    private function labels(array $releases): array
    {
        $ids = array_values(array_unique(array_filter(
            array_column($releases, 'label'),
            static fn(int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            'SELECT `id`, `name` FROM `labels` WHERE `id` IN (' . $placeholders . ')',
            $ids
        )->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = (string) $row['name'];
        }

        return $out;
    }

    /** @return array<int, string> */
    private function releaseTypes(): array
    {
        $rows = $this->db->query('SELECT `id`, `name` FROM `release_types`')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = (string) $row['name'];
        }

        return $out;
    }

    private function hydrate(array $row): array
    {
        $length = (int) $row['length'];

        return [
            'id'          => (int) $row['id'],
            'name'        => (string) $row['name'],
            'dj'          => (string) $row['dj'],
            'category'    => (int) $row['category'],
            'timestamp'   => (int) $row['timestamp'],
            'length'      => $length,
            // `mixes.length` is stored in seconds.
            'lengthLabel' => $length > 0
                ? sprintf('%d:%02d', intdiv($length, 3600), intdiv($length % 3600, 60))
                : '',
            'status'      => (int) $row['status'],
        ];
    }
}
