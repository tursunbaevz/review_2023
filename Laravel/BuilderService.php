<?php

namespace App\Services;

use App\Models\Venue;
use App\Models\Builder;
use Illuminate\Filesystem\Filesystem;

class BuilderService
{
    public function imgPublish(
        Builder $builder,
        $key,
        $columnDraft,
        $oldPublishPath,
        $imgName,
        $folder
    ) {
        $draft = json_decode($builder->$columnDraft);
        $file = new Filesystem();

        // delete publish img if draft img path is empty
        if (empty($draft->{$imgName})) {
            if ($file->exists(public_path('storage/' . $oldPublishPath))) {
                $file->delete(public_path('storage/' . $oldPublishPath));
            }

            $builder->update(["{$key}->{$imgName}" => '']);
            return;
        }

        // paths
        $publishPath = "storage/builder/{$builder->subdomain}/{$folder}/publish";
        $draftPathFile = 'storage/' . $draft->{$imgName};

        // replace path
        $publishPathFile = str_replace('draft', 'publish', $draft->{$imgName});

        // create folder
        $path = public_path($publishPath);

        if (!file_exists($path)) {
            mkdir($publishPath);
        }

        // move copy img to publish folder
        copy($draftPathFile, 'storage/' . $publishPathFile);

        // update publish data with new img path
        $builder->update(["{$key}->{$imgName}" => $publishPathFile]);

        // updated publish data
        $publish = json_decode($builder->$key);

        // delete publish old img
        if (empty($publish->$imgName) || $oldPublishPath != $publish->$imgName) {
            if ($file->exists(public_path('storage/' . $oldPublishPath))) {
                $file->delete(public_path('storage/' . $oldPublishPath));
            }
        }
    }

    public function galleryPublish(Builder $builder)
    {
        $publishPath = "storage/builder/{$builder->subdomain}/gallery/publish";

        // create folder
        if (!file_exists(public_path($publishPath))) {
            mkdir($publishPath);
        }

        // update or create images
        $builder->builderGalleries->each(function ($item) {
            $replacedPath = str_replace(BuilderGallery::DRAFT, BuilderGallery::PUBLISH, $item->image);

            if ($item->status == BuilderGallery::DRAFT) {
                if (!$item->relation) {
                    // create new publish images
                    $newPublish = BuilderGallery::create([
                        'builder_id' => $item->builder_id,
                        'order' => $item->order,
                        'title' => $item->title,
                        'image' => $replacedPath,
                        'status' => BuilderGallery::PUBLISH,
                    ]);

                    // update draft record with new publish id as child
                    $item->update(['relation' => $newPublish->id]);

                    // copy to publish folder if new image exist
                    if (file_exists('storage/' . $item->image)) {
                        copy('storage/' . $item->image, 'storage/' . $newPublish->image);
                    }
                } else {
                    // update publish record
                    BuilderGallery::where('id', '=', $item->relation)->update([
                        'order' => $item->order,
                        'title' => $item->title,
                    ]);
                }
            }
        });

        // remove images from publish folder (should be moved to trash folder for RESET functionality)
        $file = new Filesystem();
        $onlySoftDeleted = BuilderGallery::onlyTrashed()->get();

        $onlySoftDeleted->each(function ($item) use ($file) {
            $replacedPath = str_replace(BuilderGallery::DRAFT, BuilderGallery::PUBLISH, $item->image);
            if (file_exists('storage/' . $replacedPath)) {
                $file->delete('storage/' . $replacedPath);
                BuilderGallery::where('id', '=', $item->relation)->delete();
            }
        });

        // update gallery status
        $builder->update(['gallery_status' => 0]);
    }

    public function updateSeoSettings(Builder $builder, Venue $venue, $pages)
    {
        $locale = collect(explode('.', $builder->default_domain))->last();

        app()->setLocale($locale);

        $seoSettings = [];

        foreach ($pages as $page) {
            $seoSettings[$page->page] = [
                'metaTitle' => trans($page->page) . " | {$venue->title}",
                'premiumMetaTitle' => $venue->categories->first()?->title .
                    ' ' . $venue->location?->title . " | {$venue->title}",
                'metaDescription' => '',
            ];
        }

        $builder->update(['seo_settings' => $seoSettings]);
    }
}
