<?php

namespace App\Support\Recruitment;

use App\Support\AppSettings;

/**
 * The shape every requisition follows (REC-WZ2, REC-TPL-001) and the lines a new one starts with.
 *
 * The sections are fixed: the job posting (Tentang Posisi, Tanggung Jawab Utama with subtitles of the requester's
 * own, Benefit & Fasilitas) and the candidate checklist (Kualifikasi: Pendidikan and Pengalaman; Skills: Hard Skills
 * and Soft Skills; Nilai Plus). What HR sets in the admin panel is the lines each starts with, so the base
 * persyaratan and benefits are the same for every listing. A requisition copies them when it is started and keeps its
 * own after that: changing the template never rewrites a request that is already under way.
 */
class RequisitionTemplate
{
    public const KEY = 'recruitment.template';

    /** The checklist's fixed subtitles, in order: key => label. */
    public const CHECKLIST = [
        'education' => 'Pendidikan',
        'experience' => 'Pengalaman',
        'hard' => 'Hard Skills',
        'soft' => 'Soft Skills',
        'bonus' => 'Nilai Plus',
    ];

    /** The required sections of the checklist and which subtitles make them up. Nilai Plus is not required. */
    public const REQUIRED = [
        'qualification' => ['label' => 'Kualifikasi', 'keys' => ['education', 'experience']],
        'skills' => ['label' => 'Skills', 'keys' => ['hard', 'soft']],
    ];

    public const MAX_LINES = 20;

    public const MAX_LINE_LENGTH = 300;

    /**
     * The benefits every listing carries until HR changes them.
     *
     * @var list<string>
     */
    private const DEFAULT_BENEFITS = [
        'BPJS Kesehatan & BPJS Ketenagakerjaan.',
        'Insentif dan bonus kinerja sesuai kebijakan perusahaan.',
        'Tunjangan Hari Raya (THR) sesuai ketentuan yang berlaku.',
        'Kesempatan mengikuti pelatihan dan pengembangan kompetensi.',
        'Budaya kerja yang profesional, kolaboratif, dan berorientasi pada pengembangan karyawan.',
        'Fasilitas kerja yang mendukung produktivitas karyawan.',
        'Employee gathering dan kegiatan perusahaan.',
    ];

    public function __construct(private AppSettings $settings) {}

    /**
     * The lines HR has set, or the built-in ones for what was never set. A saved empty list stays empty, so
     * clearing the benefits sticks instead of bringing the defaults back.
     *
     * @return array{benefits: list<string>, education: list<string>, experience: list<string>, hard: list<string>, soft: list<string>, bonus: list<string>}
     */
    public function current(): array
    {
        $saved = $this->settings->get(self::KEY, []);
        $saved = is_array($saved) ? $saved : [];

        return [
            'benefits' => array_key_exists('benefits', $saved) ? self::clean($saved['benefits']) : self::DEFAULT_BENEFITS,
            'education' => self::clean($saved['education'] ?? []),
            'experience' => self::clean($saved['experience'] ?? []),
            'hard' => self::clean($saved['hard'] ?? []),
            'soft' => self::clean($saved['soft'] ?? []),
            'bonus' => self::clean($saved['bonus'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $lines  keyed like `current()`
     */
    public function save(array $lines): void
    {
        $this->settings->set(self::KEY, [
            'benefits' => self::clean($lines['benefits'] ?? []),
            'education' => self::clean($lines['education'] ?? []),
            'experience' => self::clean($lines['experience'] ?? []),
            'hard' => self::clean($lines['hard'] ?? []),
            'soft' => self::clean($lines['soft'] ?? []),
            'bonus' => self::clean($lines['bonus'] ?? []),
        ]);
    }

    /**
     * What a new requisition starts with: the template's lines, and nothing the requester has to type is filled.
     *
     * @return array{about: string, responsibilities: list<array{title: string, items: list<string>}>, benefits: list<string>, checklist: array<string, list<string>>}
     */
    public function startingContent(): array
    {
        $current = $this->current();

        return [
            'about' => '',
            'responsibilities' => [],
            'benefits' => $current['benefits'],
            'checklist' => [
                'education' => $current['education'],
                'experience' => $current['experience'],
                'hard' => $current['hard'],
                'soft' => $current['soft'],
                'bonus' => $current['bonus'],
            ],
        ];
    }

    /**
     * A textarea with one line per point, as a list: trimmed, blanks dropped.
     *
     * @return list<string>
     */
    public static function fromText(?string $text): array
    {
        return self::clean(preg_split('/\R/u', (string) $text) ?: []);
    }

    /**
     * A list of lines as one text, a line per point.
     *
     * @param  list<string>  $lines
     */
    public static function toText(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * Trimmed lines with the blank ones dropped.
     *
     * @return list<string>
     */
    public static function clean(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $line): string => is_scalar($line) ? trim((string) $line) : '', $lines),
            fn (string $line): bool => $line !== '',
        ));
    }
}
