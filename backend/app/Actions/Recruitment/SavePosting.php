<?php

namespace App\Actions\Recruitment;

use App\Enums\JobPlatform;
use App\Models\Requisition;
use App\Models\RequisitionPosting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * HR records that the listing is up on a platform, or corrects where, when or the link (REC-PST-002). One record per
 * named platform; "Lainnya" may be used more than once, for different sites.
 */
class SavePosting
{
    /**
     * @param  array{platform: string, platform_name: string|null, url: string|null, posted_on: string}  $fields
     *
     * @throws ValidationException the platform is already recorded for this requisition
     */
    public function handle(Requisition $requisition, array $fields, User $by, ?RequisitionPosting $posting = null): RequisitionPosting
    {
        $platform = JobPlatform::from($fields['platform']);

        if ($platform !== JobPlatform::Other) {
            $taken = $requisition->postings()->where('platform', $platform->value)
                ->when($posting !== null, fn ($query) => $query->whereKeyNot($posting?->id))
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'platform' => "Lowongan ini sudah dicatat di {$platform->label()}. Ubah catatan yang ada.",
                ]);
            }
        }

        $posting ??= new RequisitionPosting(['requisition_id' => $requisition->id, 'posted_by_user_id' => $by->id]);

        $posting->fill([
            'platform' => $platform,
            'platform_name' => $platform === JobPlatform::Other ? $fields['platform_name'] : null,
            'url' => $fields['url'],
            'posted_on' => $fields['posted_on'],
        ])->save();

        return $posting;
    }
}
