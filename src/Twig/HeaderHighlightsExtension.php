<?php

declare(strict_types=1);

namespace Moderna\Twig;

use Propel\Runtime\Propel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides header highlights data for the homepage hero section.
 *
 * Note: This extension uses raw SQL because the header_highlights table is managed
 * by an external Thelia module (HeaderHighlights) which does not expose Propel models
 * to the template bundle. If the module provides Query classes in the future,
 * this should be refactored to use them (e.g., HeaderHighlightsQuery).
 */
class HeaderHighlightsExtension extends AbstractExtension
{
    private const IMAGE_BASE_PATH = '/local/media/images/headerHighlights/';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('headerHighlights', [$this, 'getHeaderHighlights']),
        ];
    }

    /**
     * Get header highlights for a specific display type and locale.
     *
     * @param string $displayType Display type filter (e.g., 'desktop', 'mobile')
     * @param string $locale Locale for i18n content (e.g., 'fr_FR', 'en_US')
     * @return array<int, array{ID: int, TITLE: string, CTA: string, URL: string, CATCHPHRASE: string, IMAGE_URL: string, IMAGE_BLOCK: int|null, CATEGORY_ID: int|null}>
     */
    public function getHeaderHighlights(string $displayType = 'desktop', string $locale = 'fr_FR'): array
    {
        try {
            $connection = Propel::getConnection();

            $sql = "
                SELECT
                    h.id,
                    h.display_type,
                    h.image_block,
                    h.category_id,
                    hi18n.title,
                    hi18n.call_to_action as cta,
                    hi18n.url,
                    hi18n.catchphrase,
                    img.file as image_file
                FROM header_highlights h
                LEFT JOIN header_highlights_i18n hi18n ON h.id = hi18n.id AND hi18n.locale = :locale
                LEFT JOIN header_highlights_image img ON h.id = img.header_highlights_id
                WHERE h.display_type = :display_type
                ORDER BY h.image_block ASC
            ";

            $stmt = $connection->prepare($sql);
            $stmt->execute([
                ':display_type' => $displayType,
                ':locale' => $locale
            ]);

            $results = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $results[] = $this->mapRowToHighlight($row);
            }

            return $results;
        } catch (\Exception $e) {
            // Log error and return empty array to avoid breaking the page
            // In production, consider using a proper logger
            return [];
        }
    }

    /**
     * Map a database row to a highlight array.
     */
    private function mapRowToHighlight(array $row): array
    {
        $imageUrl = '';
        if (!empty($row['image_file'])) {
            $imageUrl = self::IMAGE_BASE_PATH . $row['image_file'];
        }

        return [
            'ID' => (int) $row['id'],
            'TITLE' => $row['title'] ?? '',
            'CTA' => $row['cta'] ?? '',
            'URL' => $row['url'] ?? '',
            'CATCHPHRASE' => $row['catchphrase'] ?? '',
            'IMAGE_URL' => $imageUrl,
            'IMAGE_BLOCK' => $row['image_block'] !== null ? (int) $row['image_block'] : null,
            'CATEGORY_ID' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        ];
    }
}
