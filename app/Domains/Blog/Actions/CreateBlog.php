<?php

namespace App\Domains\Blog\Actions;

use App\Domains\Blog\Models\Blog;
use App\Domains\Blog\Models\Tag;
use App\Domains\Core\Models\Site;
use App\Domains\Core\Services\ImageUploadService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CreateBlog
{
    /**
     * Create a new blog.
     */
    public function execute(Site $site, User $user, array $data): Model
    {
        // Handle featured image upload
        $featuredImageUrl = $this->handleFeaturedImage();

        // Handle published_at: auto-set when creating with status 'published' and no explicit published_at given
        // Mirrors the logic in UpdateBlog so publish-on-create and publish-on-update behave identically
        $status = $data['status'] ?? 'draft';
        $publishedAt = $data['published_at'] ?? null;
        if ($status === 'published' && ! $publishedAt) {
            $publishedAt = now();
        }

        $blog = Blog::create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'author_name' => $data['author_name'] ?? null,
            'last_edited_by' => $user->id,
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'featured_image' => $featuredImageUrl,
            'alt_featured_image' => $data['alt_featured_image'] ?? null,
            'status' => $status,
            'published_at' => $publishedAt,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'primary_category_id' => $data['primary_category_id'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'og_type' => $data['og_type'] ?? null,
            'og_title' => $data['og_title'] ?? null,
            'og_description' => $data['og_description'] ?? null,
            'og_image' => $data['og_image'] ?? null,
            'og_image_alt' => $data['og_image_alt'] ?? null,
            'twitter_title' => $data['twitter_title'] ?? null,
            'twitter_description' => $data['twitter_description'] ?? null,
            'twitter_image' => $data['twitter_image'] ?? null,
            'twitter_site' => $data['twitter_site'] ?? null,
        ]);

        // Attach tags if provided
        if (! empty($data['tags'])) {
            $this->attachTags($blog, $data['tags'], $site->id);
        }

        return $blog;
    }

    /**
     * Handle featured image upload.
     */
    private function handleFeaturedImage(): ?string
    {
        // Check if file was uploaded
        if (request()->hasFile('featured_image')) {
            $file = request()->file('featured_image');
            $imageUploadService = new ImageUploadService;

            return $imageUploadService->handleFileUpload($file, 'blogs');
        }

        return null;
    }

    /**
     * Attach tags to the blog.
     */
    protected function attachTags(Blog $blog, array $tagIds, int $siteId): void
    {
        $tags = Tag::whereIn('id', $tagIds)
            ->where('site_id', $siteId)
            ->get();

        $pivotData = $tags->mapWithKeys(function ($tag) use ($siteId) {
            return [$tag->id => ['site_id' => $siteId]];
        });

        $blog->tags()->attach($pivotData);
    }
}
