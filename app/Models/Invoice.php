<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Billing\InvoiceLineBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $type
 * @property int $amount_minor
 * @property list<array{description: string, amount_minor: int}>|null $lines
 * @property string $status
 * @property string|null $gateway_txn_ref
 * @property int|null $pdf_asset_id
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read MediaAsset|null $pdfAsset
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePdfAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice withoutTenancy()
 *
 * @mixin \Eloquent
 */
class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'lines' => 'array',
    ];

    /**
     * Canonical display breakdown for school invoices, including a zero-value
     * registration line when that fee was waived for a top-up.
     *
     * @return list<array{description: string, amount_minor: int}>
     */
    public function breakdownLines(): array
    {
        $lines = collect($this->lines ?? [])->map(function (array $line): array {
            $description = strtolower(trim($line['description']));

            if (str_contains($description, 'student') && str_contains($description, 'school')) {
                $line['description'] = InvoiceLineBuilder::STUDENT_SCHOOL_FEES;
            } elseif (str_contains($description, 'registration')) {
                $line['description'] = InvoiceLineBuilder::REGISTRATION_FEES;
            }

            return [
                'description' => $line['description'],
                'amount_minor' => (int) $line['amount_minor'],
            ];
        })->values();

        $hasStudentFees = $lines->contains('description', InvoiceLineBuilder::STUDENT_SCHOOL_FEES);
        $hasRegistrationFees = $lines->contains('description', InvoiceLineBuilder::REGISTRATION_FEES);

        if ($hasStudentFees && ! $hasRegistrationFees) {
            $studentIndex = $lines->search(fn (array $line) => $line['description'] === InvoiceLineBuilder::STUDENT_SCHOOL_FEES);
            $lines->splice((int) $studentIndex + 1, 0, [[
                'description' => InvoiceLineBuilder::REGISTRATION_FEES,
                'amount_minor' => 0,
            ]]);
        }

        /** @var list<array{description: string, amount_minor: int}> $result */
        $result = $lines->all();

        return $result;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function pdfAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'pdf_asset_id');
    }
}
