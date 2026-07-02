<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

class AgendaTreeService {
    public function orderedTreeItems(array $items): array {
        $nodes = [];
        $rootIds = [];

        foreach ($items as $item) {
            $id = (int)$item['id'];
            $item['children'] = [];
            $nodes[$id] = $item;
        }

        foreach ($nodes as $id => &$node) {
            $parentId = (int)($node['parent_id'] ?? 0);
            if ($parentId > 0 && isset($nodes[$parentId]) && $parentId !== $id) {
                $nodes[$parentId]['children'][] = $id;
            } else {
                $node['parent_id'] = null;
                $rootIds[] = $id;
            }
        }
        unset($node);

        $result = [];
        $visited = [];

        $walk = function (array $ids, int $level) use (&$walk, &$nodes, &$result, &$visited): void {
            foreach ($ids as $id) {
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;

                $node = $nodes[$id];
                $children = $node['children'];
                unset($node['children']);
                $node['level'] = $level;
                $result[] = $node;

                if (count($children) > 0 && $level < 3) {
                    $walk($children, $level + 1);
                } elseif (count($children) > 0) {
                    foreach ($children as $childId) {
                        $nodes[$childId]['parent_id'] = null;
                    }
                    $walk($children, 1);
                }
            }
        };

        $walk($rootIds, 1);

        foreach ($nodes as $id => $node) {
            if (!isset($visited[$id])) {
                unset($node['children']);
                $node['parent_id'] = null;
                $node['level'] = 1;
                $result[] = $node;
            }
        }

        return $result;
    }

    public function numberedItems(array $tops): array {
        $nodes = [];
        $rootIds = [];

        foreach ($tops as $top) {
            $id = (int)$top['id'];
            $top['children'] = [];
            $nodes[$id] = $top;
        }

        foreach ($nodes as $id => &$node) {
            $parentId = (int)($node['parent_id'] ?? 0);
            if ($parentId > 0 && isset($nodes[$parentId]) && $parentId !== $id) {
                $nodes[$parentId]['children'][] = $id;
            } else {
                $rootIds[] = $id;
            }
        }
        unset($node);

        $result = [];
        $visited = [];

        $walk = function (array $ids, string $prefix) use (&$walk, &$nodes, &$result, &$visited): void {
            foreach ($ids as $index => $id) {
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;

                $number = $prefix === '' ? (string)($index + 1) : $prefix . '.' . ($index + 1);
                $node = $nodes[$id];
                $children = $node['children'];
                unset($node['children']);
                $node['agenda_number'] = $number;
                $result[] = $node;

                if (count($children) > 0) {
                    $walk($children, $number);
                }
            }
        };

        $walk($rootIds, '');

        foreach ($nodes as $id => $node) {
            if (!isset($visited[$id])) {
                unset($node['children']);
                $node['agenda_number'] = (string)(count($result) + 1);
                $result[] = $node;
            }
        }

        return $result;
    }

    public function findItem(array $items, int $topId): ?array {
        foreach ($items as $item) {
            if ((int)$item['id'] === $topId) {
                return $item;
            }
        }

        return null;
    }

    public function indexOfItem(array $items, int $topId): ?int {
        foreach ($items as $index => $item) {
            if ((int)$item['id'] === $topId) {
                return $index;
            }
        }

        return null;
    }

    public function parentId(array $item): ?int {
        $parentId = (int)($item['parent_id'] ?? 0);

        return $parentId > 0 ? $parentId : null;
    }

    public function previousSibling(array $items, array $target): ?array {
        $siblings = array_values(array_filter($items, function (array $item) use ($target): bool {
            return (int)($item['parent_id'] ?? 0) === (int)($target['parent_id'] ?? 0)
                && (int)$item['position'] < (int)$target['position'];
        }));

        return count($siblings) === 0 ? null : $siblings[count($siblings) - 1];
    }

    public function maxSubtreeLevel(array $items, int $topId): int {
        $max = 1;
        foreach ($this->subtreeIds($items, $topId) as $id) {
            $item = $this->findItem($items, $id);
            if ($item !== null) {
                $max = max($max, (int)$item['level']);
            }
        }

        return $max;
    }

    public function subtreeIds(array $items, int $topId): array {
        $childrenByParent = [];
        foreach ($items as $item) {
            $parentId = (int)($item['parent_id'] ?? 0);
            if ($parentId > 0) {
                $childrenByParent[$parentId][] = (int)$item['id'];
            }
        }

        $ids = [];
        $walk = function (int $id) use (&$walk, &$ids, $childrenByParent): void {
            $ids[] = $id;
            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $walk($childId);
            }
        };
        $walk($topId);

        return $ids;
    }
}
