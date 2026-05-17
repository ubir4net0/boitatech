<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PortalNoticia extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'portal.noticias';

    protected $fillable = [
        'source_key',
        'source_name',
        'external_id',
        'title',
        'excerpt',
        'image_url',
        'url',
        'canonical_url',
        'source_url_hash',
        'title_hash',
        'content_hash',
        'normalized_title',
        'title_signature',
        'language',
        'country',
        'category',
        'published_at',
        'ingested_at',
        'is_featured',
        'quality_score',
        'review_status',
        'review_reason',
        'curation_score',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'ingested_at' => 'datetime',
        'is_featured' => 'bool',
        'quality_score' => 'int',
        'curation_score' => 'int',
        'metadata' => 'array',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('review_status', 'approved')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->utc());
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('review_status', 'approved');
    }
}
