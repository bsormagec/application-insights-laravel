<?php

namespace Sormagec\AppInsightsLaravel\Support;

trait PathExclusionTrait
{
    /**
     * Check if the current request path should be excluded from tracking.
     *
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    protected function shouldExcludeRequest($request): bool
    {
        $excludedPaths = Config::get('excluded_paths', []);

        if (empty($excludedPaths)) {
            return false;
        }

        $path = $request->path();

        foreach ($excludedPaths as $pattern) {
            if ($this->pathMatchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a pattern (supports wildcards).
     *
     * @param string $path The URL path to check
     * @param string $pattern The pattern to match (supports * wildcard)
     * @return bool
     */
    protected function pathMatchesPattern(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Convert wildcard pattern to regex
        // Escape special regex characters except *
        $regex = preg_quote($pattern, '/');
        // Replace escaped \* with regex .*
        $regex = str_replace('\*', '.*', $regex);
        // Add anchors
        $regex = '/^' . $regex . '$/';

        return preg_match($regex, $path) === 1;
    }
}
