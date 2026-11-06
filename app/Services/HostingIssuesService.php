<?php

declare(strict_types=1);

namespace App\Services;

class HostingIssuesService
{
    private static ?array $cached = null;

    /**
     * Complete catalogue of 70 realistic hosting support issues from E:\agency\content\hosting_support_issues.md
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $filePath = dirname(__DIR__, 2) . '/resources/data/hosting_issues.json';
        if (!file_exists($filePath)) {
            return [];
        }

        self::$cached = json_decode((string) file_get_contents($filePath), true) ?? [];
        return self::$cached;
    }

    /**
     * Find single issue by ID (1-70)
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        foreach (self::all() as $issue) {
            if ($issue['id'] === $id) {
                return $issue;
            }
        }
        return null;
    }

    /**
     * Group all 70 issues by Category (A to L)
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::all() as $issue) {
            $cat = $issue['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $issue;
        }
        return $grouped;
    }
}
