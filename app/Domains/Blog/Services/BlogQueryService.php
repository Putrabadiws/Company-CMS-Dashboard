<?php

namespace App\Domains\Blog\Services;

use App\Domains\Blog\Models\Blog;
use App\Domains\Core\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class BlogQueryService
{
    /**
     * Get paginated blogs with filters and sorting.
     */
    public function getPaginatedBlogs(Site $site, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Blog::query()
            ->forSite($site)
            ->with(['author', 'primaryCategory', 'tags']);

        // Default to published if no status filter is provided
        // If status=all, don't apply any status filter (show all statuses)
        if (empty($filters['status'])) {
            $query->published();
        } elseif (strtolower($filters['status']) === 'all') {
            // Don't apply any status filter - show all blogs regardless of status
            unset($filters['status']);
        }

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $this->applySorting($query, $filters['sort'] ?? null);

        return $query->paginate($perPage);
    }

    /**
     * Get blogs with limit (without pagination).
     */
    public function getBlogs(Site $site, array $filters = [], ?int $limit = null): Collection
    {
        $query = Blog::query()
            ->forSite($site)
            ->with(['author', 'primaryCategory', 'tags']);

        // Default to published if no status filter is provided
        // If status=all, don't apply any status filter (show all statuses)
        if (empty($filters['status'])) {
            $query->published();
        } elseif (strtolower($filters['status']) === 'all') {
            // Don't apply any status filter - show all blogs regardless of status
            unset($filters['status']);
        }

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $this->applySorting($query, $filters['sort'] ?? null);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get a single blog by slug.
     */
    public function getBlogBySlug(Site $site, string $slug): ?Blog
    {
        return Blog::query()
            ->forSite($site)
            ->with(['author', 'editor', 'primaryCategory', 'tags', 'site'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Apply filters to the query.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Search query
        if (! empty($filters['q'])) {
            $query->fullTextSearch($filters['q'], ['title', 'excerpt', 'content']);
        }

        // Status filter
        // Note: status=all is handled in getPaginatedBlogs/getBlogs methods
        // If status filter exists here, it means it's a specific status (published, draft, scheduled)
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Featured filter (if is_featured field exists)
        if (isset($filters['is_featured'])) {
            // Check if column exists before applying filter
            if (Schema::hasColumn('blogs', 'is_featured')) {
                $query->where('is_featured', (bool) $filters['is_featured']);
            } elseif ((bool) $filters['is_featured']) {
                // Fallback: use most viewed or latest published blog as featured
                $query->orderBy('view_count', 'desc')->orderBy('published_at', 'desc');
            }
        }

        // Category filter
        if (! empty($filters['category'])) {
            $this->applyCategoryFilter($query, $filters['category']);
        }

        // Tag filter
        if (! empty($filters['tag'])) {
            $this->applyTagFilter($query, $filters['tag']);
        }

        // Author filter
        if (! empty($filters['author'])) {
            $this->applyAuthorFilter($query, $filters['author']);
        }
    }

    /**
     * Apply category filter.
     */
    protected function applyCategoryFilter(Builder $query, string $category): void
    {
        $query->whereHas('primaryCategory', function (Builder $q) use ($category) {
            if (is_numeric($category)) {
                $q->where('id', $category);
            } else {
                $q->where('slug', $category);
            }
        });
    }

    /**
     * Apply tag filter.
     */
    protected function applyTagFilter(Builder $query, string $tag): void
    {
        $query->whereHas('tags', function (Builder $q) use ($tag) {
            if (is_numeric($tag)) {
                $q->where('id', $tag);
            } else {
                $q->where('slug', $tag);
            }
        });
    }

    /**
     * Apply author filter.
     */
    protected function applyAuthorFilter(Builder $query, string $author): void
    {
        $query->whereHas('author', function (Builder $q) use ($author) {
            if (is_numeric($author)) {
                $q->where('users.id', $author); // Specify table name to avoid ambiguity
            } else {
                // Could be email or name - try email first, then name
                $q->where('users.email', $author)
                    ->orWhere('users.name', 'like', '%'.$author.'%');
            }
        });
    }

    /**
     * Apply sorting to the query.
     * Default: published_at DESC (terbaru).
     */
    protected function applySorting(Builder $query, ?string $sort): void
    {
        $sortMap = [
            'published_at' => ['published_at', 'asc'], // Oldest first
            '-published_at' => ['published_at', 'desc'], // Newest first (default)
            'title' => ['title', 'asc'],
            '-title' => ['title', 'desc'],
            'created_at' => ['created_at', 'asc'], // Oldest first
            '-created_at' => ['created_at', 'desc'], // Newest first
            'view_count' => ['view_count', 'asc'], // Lowest first
            '-view_count' => ['view_count', 'desc'], // Highest first
            'updated_at' => ['updated_at', 'asc'], // Oldest first
            '-updated_at' => ['updated_at', 'desc'], // Newest first
        ];

        if ($sort && isset($sortMap[$sort])) {
            $sortValue = $sortMap[$sort];
            if (is_array($sortValue)) {
                $query->orderBy($sortValue[0], $sortValue[1]);
            } else {
                $query->orderBy($sortValue);
            }
        } else {
            // Default: urutkan berdasarkan published_at terbaru (DESC)
            $query->orderBy('published_at', 'desc');
        }
    }
}
