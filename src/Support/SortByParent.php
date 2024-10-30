<?php

namespace Statamic\Importer\Support;

class SortByParent
{
    public function sort(array $items): array
    {
        // Create a map of item IDs to their items for quick access
        $itemsById = collect($items)->keyBy('id')->all();
        $sorted = [];
        $addedIds = [];

        // Start adding items with their children
        foreach ($itemsById as $item) {
            // Check if the item has already been added
            if (! in_array($item['id'], $addedIds)) {
                $this->addItemWithChildren($itemsById, $sorted, $addedIds, $item['parent']);
            }
        }

        return $sorted;
    }

    private function addItemWithChildren(array $itemsById, array &$sorted, array &$addedIds, int|string $parentId): void
    {
        // First, add the parent item if it exists and hasn't been added yet
        if ($parentId && isset($itemsById[$parentId]) && ! in_array($parentId, $addedIds)) {
            $parentItem = $itemsById[$parentId];
            $sorted[] = $parentItem;
            $addedIds[] = $parentId;
        }

        // Now, add the children of the current parent
        foreach ($itemsById as $item) {
            if ($item['parent'] === $parentId && ! in_array($item['id'], $addedIds)) {
                $sorted[] = $item;
                $addedIds[] = $item['id'];

                // Recursively add its children
                $this->addItemWithChildren($itemsById, $sorted, $addedIds, $item['id']);
            }
        }
    }
}
